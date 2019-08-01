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

use TrackMage\Client\Swagger\Model\WorkflowSetWorkflowSetIntegration;
use TrackMage\Client\Swagger\ApiException;

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
		add_filter( 'pre_update_option_trackmage_workspace', [ $this, 'select_workspace' ], 10, 3 );
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
			__( 'General', 'trackmage' ),
			__( 'General', 'trackmage' ),
			'manage_options',
			'trackmage',
			[ $this, 'render' ]
		);

		add_submenu_page(
			'trackmage',
			__( 'Statuses', 'trackmage' ),
			__( 'Statuses', 'trackmage' ),
			'manage_options',
			'admin.php?page=trackmage&tab=statuses'
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
	 * Add/remove webhooks based on the selected workspace.
	 *
	 * @since 0.1.0
	 */
	public function select_workspace( $value, $old_value, $option ) {
		// Exit if value has not changed.
		if ( $value === $old_value ) {
			return $old_value;
		}

		$client = Plugin::get_client();
		$url = Utils::get_endpoint();

		// Find and remove any activated webhook, if any.
		$webhook = get_option( 'trackmage_webhook', '' );
		if ( ! empty( $webhook ) ) {
			try {
				$client->getWorkflowApi()->deleteWorkflowItem( $webhook );
				update_option( 'trackmage_webhook', '' );
			} catch ( ApiException $e ) {
				// Trigger error message and exit.
				return $old_value;
			}
		}

		// Stop here if no workspace is selected.
		if ( empty( $value ) ) {
			return 0;
		}

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
			'workspace' => '/workspaces/' . $value,
			'integration' => $integration,
			'enabled' => true,
		];

		$workflow = new WorkflowSetWorkflowSetIntegration( $workflow );

		try {
			$result = $client->getWorkflowApi()->postWorkflowCollection( $workflow );
		} catch ( ApiException $e ) {
			// Trigger error message and exit.
			return $old_value;
		}

		update_option( 'trackmage_webhook', $result->getId() );
		return $value;
	}
}