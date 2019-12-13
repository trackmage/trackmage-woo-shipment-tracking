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

class Wizard {


    /**
     * Admin page identifier.
     *
     * @var string
     */
    const PAGE_IDENTIFIER = 'trackmage-wizard';

    /**
     * The constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        if ( ! ( $this->isConfigPage() && current_user_can( 'manage_options' ) ) ) {
            return;
        }
        // Register the page for the wizard.
        add_action( 'admin_menu', [ $this, 'addWizardPage' ] );
        //add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
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
        wp_enqueue_media();

        if ( ! wp_script_is( 'wp-element', 'registered' ) && function_exists( 'gutenberg_register_scripts_and_styles' ) ) {
            gutenberg_register_scripts_and_styles();
        }

        wp_enqueue_style( 'forms' );
        /*
        $asset_manager = new WPSEO_Admin_Asset_Manager();
        $asset_manager->register_wp_assets();
        $asset_manager->register_assets();
        $asset_manager->enqueue_script( 'configuration-wizard' );
        $asset_manager->enqueue_style( 'yoast-components' );

        $config = $this->get_config();

        wp_localize_script( WPSEO_Admin_Asset_Manager::PREFIX . 'configuration-wizard', 'yoastWizardConfig', $config );

        $yoast_components_l10n = new WPSEO_Admin_Asset_Yoast_Components_L10n();
        $yoast_components_l10n->localize_script( WPSEO_Admin_Asset_Manager::PREFIX . 'configuration-wizard' );*/
    }

    /**
     * Setup Wizard Header.
     */
    public function showWizard() {
        $this->enqueue_assets();
        $settings_url = admin_url( '/admin.php?page=trackmage-settings' );
        $wizard_title  = sprintf(
        /* translators: %s expands to TrackMage. */
            __( '%s &rsaquo; Configuration Wizard', 'trackmage' ),
            'TrackMage'
        );
        include(TRACKMAGE_VIEWS_DIR . 'wizard/container.php');

       // wp_print_scripts( 'trackmage-configuration-wizard' );
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

        wp_redirect( admin_url( 'admin.php?page=' . WPSEO_Admin::PAGE_IDENTIFIER ) );
        exit;
    }

    /**
     * Checks if the current page is the configuration page.
     *
     * @return bool
     */
    protected function isConfigPage() {
        return ( filter_input( INPUT_GET, 'page' ) === self::PAGE_IDENTIFIER );
    }
}
