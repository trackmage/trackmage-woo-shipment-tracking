<?php
/**
 * Plugin Name:       TrackMage - Woo Shipment Tracking
 * Plugin URI:        https://trackmage.com/
 * Description:       TrackMage integrates shipments tracking into your WooCommerce store.
 * Version:           2.1.0
 * Author:            TrackMage
 * Author URI:        https://trackmage.com
 * Text Domain:       trackmage
 * License:           GPL-3.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * GitHub Plugin URI: https://github.com/trackmage/trackmage-woo-shipment-tracking
 * Requires PHP: 7.4
 * Requires at least: 5.3
 * Tested up to: 6.9
 * Requires Plugins: woocommerce
 * WC requires at least: 4.5.0
 * WC tested up to: 10.7
 *
 * Copyright (c) 2019-2026 TrackMage
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Helper;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once ABSPATH . '/wp-admin/includes/plugin.php';

if (PHP_VERSION_ID < 70400 || (!is_plugin_active('woocommerce/woocommerce.php') && !is_plugin_active_for_network('woocommerce/woocommerce.php'))) {
	add_action( 'plugins_loaded', 'trackmage_init_deactivation' );

	/**
	 * Initialise deactivation functions.
	 */
	function trackmage_init_deactivation() {
		if ( current_user_can( 'activate_plugins' ) ) {
			add_action( 'admin_init', 'trackmage_deactivate' );
			add_action( 'admin_notices', 'trackmage_deactivation_notice' );
		}
	}

	/**
	 * Deactivate the plugin.
	 */
	function trackmage_deactivate() {
		deactivate_plugins( plugin_basename( __FILE__ ) );
	}

	/**
	 * Show deactivation admin notice.
	 */
	function trackmage_deactivation_notice() {
	    if(PHP_VERSION_ID < 70400) {
            $notice = sprintf(
            // Translators: 1: Required PHP version, 2: Current PHP version.
                __( '<strong>TrackMage for WordPress</strong> requires PHP %1$s to run. This site uses %2$s, so the plugin has been <strong>deactivated</strong>.', 'trackmage' ),
                '7.4',
                PHP_VERSION
            );
        }else{
	        $notice = __('To use TrackMage for WooCommerce it is required that WooCommerce is installed and activated', 'trackmage');
        }
		?>
		<div class="error"><p><?php echo wp_kses_post( $notice ); ?></p></div>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	return false;
}



if ( ! defined( 'TRACKMAGE_VERSION' ) ) {
    // phpcs:ignore NeutronStandard.Constants.DisallowDefine.Define
    define( 'TRACKMAGE_VERSION', '2.1.0' );
}

if ( ! defined( 'TRACKMAGE_DIR' ) ) {
    // phpcs:ignore NeutronStandard.Constants.DisallowDefine.Define
    define( 'TRACKMAGE_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TRACKMAGE_VIEWS_DIR' ) ) {
    // phpcs:ignore NeutronStandard.Constants.DisallowDefine.Define
    define( 'TRACKMAGE_VIEWS_DIR', plugin_dir_path( __FILE__ ) . 'views/' );
}

if ( ! defined( 'TRACKMAGE_URL' ) ) {
    // phpcs:ignore NeutronStandard.Constants.DisallowDefine.Define
    define( 'TRACKMAGE_URL', plugin_dir_url( __FILE__ ) );
}

$content = file_get_contents(__DIR__ . '/vendor/composer/autoload_real.php');
$composerAutoloaderInitClassName = preg_match('/class\s+(ComposerAutoloaderInit[a-zA-Z0-9_]+)/', $content, $matches) ? $matches[1] ?? null : null;
// Load Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) && (null === $composerAutoloaderInitClassName || !class_exists($composerAutoloaderInitClassName, false))) {
    require_once __DIR__ . '/vendor/autoload.php';
}


define('TRACKMAGE_PLUGIN_FILE',	__FILE__);

define('TRACKMAGE_PLUGIN_BASENAME', plugin_basename( TRACKMAGE_PLUGIN_FILE ));

if (!defined('TRACKMAGE_API_DOMAIN')) {
    define('TRACKMAGE_API_DOMAIN', 'https://api.trackmage.com');
}

if (!defined('TRACKMAGE_APP_DOMAIN')) {
    define('TRACKMAGE_APP_DOMAIN', 'https://app.trackmage.com');
}

add_action('before_woocommerce_init', 'trackMageDeclareWooCompat');
add_action('plugins_loaded', 'trackMageInit');
add_action('init', 'trackMageLoadTextdomain');
register_activation_hook(__FILE__, 'trackMageActivate');
register_deactivation_hook(__FILE__, 'trackMageDeactivate');
register_uninstall_hook( __FILE__, 'trackMageUninstall');

function trackMageLoadTextdomain() {
    load_plugin_textdomain( 'trackmage', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * trackMageActivate
 *
 * Plugin activate event
 */
function trackMageActivate() {
    $plugin = Plugin::instance();
    foreach($plugin->getRepos() as $repository) {
        try {
            $repository->init();
            $plugin->getLogger()->info("Successfully created table {$repository->getTable()}");
        } catch(Exception $e) {
            $plugin->getLogger()->critical("Unable to create table {$repository->getTable()}: {$e->getMessage()}");
        }
    }
    $plugin->dropOldTables();
    $plugin->init();
    if(!get_transient('trackmage-wizard-notice'))
        set_transient( 'trackmage-wizard-notice', true );
}

/**
 * Plugin deactivate event
 */
function trackMageDeactivate() {
    Helper::clearTransients();
}


/**
 * Declare WooCommerce feature compatibility.
 *
 * Runs on the "before_woocommerce_init" hook so that WooCommerce sees the
 * declaration before its own boot sequence finishes. Feature-detected so the
 * plugin keeps working on WooCommerce versions older than 7.1 (which is when
 * FeaturesUtil was introduced) without raising the WC minimum floor.
 *
 * Features declared:
 * - custom_order_tables: HPOS compatibility (see the WC_Order CRUD usage
 *   throughout the plugin).
 * - cart_checkout_blocks: the plugin does not inject fields or content into
 *   the cart/checkout forms (tracking info is surfaced post-purchase via
 *   order meta, emails, and the [woocommerce_order_tracking] shortcode), so
 *   it is compatible with the React-based Cart and Checkout blocks.
 */
function trackMageDeclareWooCompat() {
    if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        return;
    }
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        TRACKMAGE_PLUGIN_FILE,
        true
    );
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'cart_checkout_blocks',
        TRACKMAGE_PLUGIN_FILE,
        true
    );
}

/**
 * Initialize trackMage plugin components
 */
function trackMageInit() {
    if(!is_plugin_active('woocommerce/woocommerce.php') && !is_plugin_active_for_network('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'trackMageWooCommerceError');
        return;
    }
    Plugin::instance()->init();
}


/**
 * Display error message: WooCommerce not active
 */
function trackMageWooCommerceError() {
    printf('<div class="error"><p>%s</p></div>', __('To use TrackMage for WooCommerce it is required that WooCommerce is installed and activated'));
}

function trackMageUninstall(){
    foreach(Plugin::instance()->getRepos() as $repository) {
        try {
            $repository->drop();
        } catch(Exception $e) {
            Plugin::instance()->getLogger()->critical("Unable to drop table {$repository->getTable()}: {$e->getMessage()}");
        }
    }
    Plugin::instance()->dropOldTables();
    Helper::clearTransients();
}
