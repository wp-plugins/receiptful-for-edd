<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * EDD Software Licensing compatibility.
 *
 * @author		Receiptful
 * @version		1.0.0
 * @since		1.0.0
 */
class Receiptful_EDD_Software_Licensing_Compatibility {


	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_filter( 'receiptful_api_args_items', array( $this, 'add_license_meta' ), 10, 2 );

	}


	/**
	 * License meta.
	 *
	 * Add item license meta to the ordered products.
	 *
	 * @since 1.0.0
	 */
	public function add_license_meta( $items, $payment_id ) {

		$licensing 		= edd_software_licensing();
		$payment_data	= edd_get_payment_meta( $payment_id );

		if ( $payment_data['cart_details'] && $licensing ) {
			foreach ( $payment_data['cart_details'] as $key => $download ) {

				// Bundled product
				if ( edd_is_bundled_product( $download['id'] ) ) {
					$bundled_products = edd_get_bundled_products( $download['id'] );

					if ( $bundled_products ) {
						foreach ( $bundled_products as $bundle_item_id ) {

							$license = $licensing->get_license_by_purchase( $payment_id, $bundle_item_id, $key );

							if ( ! $license ) {
								continue;
							}

							$license_key 	= $licensing->get_license_key( $license->ID );
							$items[ $key ]['metas'][] = array(
								'key' 	=> 'License key',
								'value'	=> $license_key,
							);
						}
					}
				}

				$license = $licensing->get_license_by_purchase( $payment_id, $download['id'], $key );

				if ( ! $license ) {
					continue;
				}

				$license_key = $licensing->get_license_key( $license->ID );
				$items[ $key ]['metas'][] = array(
					'key' 	=> 'License key',
					'value'	=> $license_key,
				);



			}
		}

		return $items;

	}

}
