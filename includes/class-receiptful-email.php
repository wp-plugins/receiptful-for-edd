<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Receiptful_Email.
 *
 * Admin class.
 *
 * @class		Receiptful_Email
 * @version		1.0.0
 * @author		Receiptful
 */
class Receiptful_Email {


	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->hooks();

	}


	/**
	 * Class hooks.
	 *
	 * @since 1.0.0
	 */
	public function hooks() {

		// Remove default EDD email
		remove_action( 'edd_complete_purchase', 'edd_trigger_purchase_receipt', 999 );

		// Remove EDD resend email action
		remove_action( 'edd_email_links', 'edd_resend_purchase_receipt', 10 );

		// Add Receiptful Email
		add_action( 'edd_complete_purchase', array( $this, 'send_transactional_email' ) );

		// Add Receiptful resend email action
		add_action( 'edd_email_links', array( $this, 'resend_transactional_email' ) );

		// Create coupon
		add_action( 'receiptful_add_upsell', array( $this, 'create_coupon' ), 10, 2 );

	}


	/**
	 * Send mail.
	 *
	 * Function to send Receiptful API call to send the receipt mail.
	 *
	 * @since 1.0.0
	 *
	 * @param 	int 			$payment_id 	ID of the payment.
	 * @return	array|WP_Error					Return the API response.
	 */
	public function send_transactional_email( $payment_id ) {

		// Make sure we don't send a purchase receipt while editing a payment
		if ( isset( $_POST['edd-action'] ) && 'edit_payment' == $_POST['edd-action'] ) {
			return;
		}

		$edd_settings = get_option( 'edd_settings' );
		$payment_data = edd_get_payment_meta( $payment_id );


		// Resend receipt when it already has a Receiptful ID
		if ( '' != $receiptful_id = get_post_meta( $payment_id, '_receiptful_receipt_id', true ) ) {
			$response = $this->resend_receipt( $payment_id, $receiptful_id );

			return $response;
		}


		// Purchase items
		$items = $this->api_args_get_items( $payment_id );

		// get all the subtotals that can include subtotal, tax, discount
		$subtotals = $this->api_args_get_subtotals( $payment_id );

		// Related downloads
		$related_downloads = $this->api_args_get_related_downloads( $items );

		// Purchase args
		$order_args = $this->api_args_get_order_args( $payment_id, $items, $subtotals, $related_downloads );


		// API Response
		$response = Receiptful()->api->receipt( $order_args );

		if ( is_wp_error( $response ) ) {

			edd_insert_payment_note( $payment_id, sprintf( __( 'Error sending customer receipt via Receiptful. Error Message: %s. Receipt added to resend queue.', 'receiptful' ), implode( ', ', $response->get_error_messages() ) ) );

			// queue the message for sending via cron
			$resend_queue = get_option( '_receiptful_resend_queue' );
			$resend_queue[ $payment_id ] = $payment_id;
			update_option( '_receiptful_resend_queue', $resend_queue );

		} elseif ( $response['response']['code'] == '201' ) {

			edd_insert_payment_note( $payment_id, __( 'Customer receipt sent via Receiptful.', 'receiptful' ) );
			$body = json_decode( $response ['body'], true);

			add_post_meta( $payment_id, '_receiptful_web_link', $body['_meta']['links'] );
			add_post_meta( $payment_id, '_receiptful_receipt_id', $body['_id'] );

			$upsell = $body['upsell'];
			if ( isset( $upsell['couponCode'] ) ) {
				do_action( 'receiptful_add_upsell', $upsell, $payment_id );
			}

		} elseif ( in_array( $response['response']['code'], array( '401', '500', '503' ) ) ) {

			edd_insert_payment_note( $payment_id, sprintf( __( 'Error sending customer receipt via Receiptful. Error Code: %1$s Error Message: %2$s. Receipt added to resend queue.', 'receiptful' ), $response['response']['code'], $response['response']['message'] ) );

			// queue the message for sending via cron
			$resend_queue = get_option( '_receiptful_resend_queue' );
			$resend_queue[ $payment_id ] = $payment_id;
			update_option( '_receiptful_resend_queue', $resend_queue );

		} else {

			edd_insert_payment_note( $payment_id, sprintf( __( 'Error sending customer receipt via Receiptful. Error Code: %s Error Message: %s.', 'receiptful' ), $response['response']['code'], $response['response']['message'] ) );

		}

		/**
		 * Admin email notice.
		 * Copied from original EDD receipt function to keep compatibility.
		 */
		if ( apply_filters( 'receiptful_send_admin_notice', true ) && ! edd_admin_notices_disabled( $payment_id ) ) {
			do_action( 'edd_admin_sale_notice', $payment_id, $payment_data );
		}

		do_action( 'receiptful_after_mail_send', $payment_id, $response );

		return $response;

	}


	/**
	 * Resend receipt.
	 *
	 * Resend the purchase receipt based on the receipt ID.
	 *
	 * @since 1.0.1
	 *
	 * @param	int		$payment_id		Payment ID, used to get payment information.
	 * @return	array					API response.
	 */
	public function resend_receipt( $payment_id, $receiptful_id ) {

		$response = Receiptful()->api->resend_receipt( $receiptful_id );

		if ( is_wp_error( $response ) ) {

			edd_insert_payment_note( $payment_id, sprintf( __( 'Error resending customer receipt via Receiptful. Error Message: %s. Receipt added to resend queue.', 'receiptful' ), implode( ', ', $response->get_error_messages() ) ) );

			// queue the message for sending via cron
			$resend_queue = get_option( '_receiptful_resend_queue' );
			$resend_queue[ $payment_id ] = $payment_id;
			update_option( '_receiptful_resend_queue', $resend_queue );

		} elseif ( $response['response']['code'] == '200' ) {

			edd_insert_payment_note( $payment_id, __( 'Customer receipt resent via Receiptful.', 'receiptful' ) );
			$body = json_decode( $response ['body'], true );

		} elseif ( in_array( $response['response']['code'], array( '401', '500', '503' ) ) ) {

			edd_insert_payment_note( $payment_id, sprintf( __( 'Error resending customer receipt via Receiptful. Error Code: %1$s Error Message: %2$s. Receipt added to resend queue.', 'receiptful' ), $response['response']['code'], $response['response']['message'] ) );

			// queue the message for sending via cron
			$resend_queue = get_option( '_receiptful_resend_queue' );
			$resend_queue[ $payment_id ] = $payment_id;
			update_option( '_receiptful_resend_queue', $resend_queue );

		} else {

			edd_insert_payment_note( $payment_id, sprintf( __( 'Error resending customer receipt via Receiptful. Error Code: %1$s Error Message: %2$s.', 'receiptful' ), $response['response']['code'], $response['response']['message'] ) );

		}

		do_action( 'receiptful_after_mail_resend', $payment_id, $response );

		return $response;

	}


	/**
	 * Get Items.
	 *
	 * Get the purchase items required for the API call.
	 *
	 * @since 1.0.1
	 *
	 * @param	int		$payment_id		Payment ID, used to get payment information.
	 * @return	array					List of items that are in the purchase.
	 */
	public function api_args_get_items( $payment_id ) {

		$items			= array();
		$payment_data	= edd_get_payment_meta( $payment_id );
		$meta_data		= array();

		foreach ( $payment_data['cart_details'] as $download ) {

			$edd_download	= new EDD_Download( $download['id'] );
			$price_id		= edd_get_cart_item_price_id( $download );
			$name 			= ! empty( $download['name'] ) ? $download['name'] : __( 'Nameless product', 'receiptful' );

			if ( ! is_null( $price_id ) ) {
				$name 	.= " - " . edd_get_price_option_name( $download['id'], $price_id, $payment_id );
			}

			// Meta
			if ( $download_note = $edd_download->get_notes() ) {
				$meta_data[] = array(
					'key'	=> __( 'Note', 'receiptful' ),
					'value'	=> $download_note,
				);
			}

			$items[] = array(
				'reference'		=> $download['id'],
				'description'	=> $name,
				'quantity'		=> $download['quantity'],
				'amount'		=> isset( $download['item_price'] ) ? number_format( (float) $download['item_price'], 2, '.', '' ) : number_format( 0, 2, '.', '' ),
				'downloadUrls'	=> $this->maybe_get_download_urls( $download['id'], $payment_id, $download ),
				'metas'			=> $meta_data,
			);

		}

		return apply_filters( 'receiptful_api_args_items', $items, $payment_id );

	}


	/**
	 * Get subtotals.
	 *
	 * Get the subtotals required for the API call.
	 *
	 * @since 1.0.1
	 *
	 * @param	int		$payment_id		Payment ID, used to get payment information.
	 * @return	array					List of subtotals to display on the Receipt.
	 */
	public function api_args_get_subtotals( $payment_id ) {

		$subtotals		= array();
		$payment_data	= edd_get_payment_meta( $payment_id );

		// Discount
		if ( 'none' != $payment_data['user_info']['discount'] ) {
			$discounts = wp_list_pluck( $payment_data['cart_details'], 'discount' );

			if ( is_array( $discounts ) ) {
				$discount_amount = array_sum( $discounts );
				$subtotals[] = array( 'description' => __( 'Discount', 'receiptful' ), 'amount' => number_format( (float) $discount_amount, 2, '.', '' ) );
			}
		}

		// Tax
		if ( edd_use_taxes() ) {

			// Subtotal
			$subtotals[] = array( 'description' => __( 'Subtotal', 'receiptful' ), 'amount' => number_format( (float) edd_get_payment_subtotal( $payment_id ), 2, '.', '' ) );
			// Tax
			$subtotals[] = array( 'description' => __( 'Taxes', 'receiptful' ), 'amount' => number_format( (float) edd_get_payment_tax( $payment_id ), 2, '.', '' ) );

		}

		return apply_filters( 'receiptful_api_args_subtotals', $subtotals, $payment_id );

	}


	/**
	 * Get related downloads.
	 *
	 * Get the related downloads based on the order items.
	 *
	 * @since 1.0.1
	 *
	 * @param	array $items	List of items in the purchase, list is equal to $this->api_args_get_items().
	 * @return	array			List of related downloads data.
	 */
	public function api_args_get_related_downloads( $items ) {

		$related_downloads		= array();
		$order_item				= reset( $items );
		$first_item_id			= $order_item['reference'];
		$related_downloads		= array();
		$related_download_ids	= edd_get_related_downloads( $first_item_id, 2 );

		// Fallback to random downloads when no related are found
		if ( ! $related_download_ids ) {
			$related_download_ids = receiptful_edd_get_random_downloads( 2 );
		}

		if ( $related_download_ids ) {
			foreach ( $related_download_ids as $related_id ) {

				$download		= edd_get_download( $related_id );
				$product_image	= wp_get_attachment_image_src( get_post_thumbnail_id( $download->ID ), array( 450, 450 ) );
				$content		= strip_tags( $download->post_content );
				$description	= strlen( $content ) <= 100 ? $content : substr( $content, 0, strrpos( $content, ' ', -( strlen( $content ) - 100 ) ) );

				$related_downloads[] = array(
					'title'			=> ! empty( $download->post_title ) ? $download->post_title : __( 'Nameless product', 'receiptful' ),
					'actionUrl'		=> get_permalink( $download->ID ),
					'image'			=> $product_image[0],
					'description'	=> $description,
				);

			}
		}

		return apply_filters( 'receiptful_api_args_reated_downloads', $related_downloads, $items );

	}


	/**
	 * Order args.
	 *
	 * Get the order args required for the API call.
	 *
	 * @since 1.0.1
	 *
	 * @param	int		$payment_id			Payment ID, used to get payment information.
	 * @param	array	$items				List of items to send to the API.
	 * @param	array	$subtotals			List of subtotals to send to the API.
	 * @param	array	$related_downloads	List of related products to send to the API.
	 * @return	array						Complete list of arguments to send to the API.
	 */
	public function api_args_get_order_args( $payment_id, $items, $subtotals, $related_downloads ) {

		$payment_data	= edd_get_payment_meta( $payment_id );
		$amount			= edd_get_payment_amount( $payment_id );

		$card_type		= '';
		$last4			= '';
		$customer_ip	= '';
		$from_email		= isset( $edd_settings['from_email'] ) ? $edd_settings['from_email'] : get_bloginfo( 'admin_email' );

		$order_args = array(
			'date'			=> date_i18n( 'c', strtotime( $payment_data['date'] ) ),
			'reference'		=> edd_get_payment_number( $payment_id ),
			'currency'		=> $payment_data['currency'],
			'amount'		=> number_format( (float) $amount, 2, '.', '' ),
			'to'			=> $payment_data['user_info']['email'],
			'from'			=> $from_email,
			'payment'		=> array(
				'type'	=> edd_get_gateway_checkout_label( edd_get_payment_gateway( $payment_id ) ),
				'last4'	=> $last4
			),
			'items'			=> $items,
			'subtotals'		=> $subtotals,
			'customerIp'	=> $customer_ip,
			'billing'		=> array(
				'address'	=> array(
					'firstName'		=> $payment_data['user_info']['first_name'],
					'lastName'		=> $payment_data['user_info']['last_name'],
					'company'		=> '',
					'addressLine1'	=> isset( $payment_data['user_info']['address']['line1'] ) 		? (string) $payment_data['user_info']['address']['line1'] 	: '',
					'addressLine2'	=> isset( $payment_data['user_info']['address']['line2'] ) 		? (string) $payment_data['user_info']['address']['line2'] 	: '',
					'city'			=> isset( $payment_data['user_info']['address']['city'] ) 		? (string) $payment_data['user_info']['address']['city'] 	: '',
					'state'			=> isset( $payment_data['user_info']['address']['state'] ) 		? (string) $payment_data['user_info']['address']['state'] 	: '',
					'postcode'		=> isset( $payment_data['user_info']['address']['zip'] ) 		? (string) $payment_data['user_info']['address']['zip'] 	: '',
					'country'		=> isset( $payment_data['user_info']['address']['country'] ) 	? (string) $payment_data['user_info']['address']['country'] : '',
				),
				'phone'	=> '',
				'email'	=> $payment_data['user_info']['email'],
			),
			'upsell'		=> array( 'products' => $related_downloads ),
		);

		return apply_filters( 'receiptful_api_args_order_args', $order_args, $payment_id, $items, $subtotals, $related_downloads );

	}


	/**
	 * Create coupon.
	 *
	 * Create a coupon when upsell data returned from Receiptful API.
	 *
	 * @since 1.0.0
	 *
	 * @param	array	$data			List of data returned by the Receiptful API.
	 * @param	int		$payment_id		ID of the payment being processed.
	 */
	public function create_coupon( $data, $payment_id ) {

		$coupon_code	= apply_filters( 'edd_coupon_code', $data['couponCode'] );
		$expiry_date	= date_i18n( 'm/d/Y 23:59:59', strtotime( '+' . sanitize_text_field( $data['expiryPeriod'] ) . ' days' ) );
		$payment_data	= edd_get_payment_meta( $payment_id );

		// Check for duplicates
		if ( edd_discount_exists( $coupon_code ) ) {
			return;
		}

		if ( 'discountcoupon' == $data['upsellType'] ) {

			switch ( $data['couponType'] ) {
				default:
				case 1:
					$discount_type = 'flat';
				break;
				case 2:
					$discount_type = 'percent';
				break;
			}

		} elseif ( 'shippingcoupon' == $data['upsellType'] ) {
			// Shipping doesn't exist in EDD
		}

		$meta = array(
			'code'						=> $coupon_code,
			'uses'						=> '',
			'max_uses'					=> 1,
			'amount'					=> isset( $data['amount'] ) ? $data['amount'] : '',
			'start'						=> date( 'm/d/Y' ),
			'expiration'				=> $expiry_date,
			'type'						=> $discount_type,
			'min_price'					=> '',
			'product_reqs'				=> array(),
			'product_condition'			=> '',
			'excluded_products'			=> array(),
			'is_not_global'				=> false,
			'is_single_use'				=> true,
			'is_receiptful_coupon'		=> 'yes',
			'receiptful_coupon_payment'	=> $payment_id,
			'restrict_customer_email'	=> ! empty( $data['emailLimit'] ) ? array( $payment_data['user_info']['email'] ) : array(),
		);

		$meta = apply_filters( 'edd_insert_discount', $meta );

		do_action( 'edd_pre_insert_discount', $meta );

		$discount_id = wp_insert_post( array(
			'post_type'		=> 'edd_discount',
			'post_title'	=> $coupon_code,
			'post_status'	=> 'active'
		) );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $discount_id, '_edd_discount_' . $key, $value );
		}

		do_action( 'edd_post_insert_discount', $meta, $discount_id );

		// Discount code created
		return $discount_id;

	}


	/**
	 * Download urls.
	 *
	 * Get the download url(s) for the products that are downloadable.
	 *
	 * @since 1.0.0
	 *
	 * @param	int		$download_id	ID of the download to get the URLs for.
	 * @param	int		$payment_id		ID of the purchase to get the URLs for.
	 * @param	array	$item			Item list param as gotton from $order->get_items().
	 * @return	array					List of download URLs based on a key / value structure.
	 */
	public function maybe_get_download_urls( $download_id, $payment_id, $item ) {

		$urls			= array();
		$payment_data	= edd_get_payment_meta( $payment_id );
		$price_id		= edd_get_cart_item_price_id( $item );
		$files			= edd_get_download_files( $download_id, $price_id );

		if ( ! empty( $files ) ) {

			foreach ( $files as $filekey => $file ) {
				$download_url = edd_get_download_file_url( $payment_data['key'], $payment_data['user_info']['email'], $filekey, $download_id );
				$urls[] = array( 'key' => sprintf( __( 'Download %s', 'receiptful' ), $file['name'] ), 'value' => $download_url );
			}

		} elseif ( edd_is_bundled_product( $download_id ) ) {

			$bundled_products = apply_filters( 'edd_email_tag_bundled_products', edd_get_bundled_products( $download_id ), $item, $payment_id, 'download_list' );
			foreach ( $bundled_products as $bundle_item ) {

				$files = edd_get_download_files( $bundle_item );
				foreach ( $files as $filekey => $file ) {
					$download_url = edd_get_download_file_url( $payment_data['key'], $payment_data['user_info']['email'], $filekey, $bundle_item );
					$urls[] = array( 'key' => sprintf( __( 'Download %s', 'receiptful' ), $file['name'] ), 'value' => $download_url );
				}
			}

		}

		$urls = apply_filters( 'receiptful_get_download_urls', $urls, $item, $download_id, $payment_id );

		return ! empty( $urls ) ? $urls : null;

	}


	/**
	 * Resend receipt.
	 *
	 * Link action handler to resend Receiptful receipt.
	 * It will resend the email and redirect after that.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Payment data.
	 */
	public function resend_transactional_email( $data ) {

		$payment_id = absint( $data['purchase_id'] );

		if ( empty( $payment_id ) ) {
			return;
		}

		$this->send_transactional_email( $payment_id );

		// Grab all downloads of the purchase and update their file download limits, if needed
		// This allows admins to resend purchase receipts to grant additional file downloads
		$downloads = edd_get_payment_meta_cart_details( $payment_id, true );

		if ( is_array( $downloads ) ) {
			foreach ( $downloads as $download ) {
				$limit = edd_get_file_download_limit( $download['id'] );
				if ( ! empty( $limit ) ) {
					edd_set_file_download_limit_override( $download['id'], $payment_id );
				}
			}
		}

		wp_redirect( add_query_arg( array( 'edd-message' => 'email_sent', 'edd-action' => false, 'purchase_id' => false ) ) );
		exit;

	}


	/**
	 * Resend Queue.
	 *
	 * Resend receipts in queue. Called from Cron.
	 *
	 * @since 1.0.0
	 */
	public function resend_queue(){

		// Check queue
		$resend_queue = get_option( '_receiptful_resend_queue' );

		if ( is_array( $resend_queue ) && ( count( $resend_queue ) > 0 ) ) {

			foreach ( $resend_queue as $key => $val ) {

				$response = $this->send_transactional_email( $val );

				// Remove fron queue when its successfully send
				if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( '201', '200' ) ) ) {
					unset( $resend_queue[ $key ] );
				}

			}

			update_option( '_receiptful_resend_queue', $resend_queue );

		}

	}


}
