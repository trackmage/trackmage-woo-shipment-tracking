<?php
/**
 * Plugin Name:       TrackMage Wordpress Plugin
 * Author URI:        https://savellab.nl/
 * Description:       Wordpress plugin for managing TrackMage integration
 * Version:           1.0.0
 * Author:            Savellab
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       savellab
 * Domain Path:       /languages
 * Network: false
 * GitHub Plugin URI: https://github.com/trackmage/trackmage-wordpress-plugin
 * GitHub Plugin URI: trackmage/trackmage-wordpress-plugin
 */


namespace TrackMage;


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define(__NAMESPACE__ . '\PREFIX', 'trmg');

define(__NAMESPACE__ . '\PLUGIN_VERSION', '1.0.0');

define(__NAMESPACE__ . '\PLUGIN_NAME', 'TrackMage Wordpress Plugin');

define(__NAMESPACE__ . '\PLUGIN_URL', untrailingslashit(plugin_dir_url(__FILE__)));

define(__NAMESPACE__ . '\PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));

define(__NAMESPACE__ . '\PLUGIN_BASENAME', plugin_basename(PLUGIN_DIR) . '/bootstrap.php');

define(__NAMESPACE__ . '\PLUGIN_FOLDER', plugin_basename(PLUGIN_DIR));

define(__NAMESPACE__ . '\PLUGIN_INSTANCE', sanitize_title(crypt($_SERVER['SERVER_NAME'], $salt = PLUGIN_FOLDER)));

define(__NAMESPACE__ . '\PLUGIN_SETTINGS_URL', admin_url('admin.php?page='.PREFIX.'-settings'));

define(__NAMESPACE__ . '\CHANGELOG_COVER', PLUGIN_URL . '/assets/images/plugin-cover.jpg');

define(__NAMESPACE__ . '\AUTO_UPDATE_URL', 'https://savellab.com');

define(__NAMESPACE__ . '\ERROR_PATH', plugin_dir_path(__FILE__) . 'error.log');

define(__NAMESPACE__ . '\TEXT_DOMAIN', 'trackmage-wordpress-plugin');


//init
if(!class_exists( __NAMESPACE__ . '\Core')){
  include_once PLUGIN_DIR . '/includes/class-core.php';
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\Core::on_activation');
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\Core::on_deactivation');

//load translation, make sure this hook runs before all, so we set priority to 1
add_action('init', function(){
  load_plugin_textdomain( 'TEXT_DOMAIN', false, dirname(plugin_basename( __FILE__ )) . '/languages/' );
}, 1);