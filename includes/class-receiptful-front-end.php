<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Receiptful_Front_End.
 *
 * Front-end Class.
 *
 * @class		Receiptful_Front_End
 * @version		1.0.0
 * @author		Receiptful
 */
class Receiptful_Front_End {


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

		// Purchase history row header
		add_action( 'edd_purchase_history_header_after', array( $this, 'purchase_history_receipt_header' ) );

		// Purchase history row content
		add_action( 'edd_purchase_history_row_end', array( $this, 'purchase_history_receipt_content' ), 10, 2 );

		// Check if there is a email resctriction
		add_filter( 'edd_is_discount_valid', array( $this, 'coupon_restrict_email_validation' ), 10, 4 );

	}


	/**
	 * Receipt table header.
	 *
	 * Add the Receipt header to the purchase history table.
	 *
	 * @since 1.0.0
	 */
	public function purchase_history_receipt_header() {

		?><th class='edd_purchase_receipt'><?php _e( 'Receipt', 'receiptful' ); ?></th><?php

	}


	/**
	 * Receipt table content.
	 *
	 * Add the Receipt 'View receipt online' link to the table.
	 *
	 * @since 1.0.0
	 */
	public function purchase_history_receipt_content( $payment_id, $purchase_data ) {

		$receiptful_web_link = get_post_meta( $payment_id, '_receiptful_web_link', true );
		?><td class='edd_purchase_receipt'><?php
			if ( $receiptful_web_link ) {
				?><a href='<?php echo $receiptful_web_link['webview']; ?>'><?php _e( 'View receipt online', 'receiptful' ); ?></a><?php
			} else {
				_e( 'Sorry, no online receipt available', 'receiptful' );
			}
		?></td><?php

	}


	/**
	 * Check email restriction.
	 *
	 * Check if there is an email restriction active, if so check if the current
	 * user is valid. Email can match again account email or order given email.
	 *
	 * @since 1.0.0
	 *
	 * @param	bool	$return			Default return false.
	 * @param	int		$discount_id	ID of the coupon code being used.
	 * @param	string	$code			Coupon code being used.
	 * @param	string	$user			User email address (not working?).
	 * @return	bool					Returns whether coupon usage is valid.
	 */
	public function coupon_restrict_email_validation( $return, $discount_id, $code, $user ) {

		$restriction_emails = get_post_meta( $discount_id, '_edd_discount_restrict_customer_email', true );
		$user_email			= array();

		// Check for the user account mail, and the given mail in the order.
		if ( isset( $_POST['form'] ) ) {
			parse_str( $_POST['form'], $form );
			$user_email[] = isset( $form['edd_email'] ) ? $form['edd_email'] : '';
		} else if ( isset( $_POST['edd_email'] ) && ! empty( $_POST['edd_email'] ) ) {
			$user_email[] = sanitize_text_field( $_POST['edd_email'] );
		}
		if ( is_user_logged_in() ) {
			$user_email[] = wp_get_current_user()->user_email;
		}

		if ( is_array( $restriction_emails ) && ! empty( $restriction_emails ) ) {
			$email_matches = array_intersect( $user_email, $restriction_emails );
			if ( empty( $email_matches ) || ! is_array( $email_matches ) ) {
				edd_set_error( 'edd-discount-error', __( 'This discount is only valid for certain emails. Please use the correct email.', 'Receiptful' ) );
				return false;
			}
		}

		return $return;

	}


}
