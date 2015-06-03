<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Receiptful_Products.
 *
 * Class to sync products with the Receiptful API.
 * Products are synchronised with Receiptful to give customers
 * really good 'similar products' recommendations.
 *
 * @class		Receiptful_Products
 * @version		1.0.0
 * @author		Receiptful
 * @since		1.0.2
 */
class Receiptful_Products {


	/**
	 * Constructor.
	 *
	 * @since 1.0.2
	 */
	public function __construct() {

		// Create/Update product
		add_action( 'publish_post', array( $this, 'update_product' ), 20 );
		add_action( 'save_post', array( $this, 'update_product' ), 20 );

		// Trash product
		add_action( 'trash_download', array( $this, 'delete_product' ), 10, 2 );

	}


	/**
	 * Update product.
	 *
	 * Update a product when its being saved. When updated
	 * the data will be send to Receiptful to keep the data synced.
	 *
	 * @since 1.0.2
	 *
	 * @param 	int 			$post_id 	ID of the post currently being saved.
	 * @return	array|WP_Error				Returns the API response, or WP_Error when API call fails.
	 */
	public function update_product( $post_id ) {

		// Bail if its not a download / if its trashed
		if ( 'download' !== get_post_type( $post_id ) || 'trash' == get_post_status( $post_id ) ) {
			return;
		}

		$args 		= $this->get_formatted_product( $post_id );
		$response 	= Receiptful()->api->update_product( $post_id, $args );

		if ( is_wp_error( $response ) || in_array( $response['response']['code'], array( '401', '500', '503' ) ) ) {
			$queue 							= get_option( '_receiptful_queue', array() );
			$queue['products'][ $post_id ] 	= array( 'id' => $post_id, 'action' => 'update' );
			update_option( '_receiptful_queue', $queue );
		} elseif ( in_array( $response['response']['code'], array( '200' ) ) ) {
			update_post_meta( $post_id, '_receiptful_last_update', time() );
		}

		return $response;

	}


	/**
	 * Update products.
	 *
	 * Update multiple products at once. A product update
	 * will send data to Receiptful to keep in sync.
	 *
	 * @since 1.0.2
	 *
	 * @param	array			$download_ids	Array of download IDs to update.
	 * @return	array|WP_Error					Returns the API response, or WP_Error when API call fails.
	 */
	public function update_products( $download_ids = array() ) {

		// Prepare product args
		$args = array();
		foreach ( $download_ids as $download_id ) {
			$args[] = $this->get_formatted_product( $download_id );
		}

		// Update products
		$response = Receiptful()->api->update_products( array_values( array_filter( $args ) ) );

		// Process response
		if ( is_wp_error( $response ) || in_array( $response['response']['code'], array( '401', '500', '503' ) ) ) {

			$queue = get_option( '_receiptful_queue', array() );
			foreach ( $download_ids as $download_id ) {
				$queue['products'][ $download_id ] = array( 'id' => $download_id, 'action' => 'update' );
			}
			update_option( '_receiptful_queue', $queue );

		} elseif ( in_array( $response['response']['code'], array( '200', '202' ) ) ) {

			$failed_ids = array();
			$body 		= json_decode( $response['body'], 1 );
			foreach ( $body['errors'] as $error ) {
				$failed_ids[] = isset( $error['error']['product_id'] ) ? $error['error']['product_id'] : null;
			}

			foreach ( $download_ids as $download_id ) {
				if ( ! in_array( $download_id, $failed_ids ) ) {
					update_post_meta( $download_id, '_receiptful_last_update', time() );
				}
			}

		}

		return $response;

	}


	/**
	 * Formtted product.
	 *
	 * Get the formatted product arguments for the Receiptful API
	 * to update the product.
	 *
	 * @since 1.0.2
	 *
	 * @param	int		ID of the product to update.
	 * @return	array	Formatted array according Receiptful standards with product data.
	 */
	public function get_formatted_product( $download_id ) {

		// Bail if the ID is not a dowload ID
		if ( 'download' != get_post_type( $download_id ) ) {
			return;
		}

		$download 	= new EDD_Download( $download_id );
		$images 	= $this->get_formatted_images( $download->ID );
		$categories	= $this->get_formatted_categories( $download->ID );
		$tags		= wp_get_post_terms( $download->ID, 'download_tag', array( 'fields' => 'names' ) );
		$variants	= $this->get_formatted_variants( $download->ID );

		if ( 'publish' != $download->post_status ) :
			$hidden = true;
		elseif ( ! empty( $download->post_password ) ) :
			$hidden = true;
		else :
			$hidden = false;
		endif;

		$args = apply_filters( 'receiptful_update_product_args', array(
			'product_id'	=> (string) $download->ID,
			'title'			=> $download->post_title,
			'description'	=> strip_shortcodes( $download->post_content ),
			'hidden'		=> $hidden,
			'url'			=> get_permalink( $download->ID ),
			'images'		=> $images,
			'tags'			=> $tags,
			'categories'	=> $categories,
			'variants'		=> $variants,
		) );

		return $args;

	}


	/**
	 * Formatted categories.
	 *
	 * Get the formatted categories array. The return values
	 * will be according the Receiptful API endpoint specs.
	 *
	 * @since 1.0.2
	 *
	 * @param 	int 	$download_id	ID of the product currently processing.
	 * @return 	array					List of product categories formatted according Receiptful specs.
	 */
	public function get_formatted_categories( $download_id ) {

		$categories 	= array();
		$download_cats	= wp_get_post_terms( $download_id, 'download_category' );

		if ( $download_cats ) {
			foreach ( $download_cats as $category ) {
				$categories[] = array(
					'category_id'	=> (string) $category->term_id,
					'title'			=> $category->name,
					'description'	=> $category->description,
					'url'			=> get_term_link( $category->term_id, 'download_category' ),
				);
			}
		}

		return $categories;

	}


	/**
	 * Formatted images.
	 *
	 * Get the formatted images array. The return value
	 * will be according the Receiptful API endpoint specs.
	 *
	 * This method gets the featured image + all the gallery images.
	 *
	 * @since 1.0.2
	 *
	 * @param 	int 	$download_id 	ID of the product currently processing.
	 * @return 	array					List of product images formatted according Receiptful specs.
	 */
	public function get_formatted_images( $download_id ) {

		$images 		= array();
		$download 		= new EDD_Download( $download_id );
		$featured_id	= get_post_thumbnail_id( $download->ID );

		// Featured image
		if ( ! empty( $featured_id ) && 0 !== $featured_id && wp_get_attachment_url( $featured_id ) ) {
			$images[] = array(
				'position' 	=> count( $images ),
				'url'		=> wp_get_attachment_url( $featured_id ),
			);
		}

		return $images;

	}


	/**
	 * Formatted variants.
	 *
	 * Get the formatted variants array. Variants in Receiptful
	 * are the prices.
	 *
	 * @since 1.0.2
	 *
	 * @param 	int 	$download_id 	ID of the product currently processing.
	 * @return 	array					List of product prices formatted according Receiptful specs.
	 */
	public function get_formatted_variants( $download_id )  {

		$variants 			= array();
		$download 			= new EDD_Download( $download_id );
		$variable_pricing 	= get_post_meta( $download->ID, '_variable_pricing', true );

		if ( ! $variable_pricing ) {

			if ( null != $download->get_price() ) {
				$variants[] = array(
					'price'	=> (float) number_format( (float) $download->get_price(), 2, '.', '' ),
				);
			}

		} else {

			if ( null != $download->get_prices() ) {
				foreach ( $download->get_prices() as $key => $price_data ) {
					$variants[] = array(
						'price'	=> (float) number_format( (float) $price_data['amount'], 2, '.', '' ),
					);
				}
			}

		}

		return $variants;

	}


	/**
	 * Delete product.
	 *
	 * Delete the product from Receiptful when its deleted in the shop.
	 *
	 * @since 1.0.2
	 *
	 * @param 	int 			$post_id 	ID of the post (product) currently being deleted.
	 * @param 	WP_Post			$post 		WP_Post object containing post data.
	 * @return	array|WP_Error				Returns the API response, or WP_Error when API call fails.
	 */
	public function delete_product( $post_id, $post = '' ) {

		// Bail if its not a product
		if ( 'download' !== get_post_type( $post_id ) ) {
			return;
		}

		$response = Receiptful()->api->delete_product( $post_id );

		if ( is_wp_error( $response ) || in_array( $response['response']['code'], array( '401', '500', '503' ) ) ) {
			$queue 							= get_option( '_receiptful_queue', array() );
			$queue['products'][ $post_id ] 	= array( 'id' => $post_id, 'action' => 'delete' );
			update_option( '_receiptful_queue', $queue );
		}

		return $response;

	}


	/**
	 * Processes product queue.
	 *
	 * Process the producs that are in the queue.
	 *
	 * @since 1.0.2
	 */
	public function process_queue() {

		$queue = get_option( '_receiptful_queue', array() );

		if ( isset( $queue['products'] ) && is_array( $queue['products'] ) ) {
			foreach ( $queue['products'] as $key => $product ) {

				if ( 'delete' == $product['action'] ) {
					$response = $this->delete_product( $product['id'] );
				} else {
					$response = $this->update_product( $product['id'] );
				}

				if ( ! is_wp_error( $response ) && in_array( $response['response']['code'], array( '200', '204', '400', '404' ) ) ) { // Unset from queue when appropiate
					unset( $queue['products'][ $key ] );
				}

			}
		}

		update_option( '_receiptful_queue', $queue );

	}


}
