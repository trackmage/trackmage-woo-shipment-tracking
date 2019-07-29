<?php
/**
 * Initialise the plugin
 *
 * This file can use syntax from the required level of PHP or later.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

use BrightNucleus\Config\ConfigFactory;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'TRACKMAGE_DIR' ) ) {
	// phpcs:ignore NeutronStandard.Constants.DisallowDefine.Define
	define( 'TRACKMAGE_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TRACKMAGE_URL' ) ) {
	// phpcs:ignore NeutronStandard.Constants.DisallowDefine.Define
	define( 'TRACKMAGE_URL', plugin_dir_url( __FILE__ ) );
}

// Load Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin.
$GLOBALS['trackmage'] = new Plugin( ConfigFactory::create( __DIR__ . '/config/defaults.php' )->getSubConfig( 'TrackMage\WordPress' ) );
$GLOBALS['trackmage']->run();
