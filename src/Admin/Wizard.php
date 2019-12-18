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

use GuzzleHttp\Exception\ClientException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Helper;
use TrackMage\Client\Swagger\ApiException;
use TrackMage\WordPress\Assets;

class Wizard {


    /**
     * Admin page identifier.
     *
     * @var string
     */
    const PAGE_IDENTIFIER = 'trackmage-wizard';

    const AJAX_PAGE_CONTENT_ENDPOINT = 'get_step_content';
    const AJAX_PAGE_PROCESS_ENDPOINT = 'process_step';

    private $_steps;
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

        $this->_steps = [
            [ 'code' => 'credentials', 'title' => __('API Credentials','trackmage'), 'icon' => ''],
            [ 'code' => 'workspace', 'title' => __('Workspace','trackmage'), 'icon' => ''],
            [ 'code' => 'statuses', 'title' => __('Sync statuses','trackmage'), 'icon' => '']
            //[ 'code' => 'finish', 'title' => __('Finish','trackmage'), 'icon' => '']
        ];

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
            'steps' => $this->_steps,
            'i18n' => [
                'noSelect'     => __('— Select —', 'trackmage'),
                'success'      => __('Success', 'trackmage'),
                'failure'      => __('Failure', 'trackmage'),
                'unknownError' => __('Unknown error occured.', 'trackmage'),
                'testCredentials'  => __('Test Credentials', 'trackmage'),
                'successValidKeys' => __('Valid credentials. Click on <em>“Save Changes”</em> for the changes to take effect.', 'trackmage'),
            ],
        ]);

        //Assets::enqueueStyles();
        //Assets::enqueueScripts();
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

        //wp_redirect( admin_url( 'admin.php?page=' . WPSEO_Admin::PAGE_IDENTIFIER ) );
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

        $step = $_POST['step'];
        $data = '';
        if(file_exists(TRACKMAGE_VIEWS_DIR . "wizard/step-{$step}.php")) {
            ob_start();
            include( TRACKMAGE_VIEWS_DIR . "wizard/step-{$step}.php" );
            $data = ob_get_clean();

        }
        wp_send_json_success([
            'html' => $data
        ]);
    }

    public function processNextStep(){
        $step = $_POST['step'];
        switch ($step){
            case 'credentials':
                $clientId = $_POST['trackmage_client_id'];
                $clientSecret = $_POST['trackmage_client_secret'];
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
                $workspaceId = $_POST['trackmage_workspace'];
                try {
                    update_option( 'trackmage_workspace', $workspaceId );
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
                $statuses = $_POST['trackmage_sync_statuses'];
                try {
                    update_option( 'trackmage_sync_statuses', $statuses );
                }catch (\Exception $e){
                    wp_send_json_error([
                        'status' => 'error',
                        'errors' => [
                            $e->getMessage(),
                        ]
                    ]);
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
                    /* translators: %s expands to TrackMage. */
                        __( 'First-time %s configuration', 'trackmage' ),
                        'TrackMage'
                    );?></h3>
                <h3><?php echo $message?></h3>
            </div>
            <?php
        }
    }
}
