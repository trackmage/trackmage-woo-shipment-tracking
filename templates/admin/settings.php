<?php
/**
 * Plugin settings
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

use TrackMage\WordPress\Utils;

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
	<form method="post" action="options.php" id="trackmage-settings-general">
		<nav class="nav-tab-wrapper trackmage-nav-tab-wrapper">
			<a href="<?php echo admin_url( 'admin.php?page=trackmage&amp;tab=general' ); ?>" class="nav-tab<?php Utils::add_css_class( 'general' === $tab || '' === $tab, 'nav-tab-active', true, true ); ?>"><?php _e( 'General', 'trackmage' ); ?></a>
			<a href="<?php echo admin_url( 'admin.php?page=trackmage&amp;tab=statuses' ); ?>" class="nav-tab<?php Utils::add_css_class( 'statuses' === $tab, 'nav-tab-active', true, true ); ?>"><?php _e( 'Statuses', 'trackmage' ); ?></a>
		</nav>
		<div class="postbox">
			<div class="inside tab tab-<?php echo $tab; ?>">
				<?php include( TRACKMAGE_DIR . "templates/admin/settings-{$tab}.php" ); ?>
			</div>
		</div>
	</form>
</div>