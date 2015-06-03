<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Receiptful_Recommendations.
 *
 * Class to manage recommendation related business.
 *
 * @class		Receiptful_Recommendations
 * @version		1.0.7
 * @author		Receiptful
 * @since		1.0.7
 */
class Receiptful_Recommendations {


	/**
	 * Constructor.
	 *
	 * @since 1.0.7
	 */
	public function __construct() {

		// Recommendation shortcode
		add_shortcode( 'rf_recommendations', array( $this, 'recommendation_shortcode' ) );

	}


	/**
	 * Get recommendations.
	 *
	 * Get the recommendations HTML.
	 *
	 * @since 1.0.7
	 */
	public function get_recommendations() {

		return '<div class="rf-recommendations"></div>';

	}


	/**
	 * Display recommendations.
	 *
	 * Display the recommendations HTML.
	 *
	 * @since 1.0.7
	 */
	public function display_recommendations() {

		echo $this->get_recommendations();

	}

	/**
	 * Recommendation shortocde.
	 *
	 * Shortcode to simply display a div with a class where the
	 * recommendations will be loaded in via JS.
	 *
	 * @since 1.0.7
	 */
	public function recommendation_shortcode() {

		return $this->get_recommendations();

	}


}
