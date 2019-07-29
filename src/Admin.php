<?php
/**
 * Admin area
 *
 * Initialize and render the settings page.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin class.
 *
 * @since 0.1.0
 */
class Admin {

	/**
	 * The constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_init', [ $this, 'settings' ] );
		add_action( 'wp_ajax_trackmage_test_credentials', [ $this, 'test_credentials' ] );
		add_action( 'wp_ajax_trackmage_toggle_webhook', [ $this, 'toggle_webhook' ] );
	}

	/**
	 * Registers setting pages.
	 *
	 * @since 0.1.0
	 */
	public function add_page() {
		add_menu_page(
			__( 'TrackMage', 'trackmage' ),
			__( 'TrackMage', 'trackmage' ),
			'manage_options',
			'trackmage',
			[ $this, 'render' ],
			TRACKMAGE_URL . 'assets/dist/images/trackmage-icon-white.svg',
			30
		);

		add_submenu_page(
			'trackmage',
			__( 'Settings', 'trackmage' ),
			__( 'Settings', 'trackmage' ),
			'manage_options',
			'trackmage',
			[ $this, 'render' ]
		);
	}

	/**
	 * Registers the general setting fields.
	 *
	 * @since 0.1.0
	 */
	public function settings() {
		// General settings.
		register_setting( 'trackmage_general', 'trackmage_client_id' );
		register_setting( 'trackmage_general', 'trackmage_client_secret' );
		register_setting( 'trackmage_general', 'trackmage_workspace' );
		register_setting( 'trackmage_general', 'trackmage_webhook' );
		register_setting( 'trackmage_general', 'trackmage_webhook_id' );
	}

	/**
	 * Renders setting pages.
	 *
	 * @since 0.1.0
	 */
	public function render() {
		require_once TRACKMAGE_DIR . '/templates/admin/settings.php';
	}

	/**
	 * Tests API keys.
	 *
	 * @since 0.1.0
	 */
	public function test_credentials() {
		$credentials = Utils::check_credentials( $_POST['clientId'], $_POST['clientSecret'] );

		if ( $credentials ) {
			// Send response, and die.
			wp_send_json_success( [
				'status' => 'success',
			] );
		}

		// Send response, and die.
		wp_send_json_error( [
			'status' => 'error',
		] );
	}

	/**
	 * Add/remove webhook.
	 *
	 * @since 0.1.0
	 */
	public function toggle_webhook() {
		$toggle = $_POST['toggle'];
		$workspace = $_POST['workspace'];
		$client = Plugin::get_client();

		if ( 'enable' === $toggle ) {
			$url = $_POST['url'];

			// Generate random username and password.
			$username = wp_get_current_user()->user_login . '_' . substr( md5( time() . rand( 0, 1970 ) ), 0, 5 );
			$password = md5( $username . rand( 1, 337 ) );
			update_option( 'trackmage_webhook_username', $username );
			update_option( 'trackmage_webhook_password', $password );

			$integration = [
				'type' => 'webhook',
				'credentials' => [
					'url' => $url,
					'authType' => 'basic',
					'username' => $username,
					'password' => $password,
				],
			];

			$workflow = [
				'direction' => 'out',
				'period' => 'immediately',
				'title' => get_bloginfo( 'name' ),
				'workspace' => '/workspaces/' . $workspace,
				'integration' => $integration,
				'enabled' => true,
			];

			$workflow = new \TrackMage\Client\Swagger\Model\WorkflowSetWorkflowSetIntegration( $workflow );
	
			try {
				$result = $client->getWorkflowApi()->postWorkflowCollection($workflow);
			} catch (Exception $e) {
				update_option( 'trackmage_webhook', '' );

				// Send response, and die.
				wp_send_json_error( [
					'status' => 'error',
					'toggle' => 'disable',
					'errors' => [ $e->getMessage() ],
				] );
			}

			update_option( 'trackmage_webhook', 'on' );
			update_option( 'trackmage_webhook_id', $result->getId() );
			update_option( 'trackmage_workspace', $workspace );

			// Send response, and die.
			wp_send_json_success( [
				'status' => 'success',
				'toggle' => 'enable',
				'webhookId' => $result->getId(),
			] );
		} else {
			$workflow_id = $_POST['id'];
			try {
				$client->getWorkflowApi()->deleteWorkflowItem( $workflow_id );
			} catch (Exception $e) {
				update_option( 'trackmage_webhook', 'on' );

				// Send response, and die.
				wp_send_json_success( [
					'status' => 'error',
					'toggle' => 'enable',
					'errors' => [ $e->getMessage() ],
				] );
			}

			update_option( 'trackmage_webhook', '' );

			// Send response, and die.
			wp_send_json_success( [
				'status' => 'success',
				'toggle' => 'disable',
			] );
		}
	}
}