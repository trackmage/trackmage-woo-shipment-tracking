<?php
/**
 * Load admin assets
 *
 * @class   Assets
 * @package TrackMage\WordPress\Admin
 * @Author  TrackMage
 */

namespace TrackMage\WordPress\Admin;

use TrackMage\WordPress\Utils;

defined( 'WPINC' ) || exit;

/**
 * TrackMage\WordPress\Admin\Assets class.
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
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
	}

	/**
	 * Init the TrackMage\WordPress\Admin\Assets class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::get_instance();
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @since 1.0.0
	 */
	public function admin_styles() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register admin styles.
		wp_register_style( 'trackmage_admin_styles', TRACKMAGE_URL . 'assets/dist/css/admin/main' . $suffix . '.css', [], TRACKMAGE_VERSION, 'all' );

		// Enqueue WooCommerce styles.
		wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		
		// Enqueue admin styles.
		wp_enqueue_style( 'trackmage_admin_styles' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 */
	public function admin_scripts() {
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Register admin scripts.
		wp_register_script( 'trackmage_admin_scripts', TRACKMAGE_URL . 'assets/dist/js/admin/scripts' . $suffix . '.js', [ 'jquery', 'jquery-effects-highlight', 'wc-enhanced-select', 'selectWoo' ], TRACKMAGE_VERSION, true );

		// Enqueue admin scripts.
		wp_enqueue_script( 'selectWoo' );
		wp_enqueue_script( 'trackmage_admin_scripts' );
		wp_localize_script( 'trackmage_admin_scripts', 'trackmage_admin_params', [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'add_tracking_number_nonce' => wp_create_nonce( 'add-tracking-number' ),
			'images'      => [
				'iconTrackMage' => TRACKMAGE_URL . 'assets/dist/images/trackmage-icon.svg',
			],
			'aliases'     => Utils::get_aliases(),
			'messages'    => [
				'testCredentials'          => __( 'Test Credentials', 'trackmage' ),
				'successValidKeys'         => __( 'Valid credentials. Click on <em>“Save Changes”</em> for the changes to take effect.', 'trackmage' ),
				'unknownError'             => __( 'Unknown error occured.', 'trackmage' ),
				'noSelect'                 => __( '— Select —', 'trackmage' ),
				'edit'                     => __( 'Edit', 'trackmage' ),
				'delete'                   => __( 'Delete', 'trackmage' ),
				'name'                     => __( 'Name', 'trackmage' ),
				'slug'                     => __( 'Slug', 'trackmage'),
				'alias'                    => __( 'Alias', 'trackmage'),
				'cancel'                   => __( 'Cancel', 'trackmage' ),
				'update'                   => __( 'Update', 'trackmage'),
				'updateStatus'             => __( 'Update Status', 'trackmage' ),
				'successUpdateStatus'      => __( 'Status has been updated successfully.', 'trackmage' ),
				'addStatus'                => __( 'Add Status', 'trackmage' ),
				'successAddStatus'         => __( 'Status has been added successfully.', 'trackmage' ),
				'deleteStatus'             => __( 'Delete Status', 'trackmage' ),
				'successDeleteStatus'      => __( 'Status has been deleted successfully.', 'trackmage' ),
				'addTrackingNumber'        => __( 'Add Tracking Number', 'trackmage' ),
				'successAddTrackingNumber' => __( 'Tracking number added successfully.', 'trackmage' ),
			]
		] );
	}
}
