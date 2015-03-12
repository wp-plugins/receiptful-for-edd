<?php
/**
 * Plugin Name:		Receiptful for EDD
 * Plugin URI:		http://receiptful.com
 * Description:		Receiptful replaces and supercharges the default EDD receipts. Just activate, add API and be awesome.
 * Author:			Receiptful
 * Author URI:		http://receiptful.com
 * Version:			1.0.3
 * Text Domain:		receiptful
 * Domain Path:		/languages/
 *
 * @package		Receiptful-EDD
 * @author		Receiptful
 * @copyright	Copyright (c) 2012-2014, Receiptful
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Class Receiptful_EDD.
 *
 * Main class initializes the plugin.
 *
 * @class		Receiptful_EDD
 * @version		1.0.0
 * @author		Receiptful
 */
class Receiptful_EDD {


	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string $version Plugin version number.
	 */
	public $version = '1.0.3';


	/**
	 * Plugin file.
	 *
	 * @since 1.0.0
	 * @var string $file Plugin file path.
	 */
	public $file = __FILE__;


	/**
	 * Instance of Receiptful_EDD.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var object $instance The instance of Receiptful_EDD.
	 */
	protected static $instance;


	/**
	 * Constructor.
	 *
	 * Initialize the class and plugin.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Check if EDD is active
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( ! in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			if ( ! is_plugin_active_for_network( 'easy-digital-downloads/easy-digital-downloads.php' ) ) {
				return false;
			}
		}

		// Initialize plugin parts
		$this->init();

		// Plugin hooks
		$this->hooks();

		// Textdomain
		$this->load_textdomain();

		do_action( 'receiptful_loaded' );

	}


	/**
	 * Instance.
	 *
	 * An global instance of the class. Used to retrieve the instance
	 * to use on other files/plugins/themes.
	 *
	 * @since 1.0.0
	 *
	 * @return Receiptful_EDD Instance of the class.
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;

	}


	/**
	 * Init.
	 *
	 * Initialize plugin parts.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		if ( is_admin() ) {

			/**
			 * Admin settings class
			 */
			require_once plugin_dir_path( __FILE__ ) . '/includes/admin/class-edd-receiptful-admin.php';
			$this->admin = new EDD_Receiptful_Admin();

		}

		/**
		 * Main Receiptful class
		 */
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-receiptful-email.php';
		$this->email = new Receiptful_Email();

		/**
		 * Front-end Receiptful class
		 */
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-receiptful-front-end.php';
		$this->front_end = new Receiptful_Front_End();

		/**
		 * Receiptful API
		 */
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-receiptful-api.php';
		$this->api = new Receiptful_Api();

		/**
		 * Receiptful Products sync
		 */
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-receiptful-products.php';
		$this->products = new Receiptful_Products();

		/**
		 * Helper functions
		 */
		require_once plugin_dir_path( __FILE__ ) . '/includes/edd-helper-functions.php';

		/**
		 * Receiptful CRON events
		 */
		require_once plugin_dir_path( __FILE__ ) . '/includes/edd-cron-functions.php';


		if ( in_array( 'edd-software-licensing/edd-software-licenses.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || is_plugin_active_for_network( 'edd-software-licensing/edd-software-licenses.php' ) ) {

			/**
			 * EDD Software Licensing compatibility
			 */
			require_once plugin_dir_path( __FILE__ ) . '/includes/integrations/class-edd-software-licensing.php';
			$this->edd_sl_integration = new Receiptful_EDD_Software_Licensing_Compatibility();

		}

	}


	/**
	 * Hooks.
	 *
	 * Initial plugin hooks.
	 *
	 * @since 1.0.2
	 */
	public function hooks() {

		// Add the plugin page Settings and Docs links
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'receiptful_plugin_links' ));

		// Plugin activation message
		add_action( 'admin_notices', array( $this, 'plugin_activation' ) ) ;

		// Add tracking script
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Tracking calls
		add_action( 'wp_footer', array( $this, 'print_scripts' ), 99 );

	}


	/**
	 * Textdomain.
	 *
	 * Load the textdomain based on WP language.
	 *
	 * @since 1.0.2
	 */
	public function load_textdomain() {

		$locale = apply_filters( 'plugin_locale', get_locale(), 'receiptful' );

		// Load textdomain
		load_textdomain( 'receiptful', WP_LANG_DIR . '/receiptful-for-edd/receiptful-' . $locale . '.mo' );
		load_plugin_textdomain( 'receiptful', false, basename( dirname( __FILE__ ) ) . '/languages' );

	}


	/**
	 * Enqueue script.
	 *
	 * Enqueue Receiptful tracking script to track click conversions.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {

		// Add tracking script
		wp_enqueue_script( 'receiptful-tracking', 'https://app.receiptful.com/scripts/tracking.js', array(), $this->version, true );

	}


	/**
	 * Print script.
	 *
	 * Print initializing javascript.
	 *
	 * @since 1.0.0
	 */
	public function print_scripts() {

		if ( edd_is_success_page() ) {

			global $edd_receipt_args, $edd_options;

			if ( ! isset( $edd_receipt_args['id'] ) ) :
				return;
			endif;

			$coupon_tracking_code	= '';
			$payment				= get_post( $edd_receipt_args['id'] );
			$payment_data			= edd_get_payment_meta( $payment->ID );
			$amount					= edd_get_payment_amount( $payment->ID );
			$coupon_code			= explode( ', ', $payment_data['user_info']['discount'] );
			$coupon_code			= reset( $coupon_code ); // Grab the first coupon

			// There IS a coupon used..
			if ( 'none' != $coupon_code ) {

				$coupon					= edd_get_discount_by_code( $coupon_code );
				$is_receiptful_coupon	= get_post_meta( $coupon->ID, '_edd_discount_is_receiptful_coupon', true );

				if ( 'yes' == $is_receiptful_coupon ) {
					$coupon_tracking_code = "Receiptful.conversion.couponCode = '$coupon_code';";
				}

			}

			?><script type='text/javascript'>
				Receiptful.conversion.reference	= '<?php echo $payment->ID; ?>';
				Receiptful.conversion.amount	= <?php echo $amount; ?>;
				Receiptful.conversion.currency	= '<?php echo edd_get_currency(); ?>';
				<?php echo $coupon_tracking_code; ?>
				Receiptful.trackConversion();
			</script><?php
		} else {
			?><script type='text/javascript'>Receiptful.setTrackingCookie();</script><?php
		}

	}


	/**
	 * Plugin activation.
	 *
	 * Saves the version of the plugin to the database and displays an
	 * activation notice on where users can access the new options.
	 *
	 * @since 1.0.0
	 */
	public function plugin_activation() {

		$edd_settings = get_option( 'edd_settings' );

		if ( isset( $edd_settings['receiptful_api_key'] ) && empty( $edd_settings['receiptful_api_key'] ) ) {

			?><div class="updated">
				<p><?php
					_e( 'Receiptful has been activated. Please click <a href="edit.php?post_type=download&page=edd-settings&tab=extensions">here</a> to add your API key & supercharge your receipts.', 'receiptful' );
				?></p>
			</div><?php

		}

		// Update version number if its not the same
		if ( $this->version != get_option( 'receiptful_edd_version' ) ) {
			update_option( 'receiptful_edd_version', $this->version );
		}

	}


	/**
	 * Plugin page link.
	 *
	 * Add a 'settings' link to the plugin on the plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param 	array $links	List of existing plugin links.
	 * @return 	array			List of modified plugin links.
	 */
	function receiptful_plugin_links( $links ) {

		$links['settings'] = '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=extensions#receiptful' ) . '">' . __( 'Settings', 'receiptful' ) . '</a>';

		return $links;

	}


}


/**
 * The main function responsible for returning the Receiptful_EDD object.
 *
 * Use this function like you would a global variable, except without needing to declare the global.
 *
 * Example: <?php Receiptful_EDD()->method_name(); ?>
 *
 * @since 1.0.0
 *
 * @return object Receiptful_EDD class object.
 */
if ( ! function_exists( 'Receiptful' ) ) {

	function Receiptful() {
		return Receiptful_EDD::instance();
	}

}

Receiptful();
