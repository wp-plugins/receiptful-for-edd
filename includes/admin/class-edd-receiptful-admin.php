<?PHP
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class EDD_Receiptful_Admin.
 *
 * Admin class.
 *
 * @class		EDD_Receiptful_Admin
 * @version		1.0.0
 * @author		Receiptful
 */
class EDD_Receiptful_Admin {


	/**
	 * URL for the store owner's Profile page in the Receiptful app.
	 * @var string
	 */
	public $receiptful_profile_url = 'https://app.receiptful.com/profile';


	/**
	 * URL for the store owner's Template in the Receiptful app.
	 * @var string
	 */
	public $receiptful_template_url = 'https://app.receiptful.com/template';


	/**
	 * URL for the store owner's Dashboard in the Receiptful app.
	 * @var string
	 */
	public $receiptful_stats_url = 'https://app.receiptful.com/dashboard';


	/**
	 * URL for the store owner's Dashboard in the Receiptful app.
	 * @var string
	 */
	public $receiptful_recommendations_url = 'https://app.receiptful.com/recommendations';


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

		// Remove settings from email tab
		add_filter( 'edd_settings_emails', array( $this, 'remove_email_tab_settings' ) );

		// Add a 'Receiptful is active' notice
		add_action( 'edd_receiptful_active_notice', array( $this, 'receiptful_active_notice_callback' ) );

		// Add settings to 'Extensions' tab
		add_filter( 'edd_settings_extensions', array( $this, 'register_settings' ) );

		// Description setting field
		add_action( 'edd_receiptful_description', array( $this, 'receiptful_description_callback' ) );

		// Email restriction field
		add_action( 'edd_edit_discount_form_bottom', array( $this, 'edit_coupon_add_email_restriction_field' ), 10, 2 );

		// Save restriction field
		add_action( 'edd_edit_discount', array( $this, 'edit_coupon_save_email_restriction_field' ), 9, 1 );

		// Remove public key when API key gets changed (will be gotten automatically)
		add_action( 'update_option_edd_settings', array( $this, 'delete_public_key' ), 10, 2 );

	}


	/**
	 * Remove settings.
	 *
	 * Remove the default EDD email settings for the email notification
	 * as these are no longer used.
	 *
	 * @since 1.0.0
	 *
	 * @param	array $settings List of email settings.
	 * @return	array			List of modified email settings.
	 */
	public function remove_email_tab_settings( $settings ) {

		unset( $settings['email_template'] );
		unset( $settings['email_logo'] );
		unset( $settings['email_settings'] );
		unset( $settings['from_name'] );
		unset( $settings['from_email'] );
		unset( $settings['purchase_subject'] );
		unset( $settings['purchase_receipt'] );

		$new_settings['receiptful_active_notice'] = array(
			'id'	=> 'receiptful_active_notice',
			'type'	=> 'hook',
		);
		$settings = array_merge( $new_settings, $settings );

		return $settings;

	}


	/**
	 * Receiptful active descrition.
	 *
	 * Display a notice/description on the position where the old receipt
	 * settings were displayed.
	 *
	 * @since 1.0.4
	 */
	public function receiptful_active_notice_callback() {

		echo '<strong>' . __( 'Default receipt email settings are unavailable. You\'re sending awesome emails through Receiptful.', 'receiptful' ) . '</strong>';

	}


	/**
	 * Settings page array.
	 *
	 * Set settings page fields array.
	 *
	 * @since 1.0.0
	 *
	 * @param	array $existing_settings	List of existing settings.
	 * @return	array						List of modified settings.
	 */
	public function register_settings( $existing_settings ) {

		$settings = array(
			array(
				'id'	=> 'receiptful_settings',
				'name'	=> '<h3 id="receiptful" class="title">Receiptful</h3>',
				'desc'	=> '',
				'type'	=> 'header'
			),
			array(
				'id'	=> 'receiptful_api_key',
				'name'	=> __( 'API Key', 'receiptful' ),
				'desc'	=> sprintf( __( 'Add your API key (<a href="%s" target="_blank">which you can find here</a>) to get started.', 'receiptful' ), esc_attr( $this->receiptful_profile_url ) ),
				'type'	=> 'text',
			),
			array(
				'id'	=> 'receiptful_description',
				'type'	=> 'hook',
			),
			array(
				'id'	=> 'receiptful_enable_recommendations',
				'name'	=> __( 'Enable recommendations', 'receiptful' ),
				'desc'	=> sprintf( __( "Enable product recommendations. Requires to have set this up in the <a href='%s' target='_blank'>Recommendations section</a>.", 'receiptful' ), esc_attr( $this->receiptful_recommendations_url ) ),
				'type'	=> 'checkbox',
			),
		);

		// Merge with existing plugin settings
		return array_merge( $existing_settings, $settings );

	}


	/**
	 * Receiptful description.
	 *
	 * Receiptful description callback.
	 *
	 * @since 1.0.0
	 */
	public function receiptful_description_callback() {

		$template_url 	= esc_attr( $this->receiptful_template_url );
		$stats_url		= esc_attr( $this->receiptful_stats_url );
		echo sprintf( __( '<a href="%s" target="_blank">Edit My Template</a> | <a href="%s" target="_blank">View Statistics</a>', 'receiptful' ), $template_url, $stats_url );

	}


	/**
	 * Email restriction field.
	 *
	 * Add the email restriction field to the coupon edit page.
	 *
	 * @since 1.0.0
	 *
	 * @param int		$discount_id	ID of the coupon being edited.
	 * @param WP_Post	$discount		Post object of the discount coupon.
	 */
	public function edit_coupon_add_email_restriction_field( $discount_id, $discount ) {

		$restriction_emails = get_post_meta( $discount_id, '_edd_discount_restrict_customer_email', true );
		$emails = is_array( $restriction_emails ) ? implode( ', ', $restriction_emails ) : '';

		?><table class='form-table'>
			<tbody>
				<tr>
					<th scope='row' valign='top'>
						<label for='edd-restrict-email'><?php _e( 'Email restrictions', 'receiptful' ); ?></label>
					</th>
					<td>
						<input type='text' id='edd-restrict-email' name='email_restrict' value='<?php echo esc_attr( $emails ); ?>' style='width: 400px;'/>
						<p class='description edd-email-restriction-description'><?php
							_e( 'List of emails to restrict the coupon usage with. Separate by comma (,)', 'receiptful' );
						?></p>
					</td>
				</tr>
			</tbody>
		</table><?php

	}


	/**
	 * Save restriction field.
	 *
	 * Save the restriction field at save action.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data List of POST data.
	 */
	public function edit_coupon_save_email_restriction_field( $data ) {

		if ( ! isset( $data['edd-discount-nonce'] ) || ! wp_verify_nonce( $data['edd-discount-nonce'], 'edd_discount_nonce' ) ) {
			return;
		}

		$emails = array();
		if ( ! empty( $data['email_restrict'] ) ) {
			$emails = explode( ',', str_replace( ', ', ',', $data['email_restrict'] ) );
		}

		update_post_meta( $data['discount-id'], '_edd_discount_restrict_customer_email', $emails );

	}


	/**
	 * Delete public key.
	 *
	 * Delete the public key when the API key gets updated.
	 *
	 * @since 1.0.6
	 */
	public function delete_public_key( $old_value, $value ) {

		if ( isset( $old_value['receiptful_api_key'] ) && isset( $value['receiptful_api_key'] ) && $old_value['receiptful_api_key'] !== $value['receiptful_api_key'] ) :
			delete_option( 'receiptful_public_user_key' );
		endif;

	}


}
