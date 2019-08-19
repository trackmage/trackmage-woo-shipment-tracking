<?php
/**
 * Load public assets
 *
 * @class   Assets
 * @package TrackMage\WordPress
 * @Author  TrackMage
 */

namespace TrackMage\WordPress;

defined( 'WPINC' ) || exit;

/**
 * TrackMage\WordPress\Assets class.
 *
 * @since 1.0.0
 */
class Assets {

	private static $instance = null;

	private function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
	}

	/**
	 * Init the TrackMage\WordPress\Assets class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::get_instance();
	}

	/**
	 * Enqueue public styles.
	 *
	 * @since 1.0.0
	 */
	public function styles() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register styles.
		wp_register_style( 'trackmage_styles', TRACKMAGE_URL . 'assets/dist/css/main' . $suffix . '.css', [], TRACKMAGE_VERSION, 'all' );

		// Enqueue styles.
		wp_enqueue_style( 'trackmage_styles' );

	}

	/**
	 * Enqueue public scripts.
	 *
	 * @since 1.0.0
	 */
	public function scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register public scripts.
		wp_register_script( 'trackmage_scripts', TRACKMAGE_URL . 'assets/dist/js/scripts' . $suffix . '.js', [ 'jquery' ], TRACKMAGE_VERSION, true );
		
		// Enqueue public scripts.
		wp_enqueue_script( 'trackmage_scripts' );
		wp_localize_script( 'trackmage_scripts', 'trackmage_params', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		] );
	}
}
