<?php
/**
 * The Admin class.
 *
 * Initialize and render the settings page.
 *
 * @package TrackMage\WordPress\Admin
 * @author  TrackMage
 */

namespace TrackMage\WordPress\Admin;

use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Utils;
use TrackMage\Client\Swagger\Model\WorkflowSetWorkflowSetIntegration;
use TrackMage\Client\Swagger\ApiException;

/**
 * The Admin class.
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
		add_action( 'wp_ajax_trackmage_status_manager_save', [ $this, 'status_manager_save' ] );
		add_action( 'wp_ajax_trackmage_status_manager_add', [ $this, 'status_manager_add' ] );
		add_action( 'wp_ajax_trackmage_status_manager_delete', [ $this, 'status_manager_delete' ] );
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
			'trackmage-settings',
			'',
			TRACKMAGE_URL . 'assets/dist/images/trackmage-icon-white-16x16.png',
			30
		);

		add_submenu_page(
			'trackmage-settings',
			__( 'Settings', 'trackmage' ),
			__( 'Settings', 'trackmage' ),
			'manage_options',
			'trackmage-settings',
			[ $this, 'renderSettings' ]
		);

		add_submenu_page(
			'trackmage-settings',
			__( 'Status Manager', 'trackmage' ),
			__( 'Status Manager', 'trackmage' ),
			'manage_options',
			'trackmage-status-manager',
			[ $this, 'renderStatusManager' ]
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

		// Statuses settings.
		register_setting( 'trackmage_statuses', 'trackmage_sync_statuses' );
	}

	/**
	 * Renders settings page.
	 *
	 * @since 1.0.0
	 */
	public function renderSettings() {
		require_once TRACKMAGE_VIEWS_DIR . 'admin-page-settings.php';
	}

	/**
	 * Renders status manager page.
	 *
	 * @since 1.0.0
	 */
	public function renderStatusManager() {
		require_once TRACKMAGE_VIEWS_DIR . 'admin-page-status-manager.php';
	}

	/**
	 * Tests API keys.
	 *
	 * @since 0.1.0
	 */
	public function test_credentials() {
		$credentials = Utils::check_credentials( $_POST['clientId'], $_POST['clientSecret'] );

		if ( 0 === $credentials ) {
			wp_send_json_error( [
				'status' => 'error',
				'errors' => [
					__( 'Invalid credentials.', 'trackmage' ),
				]
			] );
		}

		if ( 1 === $credentials ) {
			wp_send_json_success( [
				'status' => 'success',
			] );
		}

		if ( 2 === $credentials ) {
			wp_send_json_error( [
				'status' => 'error',
				'errors' => [
					__( 'We could not peform the check. Please try again.', 'trackmage' ),
				]
			] );
		}
	}

	public function status_manager_save() {
		$name = $_POST['name'];
		$current_slug = $_POST['currentSlug'];
		$slug = $_POST['slug'];
		$alias = $_POST['alias'];
		$is_custom = '1' === $_POST['isCustom'] ? true : false;

		$custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
		$modified_statuses = get_option( 'trackmage_modified_order_statuses', [] );
		$status_aliases = get_option( 'trackmage_order_status_aliases', [] );
		$aliases = Utils::get_aliases();
		$get_statuses = Utils::get_order_statuses();

		// Errors array.
		$errors = [];

		if ( empty ( $name ) ) {
			array_push( $errors, __( 'Status name cannot be empty.', 'trackmage' ) );
		}

		if ( empty ( $slug ) ) {
			array_push( $errors, __( 'Status slug cannot be empty.', 'trackmage' ) );
		}

		if ( $current_slug !== $slug && isset( $get_statuses[ $slug ] ) ) {
			array_push( $errors, sprintf( __( 'The slug <em>“%1$s”</em> is already used by another status.', 'trackmage' ), $slug ) );
		}

		if ( $is_custom && $current_slug !== $slug ) {
			unset( $custom_statuses[ $current_slug ] );
		}
		
		if ( ! $is_custom && $current_slug !== $slug ) {
			array_push( $errors, __( 'The slug of core statuses and statuses created by other plugins and themes cannot be changed.', 'trackmage' ) );
		}

		if ( $is_custom ) {
			$custom_statuses[ $slug ] = __( $name, 'trackmage' );
		} else {
			$modified_statuses[ $slug ] = __( $name, 'trackmage' );
		}

		if ( ! empty( $alias )
			&& in_array( $alias, $status_aliases )
			&& isset( $status_aliases[ $current_slug ] )
			&& $alias !== $status_aliases[ $current_slug ] )
		{
			array_push( $errors, sprintf( __( 'The alias <em>“%1$s”</em> is already assigned to another status.', 'trackmage' ), $aliases[$alias] ) );
		} else {
			$status_aliases[ $slug ] = $alias;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( [
				'status' => 'error',
				'errors' => $errors,
			] );
		}

		update_option( 'trackmage_custom_order_statuses', $custom_statuses );
		update_option( 'trackmage_modified_order_statuses', $modified_statuses );
		update_option( 'trackmage_order_status_aliases', $status_aliases );

		wp_send_json_success( [
			'status' => 'success',
			'result' => [
				'name'  => $name,
				'slug'  => $slug,
				'alias' => $alias,
			]
		] );
	}

	public function status_manager_add() {
		$name  = $_POST['name'];
		$slug  = 'wc-' . $_POST['slug'];
		$alias = $_POST['alias'];

		$custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
		$status_aliases = get_option( 'trackmage_order_status_aliases', [] );
		$aliases = Utils::get_aliases();
		$get_statuses = Utils::get_order_statuses();
		
		// Errors array.
		$errors = [];

		if ( empty ( $name ) ) {
			array_push( $errors, __( 'Status name cannot be empty.', 'trackmage' ) );
		}

		if ( empty ( $slug ) ) {
			array_push( $errors, __( 'Status slug cannot be empty.', 'trackmage' ) );
		}

		if ( isset( $get_statuses[ $slug ] ) ) {
			array_push( $errors, sprintf( __( 'The slug <em>“%1$s”</em> is already used by another status.', 'trackmage' ), $slug ) );
		}

		if ( ! empty( $alias ) && in_array( $alias, $status_aliases ) ) {
			array_push( $errors, sprintf( __( 'The alias <em>“%1$s”</em> is already assigned to another status.', 'trackmage' ), $aliases[$alias] ) );
		} else {
			$status_aliases[ $slug ] = $alias;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( [
				'status' => 'error',
				'errors' => $errors,
			] );
		}

		$custom_statuses[ $slug ] = __( $name, 'trackmage' );

		update_option( 'trackmage_custom_order_statuses', $custom_statuses );
		update_option( 'trackmage_order_status_aliases', $status_aliases );

		wp_send_json_success( [
			'status' => 'success',
			'result' => [
				'name'  => $name,
				'slug'  => $slug,
				'alias' => $alias,
			]
		] );
	}

	public function status_manager_delete() {
		$slug  = $_POST['slug'];

		$custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
		$status_aliases = get_option( 'trackmage_order_status_aliases', [] );
		
		// Errors array.
		$errors = [];

		if ( empty ( $slug ) ) {
			array_push( $errors, __( 'Could not delete the selected status.', 'trackmage' ) );
		}

		if ( ! array_key_exists( $slug, $custom_statuses ) ) {
			array_push( $errors, __( 'Core statuses and statuses created by other plugins and themes cannot be deleted.', 'trackmage' ) );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( [
				'status' => 'error',
				'errors' => $errors,
			] );
		}

		unset( $custom_statuses[ $slug ] );
		unset( $status_aliases[ $slug ] );

		update_option( 'trackmage_custom_order_statuses', $custom_statuses );
		update_option( 'trackmage_order_status_aliases', $status_aliases );

		wp_send_json_success( [
			'status' => 'success',
			'result' => [
				'name'  => $name,
			]
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
				// Do nothing. Webhook might be removed from TrackMage.
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