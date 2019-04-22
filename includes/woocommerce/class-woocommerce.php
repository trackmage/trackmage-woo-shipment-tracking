<?php
/**
 * This is responsible for Woocommerce work stuff
 *
 * @since      1.0.0
 */

namespace TrackMage;

class Woocommerce {

	protected static $instance = null;

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}



	/**
	 * @since 1.0.0
	 */
	public function __construct(){

		add_action('init', __CLASS__ . '::run_on_init');

	}

	/**
	 * Runs after WordPress has finished loading but before any headers are sent
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function run_on_init(){

		if (class_exists('\WooCommerce')){

			// include_once PLUGIN_DIR . '/includes/woocommerce/class-settings.php';

		} else {

			Utility::show_notice(sprintf(
				__('%s plugin requires %sWooCommerce%s plugin to be installed and active!'),
				'<b>'.PLUGIN_NAME.'</b>',
				'<a href="https://woocommerce.com" target="_blank">',
				'</a>'
			), 'error');

		}
	}

}
Woocommerce::instance();