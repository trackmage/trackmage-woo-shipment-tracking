<?php
/**
 * Plugin settings
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

use TrackMage\WordPress\Helper;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Current tab.
$tab = isset( $_GET['tab'] ) && '' !== $_GET['tab'] ? $_GET['tab'] : 'general';

// Display error messages, if any.
settings_errors();
?>
<div class="wrap trackmage">
    <h1><?php _e( 'TrackMage Settings', 'trackmage' ); ?></h1>
    <nav class="nav-tab-wrapper trackmage-nav-tab-wrapper">
        <a href="<?php echo admin_url( 'admin.php?page=trackmage-settings&amp;tab=general' ); ?>" class="nav-tab<?php Helper::add_css_class( 'general' === $tab || '' === $tab, 'nav-tab-active', true, true ); ?>"><?php _e( 'General', 'trackmage' ); ?></a>
        <a href="<?php echo admin_url( 'admin.php?page=trackmage-settings&amp;tab=advanced' ); ?>" class="nav-tab<?php Helper::add_css_class( 'advanced' === $tab, 'nav-tab-active', true, true ); ?>"><?php _e( 'Advanced', 'trackmage' ); ?></a>
    </nav>
    <div class="postbox">
        <div class="inside tab tab-<?php echo $tab; ?>" id="trackmage-settings-<?php echo $tab; ?>">
            <?php include( TRACKMAGE_VIEWS_DIR . "admin-page-settings-{$tab}.php" ); ?>
        </div>
    </div>
</div>