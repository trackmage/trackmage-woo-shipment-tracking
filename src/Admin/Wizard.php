<?php
/**
 * The Wizard class.
 *
 * Initialize and render the plugin wizard page.
 *
 * @package TrackMage\WordPress\Admin
 * @author  TrackMage
 */

namespace TrackMage\WordPress\Admin;

use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Helper;

class Wizard {

    /**
     * Admin page identifier.
     *
     * @var string
     */
    const PAGE_IDENTIFIER = 'trackmage-wizard';

    const AJAX_PAGE_CONTENT_ENDPOINT = 'get_step_content';
    const AJAX_PAGE_PROCESS_ENDPOINT = 'process_step';

    const STEPS = [
        [ 'code' => 'credentials', 'title' => 'API Credentials', 'icon' => ''],
        [ 'code' => 'workspace', 'title' => 'Workspace', 'icon' => ''],
        [ 'code' => 'statuses', 'title' => 'Sync statuses', 'icon' => '']
    ];
    /**
     * The constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        add_action('wp_ajax_trackmage_wizard_'.self::AJAX_PAGE_CONTENT_ENDPOINT, [$this, 'getStepHtml']);
        add_action('wp_ajax_trackmage_wizard_'.self::AJAX_PAGE_PROCESS_ENDPOINT, [$this, 'processNextStep']);

        add_action( 'admin_notices', [$this,'showAdminNotice'] );

        if ( ! ( $this->isWizardPage() && current_user_can( 'manage_options' ) ) ) {
            return;
        }

        // Register the page for the wizard.
        add_action( 'admin_menu', [ $this, 'addWizardPage' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
        add_action( 'admin_init', [ $this, 'renderWizardPage' ] );

    }

    /**
     *  Registers the page for the wizard.
     */
    public function addWizardPage() {
        add_dashboard_page( '', '', 'manage_options', self::PAGE_IDENTIFIER, '' );
    }

    /**
     * Renders the wizard page and exits to prevent the WordPress UI from loading.
     */
    public function renderWizardPage() {
        $this->showWizard();
        exit;
    }

    /**
     * Enqueues the assets needed for the wizard.
     */
    public function enqueue_assets() {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_media();

        if ( ! wp_script_is( 'wp-element', 'registered' ) && function_exists( 'gutenberg_register_scripts_and_styles' ) ) {
            gutenberg_register_scripts_and_styles();
        }


        wp_register_style('trackmage-bootstrap', TRACKMAGE_URL . 'assets/dist/css/admin/bootstrap' . $suffix . '.css', [], TRACKMAGE_VERSION, 'all');
        wp_enqueue_style('trackmage-bootstrap');
        wp_register_style('trackmage-wizard', TRACKMAGE_URL . 'assets/dist/css/admin/wizard' . $suffix . '.css', [], TRACKMAGE_VERSION, 'all');
        wp_enqueue_style('trackmage-wizard');
        // Enqueue WooCommerce styles.
        wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION);
        wp_enqueue_style('woocommerce_admin_styles');

        wp_enqueue_script('selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full.js', [], WC_VERSION, true);

        wp_register_script('trackmage-bootstrap', TRACKMAGE_URL . 'assets/dist/js/admin/bootstrap' . $suffix . '.js', ['jquery'], TRACKMAGE_VERSION, true);
        wp_enqueue_script('trackmage-bootstrap');

        wp_register_script('jquery-validate', TRACKMAGE_URL . 'assets/dist/js/admin/jquery.validate' . $suffix . '.js', ['jquery'], TRACKMAGE_VERSION, true);
        wp_enqueue_script('jquery-validate');

        wp_register_script('trackmage-wizard-lib', TRACKMAGE_URL . 'assets/dist/js/admin/jquery.bootstrap.wizard' . $suffix . '.js', ['jquery'], TRACKMAGE_VERSION, true);
        wp_enqueue_script('trackmage-wizard-lib');


        wp_register_script('trackmage-wizard', TRACKMAGE_URL . 'assets/dist/js/admin/wizard' . $suffix . '.js', ['jquery'], TRACKMAGE_VERSION, true);
        wp_enqueue_script('trackmage-wizard');
        wp_localize_script('trackmage-wizard', 'trackmageWizard', [
            'urls' => [
                'ajax' => admin_url('admin-ajax.php'),
                'completed' => admin_url('?page=trackmage-settings')
            ],
            'steps' => array_map(function ($step){ $step['title'] = __($step['title'], 'trackmage'); return $step;} , self::STEPS),
            'i18n' => [
                'noSelect'     => __('— Select —', 'trackmage'),
                'success'      => __('Success', 'trackmage'),
                'failure'      => __('Failure', 'trackmage'),
                'unknownError' => __('Unknown error occured.', 'trackmage'),
                'testCredentials'  => __('Test Credentials', 'trackmage'),
                'successValidKeys' => __('Valid credentials. Click on <em>“Save Changes”</em> for the changes to take effect.', 'trackmage'),
            ],
        ]);
    }

    /**
     * Setup Wizard Header.
     */
    public function showWizard() {
        $this->enqueue_assets();
        $settings_url = admin_url( '/admin.php?page=trackmage-settings' );
        $wizard_title  =  __( 'Installation Wizard', 'trackmage' );
        include(TRACKMAGE_VIEWS_DIR . 'wizard/container.php');
    }

    /**
     * Check if the configuration is finished. If so, just remove the notification.
     */
    public function catch_configuration_request() {
        $configuration_page = filter_input( INPUT_GET, 'wizard' );
        $page               = filter_input( INPUT_GET, 'page' );

        if ( ! ( $configuration_page === 'finished' && ( $page === 'trackmage-settings' ) ) ) {
            return;
        }

        $this->remove_notification();
        $this->remove_notification_option();

        exit;
    }

    /**
     * Checks if the current page is the configuration page.
     *
     * @return bool
     */
    protected function isWizardPage() {
        return ( filter_input( INPUT_GET, 'page' ) === self::PAGE_IDENTIFIER );
    }

    public function getStepHtml() {
        if (! current_user_can('manage_options')) {
            wp_die(-1);
        }

        $availableSteps = array_column(self::STEPS, 'code');
        $step = isset($_POST['step']) && in_array($step = sanitize_key($_POST['step']), $availableSteps, true) ? $step : $availableSteps[0];
        $data = '';
        if(file_exists(TRACKMAGE_VIEWS_DIR . "wizard/step-{$step}.php")) {
            if ($step !== 'credentials') {
                $clientId = isset($_POST['trackmage_client_id']) ? sanitize_key($_POST['trackmage_client_id']) : '';
                $clientSecret = isset($_POST['trackmage_client_secret']) ? sanitize_key($_POST['trackmage_client_secret']) : '';
                $credentials = Helper::check_credentials($clientId, $clientSecret);
            }
            ob_start();
            include( TRACKMAGE_VIEWS_DIR . "wizard/step-{$step}.php" );
            $data = ob_get_clean();

        }
        wp_send_json_success([
            'html' => $data
        ]);
    }

    public function processNextStep(){
        $availableSteps = $availableSteps = array_column(self::STEPS, 'code');
        $step = isset($_POST['step']) && in_array($step = sanitize_key($_POST['step']), $availableSteps, true) ? $step : $availableSteps[0];
        switch ($step){
            case 'credentials':
                $clientId = isset($_POST['trackmage_client_id']) ? sanitize_key($_POST['trackmage_client_id']) : '';
                $clientSecret = isset($_POST['trackmage_client_secret']) ? sanitize_key($_POST['trackmage_client_secret']) : '';
                $credentials = Helper::check_credentials($clientId, $clientSecret);

                if (Helper::CREDENTIALS_INVALID === $credentials) {
                    wp_send_json_error([
                        'status' => 'error',
                        'errors' => [
                            __('Invalid credentials.', 'trackmage'),
                        ]
                    ]);
                }

                if (Helper::CREDENTIALS_ERROR === $credentials) {
                    wp_send_json_error([
                        'status' => 'error',
                        'errors' => [
                            __('We could not perform the check. Please try again.', 'trackmage'),
                        ]
                    ]);
                }
                try {
                    update_option( 'trackmage_client_id', $clientId );
                    update_option( 'trackmage_client_secret', $clientSecret );
                }catch (\Exception $e){
                    wp_send_json_error([
                        'status' => 'error',
                        'errors' => [
                            $e->getMessage(),
                        ]
                    ]);
                }

                wp_send_json_success([
                    'status' => 'success',
                ]);
                break;
            case 'workspace':
                $workspaceId = isset($_POST['trackmage_workspace']) ? sanitize_key($_POST['trackmage_workspace']) : '';
                if(empty($workspaceId)) {
                    wp_send_json_error([
                        'status' => 'error',
                        'errors' => [
                            __('Please select workspace','trackmage')
                        ]
                    ]);
                }
                try {
                    $oldWsID = get_option( 'trackmage_workspace', null );
                    if (null !== $oldWsID && $oldWsID !== $workspaceId) {
                        Helper::unlinkAllOrders();
                        Helper::unlinkAllProducts();
                    }
                    update_option( 'trackmage_workspace', $workspaceId );
                    $workspaces = Helper::get_workspaces();
                    if (false !== $idx = array_search($workspaceId, array_column($workspaces, 'id'))) {
                        update_option('trackmage_team', $workspaces[$idx]['team']);
                    }
                }catch (\Exception $e){
                    wp_send_json_error([
                        'status' => 'error',
                        'errors' => [
                            $e->getMessage(),
                        ]
                    ]);
                }
                wp_send_json_success([
                    'status' => 'success',
                ]);
                break;
            case 'statuses':
                $statuses = isset($_POST['trackmage_sync_statuses']) && is_array($_POST['trackmage_sync_statuses']) ? $_POST['trackmage_sync_statuses'] : [];
                try {
                    update_option( 'trackmage_sync_statuses', $statuses );
                } catch (\Exception $e) {
                    wp_send_json_error(['status' => 'error', 'errors' => [$e->getMessage()]]);
                }
                $startDate = isset($_POST['trackmage_sync_start_date']) && $this->validateDate($_POST['trackmage_sync_start_date']) ? $_POST['trackmage_sync_start_date'] : null;
                try {
                    update_option('trackmage_sync_start_date', $startDate);
                } catch (\Exception $e) {
                    wp_send_json_error(['status' => 'error', 'errors' => [$e->getMessage()]]);
                }
                set_transient('trackmage-wizard-notice', false);
                $this->_triggerSync();
                wp_send_json_success([
                    'status' => 'success',
                ]);
                break;
            default:
                wp_send_json_error([
                    'status' => 'error',
                    'errors' => [
                        __('Unknown wizard step.', 'trackmage'),
                    ]
                ]);
        }
        wp_send_json_success([
            'step' => $step
        ]);
    }

    /**
     * @param string $date
     * @param string $format
     * @return bool
     */
    private function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }

    private function _triggerSync(){
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
        Helper::scheduleNextBackgroundTask();
    }

    private function _shouldShowNotification(){
        return get_transient( 'trackmage-wizard-notice' );
    }

    public function showAdminNotice(){
        /* Check transient, if available display notice */
        if( $this->_shouldShowNotification() ){
            $message = sprintf(
                __( 'We have detected that you have not finished this wizard yet, so we recommend you to %2$sstart the configuration wizard to configure %1$s%3$s.', 'trackmage' ),
                'TrackMage',
                '<a href="' . admin_url( '?page=' . self::PAGE_IDENTIFIER ) . '">',
                '</a>'
            );
            ?>
            <div class="updated notice is-dismissible">
                <h3><?php echo  sprintf(
                        __( 'First-time %s configuration', 'trackmage' ),
                        'TrackMage'
                    );?></h3>
                <h3><?php echo $message?></h3>
            </div>
            <?php
        }
    }
}
