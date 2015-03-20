<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * CRON events.
 *
 * @author		Receiptful
 * @version		1.0.0
 * @since		1.0.0
 */


/**
 * 15 minute interval.
 *
 * Add a 15 minute interval to the cron schedules.
 *
 * @since 1.0.0
 *
 * @param 	array $schedules	List of current CRON schedules.
 * @return 	array				List of modified CRON schedules.
 */
function receiptful_add_quarter_schedule( $schedules ) {

	$schedules['quarter_hour'] = array(
		'interval' 	=> 60 * 15, // 60 seconds * 15 minutes
		'display' 	=> __( 'Every quarter', 'receiptful' ),
	);

	return $schedules;

}
add_filter( 'cron_schedules', 'receiptful_add_quarter_schedule' );


/**
 * Schedule events.
 *
 * Schedule the resend of receipts to fire every 15 minutes
 * Scheduled outside class because working with objects isn't
 * perfect while doing events.
 *
 * @since 1.0.0
 */
function receiptful_schedule_event() {

	// Resend queue
	if ( ! wp_next_scheduled( 'receiptful_check_resend' ) ) {
		wp_schedule_event( 1407110400, 'quarter_hour', 'receiptful_check_resend' ); // 1407110400 is 08 / 4 / 2014 @ 0:0:0 UTC
	}

	// Initial product sync
	if ( ! wp_next_scheduled( 'receiptful_initial_product_sync' ) && 1 != get_option( 'receiptful_completed_initial_product_sync', 0 ) ) {
		wp_schedule_event( 1407110400, 'quarter_hour', 'receiptful_initial_product_sync' ); // 1407110400 is 08 / 4 / 2014 @ 0:0:0 UTC
	} elseif ( wp_next_scheduled( 'receiptful_initial_product_sync' ) && 1 == get_option( 'receiptful_completed_initial_product_sync', 0 ) ) {
		wp_clear_scheduled_hook( 'receiptful_initial_product_sync' ); // Remove CRON when we're done with it.
	}

	// Initial receipt sync
	if ( ! wp_next_scheduled( 'receiptful_initial_receipt_sync' ) && 1 != get_option( 'receiptful_completed_initial_receipt_sync', 0 ) ) {
		wp_schedule_event( 1407110400, 'quarter_hour', 'receiptful_initial_receipt_sync' ); // 1407110400 is 08 / 4 / 2014 @ 0:0:0 UTC
	} elseif ( wp_next_scheduled( 'receiptful_initial_receipt_sync' ) && 1 == get_option( 'receiptful_completed_initial_receipt_sync', 0 ) ) {
		wp_clear_scheduled_hook( 'receiptful_initial_receipt_sync' ); // Remove CRON when we're done with it.
	}

}
add_action( 'init', 'receiptful_schedule_event' );


/**
 * Resend queue.
 *
 * Function is called every 15 minutes by a CRON job.
 * This fires the resend of Receipts and data that should be synced.
 *
 * @since 1.0.0
 */
function receiptful_check_resend() {

	// Receipt queue
	Receiptful()->email->resend_queue();

	// Products queue
	Receiptful()->products->process_queue();

}
add_action( 'receiptful_check_resend', 'receiptful_check_resend' );


/**
 * Sync product data.
 *
 * Sync data with the Receiptful API, this contains products for now.
 * The products are synced with Receiptful to give the best product recommendations.
 * This is a initial product sync, the process should be completed once.
 *
 * @since 1.0.2
 */
function receiptful_initial_product_sync() {

	$download_ids = get_posts( array(
		'fields'			=> 'ids',
		'posts_per_page'	=> '225',
		'post_type'			=> 'download',
		'meta_query'		=> array(
			array(
				'key'		=> '_receiptful_last_update',
				'compare'	=> 'NOT EXISTS',
				'value'		=> '',
			),
		),
	) );

	// Update option so the system knows it should stop syncing
	if ( empty ( $download_ids ) ) {
		update_option( 'receiptful_completed_initial_product_sync', 1 );
		return;
	}

	// Get products args
	$args = array();
	foreach ( $download_ids as $download_id ) {
		$args[] = Receiptful()->products->get_formatted_product( $download_id );
	}

	// Update products
	$response = Receiptful()->api->update_products( $args );

	// Process response
	if ( is_wp_error( $response ) ) {

		return false;

	} elseif ( in_array( $response['response']['code'], array( '400' ) ) ) {

		// Set empty update time, so its not retried at next CRON job
		foreach ( $download_ids as $download_id ) {
			update_post_meta( $download_id, '_receiptful_last_update', '' );
		}

	} elseif ( in_array( $response['response']['code'], array( '200', '202' ) ) ) { // Update only the ones without error - retry the ones with error

		$failed_ids = array();
		$body 		= json_decode( $response['body'], 1 );
		foreach ( $body['errors'] as $error ) {
			$failed_ids[] = isset( $error['error']['product_id'] ) ? $error['error']['product_id'] : null;
		}

		// Set empty update time, so its not retried at next CRON job
		foreach ( $download_ids as $download_id ) {
			if ( ! in_array( $download_id, $failed_ids ) ) {
				update_post_meta( $download_id, '_receiptful_last_update', time() );
			} else {
				update_post_meta( $download_id, '_receiptful_last_update', '' );
			}
		}

	} elseif ( in_array( $response['response']['code'], array( '401', '500', '503' ) ) ) { // Retry later - keep meta unset
	}

}
add_action( 'receiptful_initial_product_sync', 'receiptful_initial_product_sync' );


/**
 * Sync Receipt data.
 *
 * Sync data with the Receiptful API, this contains products for now.
 * The products are synced with Receiptful to give the best product recommendations.
 * This is a initial product sync, the process should be completed once.
 *
 * @since 1.0.2
 */
function receiptful_initial_receipt_sync() {

	$receipt_ids = get_posts( array(
		'fields'			=> 'ids',
		'posts_per_page'	=> '225',
		'post_type'			=> 'edd_payment',
		'meta_query'		=> array(
			array(
				'key'		=> '_receiptful_last_update',
				'compare'	=> 'NOT EXISTS',
				'value'		=> '',
			),
			array(
				'key'		=> '_receiptful_receipt_id',
				'compare'	=> 'NOT EXISTS',
				'value'		=> '',
			),
		),
	) );

	// Update option so the system knows it should stop syncing
	if ( empty ( $receipt_ids ) ) {
		update_option( 'receiptful_completed_initial_receipt_sync', 1 );
		return;
	}

	// Prepare product args
	$args = array();
	foreach ( $receipt_ids as $receipt_id ) {

		$items 				= Receiptful()->email->api_args_get_items( $receipt_id );
		$subtotals 			= Receiptful()->email->api_args_get_subtotals( $receipt_id );
		$related_downloads 	= Receiptful()->email->api_args_get_related_downloads( $items );
		$order_args 		= Receiptful()->email->api_args_get_order_args( $receipt_id, $items, $subtotals, $related_downloads );

		$args[] = $order_args;

	}

	// Update products
	$response = Receiptful()->api->upload_receipts( $args );

	// Process response
	if ( is_wp_error( $response ) ) {

		return false;

	} elseif ( in_array( $response['response']['code'], array( '400' ) ) ) {

		// Set empty update time, so its not retried at next CRON job
		foreach ( $receipt_ids as $receipt_id ) {
			update_post_meta( $receipt_id, '_receiptful_last_update', '' );
		}

	} elseif ( in_array( $response['response']['code'], array( '200', '202' ) ) ) { // Update only the ones without error - retry the ones with error

		$failed_ids = array();
		$body 		= json_decode( $response['body'], 1 );
		foreach ( $body['errors'] as $error ) {
			$failed_ids[] = isset( $error['error']['product_id'] ) ? $error['error']['product_id'] : null;
		}

		// Set empty update time, so its not retried at next CRON job
		foreach ( $receipt_ids as $receipt_id ) {
			if ( ! in_array( $receipt_id, $failed_ids ) ) {
				update_post_meta( $receipt_id, '_receiptful_last_update', time() );
			} else {
				update_post_meta( $receipt_id, '_receiptful_last_update', '' );
			}
		}

	} elseif ( in_array( $response['response']['code'], array( '401', '500', '503' ) ) ) { // Retry later - keep meta unset
	}

}
add_action( 'receiptful_initial_receipt_sync', 'receiptful_initial_receipt_sync' );
