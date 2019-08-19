<?php
/**
 * Main class
 *
 * The main class of the plugin.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

use TrackMage\WordPress\Admin\Admin;
use TrackMage\WordPress\Admin\Orders;
use BrightNucleus\Config\ConfigInterface;
use BrightNucleus\Config\ConfigTrait;
use BrightNucleus\Config\Exception\FailedToProcessConfigException;
use BrightNucleus\Settings\Settings;
use TrackMage\Client\TrackMageClient;
use TrackMage\Client\Swagger\ApiException;

/**
 * Main plugin class.
 *
 * @since   0.1.0
 */
class Plugin {

	use ConfigTrait;

	/**
	 * Static instance of the plugin.
	 *
	 * @since 0.1.0
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * The singleton instance of TrackMageClient.
	 *
	 * @since 0.1.0
	 * @var TrackMageClient
	 */
	protected static $client = null;

	/**
	 * Returns the singleton instance of TrackMageClient.
	 *
	 * Ensures only one instance of TrackMageClient is/can be loaded.
	 *
	 * @since 0.1.0
	 * @return TrackMageClient
	 */
	public static function get_client($config = []) {
		if ( null === self::$client ) {
			self::$client = new TrackMageClient();

			try {
				$client_id = isset( $config['client_id'] ) ? $config['client_id'] : get_option( 'trackmage_client_id', '' );
				$client_secret = isset( $config['client_secret'] ) ? $config['client_secret'] : get_option( 'trackmage_client_secret', '' );

				self::$client = new TrackMageClient( $client_id, $client_secret );
				self::$client->setHost( 'https://api.stage.trackmage.com' );
			} catch( ApiException $e ) {
				return null;
			}
		}

		return self::$client;
	}

	/**
	 * Instantiate a Plugin object.
	 *
	 * Don't call the constructor directly, use the `Plugin::get_instance()`
	 * static method instead.
	 *
	 * @since 0.1.0
	 *
	 * @throws FailedToProcessConfigException If the Config could not be parsed correctly.
	 * @param ConfigInterface $config Config to parametrize the object.
	 */
	public function __construct( ConfigInterface $config ) {
		$this->processConfig( $config );
	}

	/**
	 * Launch the initialization process.
	 *
	 * @since 0.1.0
	 */
	public function run() {
		// Hooks.
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'styles' ] );

		// Initialize classes.
		new Endpoint;
		new Templates;
		new Admin;
		new Orders;
		Ajax::init();
	}

	/**
	 * Loads plugin scripts.
	 *
	 * @since 1.0.0
	 */
	public function scripts() {
		// Back-end scripts.
		if ( 'admin_enqueue_scripts' === current_action() ) {
			// Scripts from WooCommerce core.
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_script( 'wc-enhanced-select' );

			wp_enqueue_script( 'jquery-effects-highlight' );
			wp_enqueue_script( 'trackmage-admin-scripts', TRACKMAGE_URL . 'assets/dist/js/admin/scripts.min.js', [ 'jquery', 'jquery-effects-highlight', 'wc-enhanced-select' ], null, true );
			wp_localize_script( 'trackmage-admin-scripts', 'trackmageAdminParams', [
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
		// Front-end scripts.
		else {
			wp_enqueue_script( 'trackmage-scripts', TRACKMAGE_URL . 'assets/dist/js/scripts.min.js', [ 'jquery' ], null, false );
			wp_localize_script( 'trackmage-scripts', 'trackmageParams', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			] );
		}
	}

	/**
	 * Loads plugin styles.
	 *
	 * @since 1.0.0
	 */
	public function styles() {
		// Back-end styles.
		if ( 'admin_enqueue_scripts' === current_action() ) {
			// Styles from WooCommerce core.
			wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
			wp_enqueue_style( 'woocommerce_admin_styles' );

			wp_enqueue_style( 'trackmage-admin-styles', TRACKMAGE_URL . 'assets/dist/css/admin/main.min.css', [], false, 'all' );
		}
		// Front-end styles.
		else {
			wp_enqueue_style( 'trackmage-styles', TRACKMAGE_URL . 'assets/dist/css/main.min.css', [], false, 'all' );
		}
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @since 0.1.0
	 */
	public function load_textdomain() {
	}
}
