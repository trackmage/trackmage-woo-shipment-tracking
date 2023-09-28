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

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use TrackMage\Client\TrackMageClient;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Helper;

/**
 * The Admin class.
 *
 * @since 0.1.0
 */
class Admin {

    const LOCK_CHANGES_TIMEOUT = 300;

    private $lockedChangesAt = null;

    /**
     * @since 0.1.0
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_init', [$this, 'settings']);
        add_action('wp_ajax_trackmage_test_credentials', [$this, 'test_credentials']);
        add_action('wp_ajax_trackmage_reload_workspaces', [$this, 'reload_workspaces']);

        add_filter('pre_update_option_trackmage_client_id', [$this, 'trimCredentials'], 10, 3);
        add_filter('pre_update_option_trackmage_client_secret', [$this, 'trimCredentials'], 10, 3);
        add_filter('update_option_trackmage_client_id', [$this, 'changed_api_credentials'], 10, 3);
        add_filter('update_option_trackmage_client_secret', [$this, 'changed_api_credentials'], 10, 3);

        add_filter('pre_update_option_reset_plugin_settings', [$this, 'resetPluginSettings'], 10, 3);
        add_filter('pre_update_option_trackmage_workspace', [$this, 'select_workspace'], 10, 3);

        add_filter('pre_update_option_trackmage_delete_data', [$this, 'trigger_delete_data'], 10, 3);
        add_filter('pre_update_option_trackmage_trigger_sync', [$this, 'trigger_sync'], 20, 3);
        add_filter( 'plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2 );
        add_filter( 'plugin_action_links_' . TRACKMAGE_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
    }

    /**
     * Registers setting pages.
     *
     * @since 0.1.0
     */
    public function add_page() {
        add_menu_page(
            __('TrackMage', 'trackmage'),
            __('TrackMage', 'trackmage'),
            'manage_options',
            get_transient( 'trackmage-wizard-notice' ) ? 'trackmage-wizard' : 'trackmage-settings',
            '',
            TRACKMAGE_URL . 'assets/dist/images/trackmage-icon-white-16x16.png',
            30
        );

        add_submenu_page(
            'trackmage-settings',
            __('Settings', 'trackmage'),
            __('Settings', 'trackmage'),
            'manage_options',
            get_transient( 'trackmage-wizard-notice' ) ? 'trackmage-wizard' : 'trackmage-settings',
            [$this, 'renderSettings']
        );

        add_submenu_page(
            'trackmage-settings',
            __('Status Manager', 'trackmage'),
            __('Status Manager', 'trackmage'),
            'manage_options',
            'trackmage-status-manager',
            [$this, 'renderStatusManager']
        );
    }

    /**
     * Registers the general setting fields.
     *
     * @since 0.1.0
     */
    public function settings() {
        // General settings.
        register_setting('trackmage_general', 'trackmage_client_id');
        register_setting('trackmage_general', 'trackmage_client_secret');
        register_setting('trackmage_general', 'trackmage_workspace');

        // Statuses settings.
        register_setting('trackmage_general', 'trackmage_sync_statuses');
        register_setting('trackmage_general', 'trackmage_sync_start_date');

        // Buttons must be processed at the end
        register_setting('trackmage_general', 'trackmage_trigger_sync');
        register_setting('trackmage_general', 'trackmage_delete_data');
        register_setting('trackmage_general', 'reset_plugin_settings');
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
        if(!isset($_POST['clientId'], $_POST['clientSecret'])){
            wp_send_json_error([
                'status' => 'error',
                'errors' => [
                    __('Values should not be empty', 'trackmage'),
                ]
            ]);
        }
        $credentials = Helper::check_credentials(sanitize_key($_POST['clientId']), sanitize_key($_POST['clientSecret']));
        if (Helper::CREDENTIALS_INVALID === $credentials) {
            wp_send_json_error([
                'status' => 'error',
                'errors' => [
                    __('Invalid credentials.', 'trackmage'),
                ]
            ]);
        }

        if (Helper::CREDENTIALS_VALID === $credentials) {
            wp_send_json_success([
                'status' => 'success',
            ]);
        }

        if (Helper::CREDENTIALS_ERROR === $credentials) {
            wp_send_json_error([
                'status' => 'error',
                'errors' => [
                    __('We could not perform the check. Please check credentials in TrackMage and try again.', 'trackmage'),
                ]
            ]);
        }
    }

    public function reload_workspaces(){
        $workspaces = Helper::get_workspaces(true);
        if($workspaces !== false){
            wp_send_json_success([
                'status'    => 'success',
                'message'   => __('Workspaces have been reloaded', 'trackmage'),
                'workspaces' => $workspaces
            ]);
        } else {
            wp_send_json_error([
                'status' => 'error',
                'errors' => [
                    __('We could not perform the check. Please try again.', 'trackmage'),
                ]
            ]);
        }
    }

    public function trimCredentials($value, $old_value, $option){
        return trim($value);
    }

    public function changed_api_credentials($old_value, $value, $option){
        // Exit if value has not changed.
        if ($value === $old_value) {
            return;
        }
        Helper::clearTransients();
    }

    /**
     * Add/remove webhooks based on the selected workspace.
     *
     * @since 0.1.0
     */
    public function select_workspace($value, $old_value, $option) {
        if(null !== $this->lockedChangesAt && (time() - $this->lockedChangesAt) < self::LOCK_CHANGES_TIMEOUT  ) {
            return $old_value;
        }
        $resetPlugin = isset($_POST['reset_plugin_settings']) && $_POST['reset_plugin_settings'] === '1';
        // Exit if value has not changed.
        if ($value === $old_value || $resetPlugin) {
            return $old_value;
        }
        $this->lockedChangesAt = time();
        $deleteData = isset($_POST['trackmage_delete_data']) && $_POST['trackmage_delete_data'] === '1';

        $workspaces = Helper::get_workspaces(true);
        if (!empty($value) && !in_array($value, array_column($workspaces, 'id'))) {
            add_settings_error('trackmage_workspace', 0, 'Trackmage cannot be connected to selected workspace: '.$value);
            return $old_value;
        }
        $client = Plugin::get_client();
        $url = Helper::get_endpoint();

        // Find and remove any activated integration and webhook, if any.
        $integration = get_option('trackmage_integration', '');
        $webhook = get_option('trackmage_webhook', '');

        if (! empty($integration) && in_array($old_value, array_map(fn($ws) => $ws['id'], $workspaces), true)) {
            try {
                $client->delete('/workflows/'.$integration, [RequestOptions::QUERY => ['deleteData'=>$deleteData]]);
            } catch(ClientException $e){
                $error = TrackMageClient::error($e);
                $error = trim(str_contains($error, ':') ? explode(':', $error)[1] : $error);
                error_log('Unable to delete workflow: '.$error);
                add_settings_error('trackmage_workspace', 0, $error);
                set_transient('trackmage_error', $error, 10);
                add_action( 'admin_notices', [$this, 'showErrorNotice'], 1 );
                return $old_value;
            }
        }

        update_option('trackmage_webhook', '');
        update_option('trackmage_integration', '');
        update_option('trackmage_team', '');

        Helper::clearTransients();

        // Stop here if no workspace is selected.
        if (empty($value)) {
            $this->lockedChangesAt = null;
            return 0;
        }

        // Generate random username and password.
        $username = wp_get_current_user()->user_login . '_' . substr(md5(time() . rand(0, 1970)), 0, 5);
        $password = md5($username . rand(1, 337));
        update_option('trackmage_webhook_username', $username);
        update_option('trackmage_webhook_password', $password);

        $integration = [
            'title' => get_bloginfo('url'),
            'workspace' => '/workspaces/' . $value,
            'type' => 'integration-woocommerce',
            'enabled' => true,
        ];

        try {
            $response = $client->get("/workspaces/{$value}/workflows", [
                'query' => [
                    'type' => 'integration-woocommerce',
                    'title' => get_bloginfo('url')
                ]
            ]);
            $integrations = TrackMageClient::collection($response);
            if(count($integrations) > 0) {
                $integration = "/workflows/{$integrations[0]['id']}";
            }
        } catch( ClientException $e ) {
            $error = TrackMageClient::error($e);
            $error = trim(str_contains($error, ':') ? explode(':', $error)[1] : $error);
            error_log('An error during request to TrackMage: ' . $error);
            $this->lockedChangesAt = null;
            add_settings_error('trackmage_workspace', 0, $error);
            set_transient('trackmage_error', $error, 10);
            add_action( 'admin_notices', [$this, 'showErrorNotice'], 1 );
            return null;
        }

        $workflow = [
            'type' => 'webhook',
            'period' => 'immediately',
            'event' => 'update',
            'title' => get_bloginfo('name'),
            'workspace' => '/workspaces/' . $value,
            'url' => $url,
            'authType' => 'basic',
            'username' => $username,
            'password' => $password,
            'enabled' => true,
            'entity' => 'orders',
            'integration' => $integration
        ];

        try {
            $response = $client->post('/workflows', ['json' => $workflow]);
            $data = TrackMageClient::item($response);
        } catch( ClientException $e ) {
            $error = TrackMageClient::error($e);
            $error = trim(str_contains($error, ':') ? explode(':', $error)[1] : $error);
            error_log('Unable to create webhook: '.$error);
            $this->lockedChangesAt = null;
            add_settings_error('trackmage_workspace', 0, $error);
            set_transient('trackmage_error', $error, 10);
            add_action( 'admin_notices', [$this, 'showErrorNotice'], 1 );
            return null;
        }

        if (false !== $idx = array_search($value, array_column($workspaces, 'id'))) {
            update_option('trackmage_team', $workspaces[$idx]['team']);
        }

        Helper::unlinkAllOrders();
        Helper::unlinkAllProducts();

        update_option( 'trackmage_order_status_aliases', [] );
        update_option('trackmage_webhook', $data['id']);
        update_option('trackmage_integration', $data['integration']['id']);
        $this->lockedChangesAt = null;
        return $value;
    }

    public function trigger_delete_data($value, $old_value, $option) {
        if (isset($_POST['trackmage_delete_data']) && $_POST['trackmage_delete_data'] === '1'){
            Helper::unlinkAllOrders();
            Helper::unlinkAllProducts();
        }
        return 0;
    }

    public function trigger_sync($value, $old_value, $option) {
        if($value === '1') {
            $allOrdersIds = Helper::getAllOrdersIds();
            $backgroundTaskRepo = Plugin::instance()->getBackgroundTaskRepo();
            foreach(array_chunk($allOrdersIds, 50) as $ordersIds) {
                $backgroundTask = $backgroundTaskRepo->insert([
                    'action' => 'trackmage_bulk_orders_sync',
                    'params' => json_encode($ordersIds),
                    'status' => 'new',
                    'priority' => 10
                ]);
            }
            if(!(isset($_POST['trackmage_delete_data']) && (int) $_POST['trackmage_delete_data'] !== 0) ){
                Helper::scheduleNextBackgroundTask();
            }
        }
        return 0;
    }

    public function resetPluginSettings($value, $old_value){
        $resetPlugin = $value === '1';
        if($resetPlugin){
            $logger     = Plugin::instance()->getLogger();
            $deleteData = isset($_POST['trackmage_delete_data']) && $_POST['trackmage_delete_data'] === '1';
            $logger->info('Run Reset all plugin settings and data', ['deleteData' => $deleteData]);
            try{
                $integration = get_option('trackmage_integration', '');
                if(!empty($integration)){
                    $client = Plugin::get_client();
                    $client->delete('/workflows/' . $integration, [RequestOptions::QUERY => ['deleteData' => $deleteData]]);
                }

                $backgroundTaskRepo = Plugin::instance()->getBackgroundTaskRepo();
                $backgroundTaskRepo->truncate();
                Helper::clearTransients();
                Helper::clearOptions();
                set_transient( 'trackmage-wizard-notice', true );
            }catch(\Exception $e){
                $logger->error('Error during resetting: ' . $e->getMessage(), $e->getTrace());
            }
            $logger->info('Finish Reset all plugin settings and data');
            wp_redirect(admin_url('admin.php?page=trackmage-wizard'));
            die();
        }
        return 0;
    }

    public function showErrorNotice() {
        $error = get_transient('trackmage_error');
        $msg = !$error ? 'An error occurred during changing workspace. Please try again later or contact with <a href="mailto:support@trackmage.com">TrackMage.com support</a>' : esc_html($error);
            ?>
        <div class="error notice notice-error">
            <p><?php _e( $msg, 'trackmage' ); ?></p>
        </div>
            <?php
    }

    public function plugin_row_meta( $links, $file ) {
        if ( TRACKMAGE_PLUGIN_BASENAME !== $file ) {
            return $links;
        }
        $docs_url = 'https://help.trackmage.com/en';
        $api_docs_url = 'https://docs.trackmage.com/docs/';
        $community_support_url = 'mailto:support@trackmage.com';
        $pricing_url = 'https://trackmage.com/pricing/';
        $row_meta = array(
            'docs'    => '<a href="' . esc_url( $docs_url ) . '" aria-label="' . esc_attr__( 'View Trackmage documentation', 'trackmage' ) . '">' . esc_html__( 'User Guide', 'trackmage' ) . '</a>',
            'apidocs' => '<a href="' . esc_url( $api_docs_url ) . '" aria-label="' . esc_attr__( 'View Trackmage API docs', 'trackmage' ) . '">' . esc_html__( 'For Developers', 'trackmage' ) . '</a>',
            'pricing' => '<a href="' . esc_url( $pricing_url ) . '" aria-label="' . esc_attr__( 'View Trackmage Pricing', 'trackmage' ) . '">' . esc_html__( 'Pricing', 'trackmage' ) . '</a>',
            'support' => '<a href="' . esc_url( $community_support_url ) . '" aria-label="' . esc_attr__( 'Contact Us', 'trackmage' ) . '">' . esc_html__( 'Contact Support', 'trackmage' ) . '</a>',
        );

        return array_merge( $links, $row_meta );
    }

    public function plugin_action_links( $links ) {
        $action_links = array(
            'settings' => '<a href="' . admin_url( 'admin.php?page=trackmage-settings' ) . '" aria-label="' . esc_attr__( 'View Trackmage settings', 'trackmage' ) . '">' . esc_html__( 'Settings', 'trackmage' ) . '</a>',
        );

        return array_merge( $action_links, $links );
    }
}
