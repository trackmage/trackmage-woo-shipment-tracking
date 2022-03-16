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
$tab = isset( $_GET['tab'] ) && !empty($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

// Display error messages, if any.
settings_errors();
?>
<div class="wrap trackmage">
    <h1><?php _e( 'TrackMage Settings', 'trackmage' ); ?></h1>
    <div class="container-fluid p-0 m-0">
        <div class="row">
            <div class="col-12 col-md-9">
                <?php if(false):?>
                <nav class="nav-tab-wrapper trackmage-nav-tab-wrapper">
                    <a href="<?php echo admin_url( 'admin.php?page=trackmage-settings&amp;tab=general' ); ?>" class="nav-tab<?php Helper::add_css_class( 'general' === $tab || '' === $tab, 'nav-tab-active', true, true ); ?>"><?php _e( 'General', 'trackmage' ); ?></a>
                    <?php /*  ADVANCED tab disabled for now ?>
                    <a href="<?php echo admin_url( 'admin.php?page=trackmage-settings&amp;tab=advanced' ); ?>" class="nav-tab<?php Helper::add_css_class( 'advanced' === $tab, 'nav-tab-active', true, true ); ?>"><?php _e( 'Advanced', 'trackmage' ); ?></a>
                    <?php */?>
                </nav>
                <?php endif; ?>
                <div class="postbox">
                    <div class="inside tab tab-<?php echo $tab; ?>" id="trackmage-settings-<?php echo $tab; ?>">
                        <?php include( TRACKMAGE_VIEWS_DIR . "admin-page-settings-{$tab}.php" ); ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card m-0 p-2 p-xl-4">
                    <img class="card-img-top" src="<?php echo TRACKMAGE_URL . 'assets/dist/images/trackmage_logo_big.png' ?>" alt="TrackMage">
                    <div class="card-body p-0 text-center">
                        <h5 class="card-title">How are we doing?</h5>
                        <h6 class="card-subtitle mb-2 text-muted">Whether you love us or feel we could be doing better, we want to know!</h6>
                        <p class="card-text">
                            <span class="d-inline-flex w-100 justify-content-between">
                                <img class="img-fluid" width="18%" src="<?php echo TRACKMAGE_URL . 'assets/dist/images/green-star.png' ?>">
                                <img class="img-fluid" width="18%" src="<?php echo TRACKMAGE_URL . 'assets/dist/images/green-star.png' ?>">
                                <img class="img-fluid" width="18%" src="<?php echo TRACKMAGE_URL . 'assets/dist/images/green-star.png' ?>">
                                <img class="img-fluid" width="18%" src="<?php echo TRACKMAGE_URL . 'assets/dist/images/green-star.png' ?>">
                                <img class="img-fluid" width="18%" src="<?php echo TRACKMAGE_URL . 'assets/dist/images/green-star.png' ?>">
                            </span>
                            <a role="link" class="action-button" href="https://wordpress.org/support/plugin/trackmage-woo-shipment-tracking/reviews/?rate=5#new-post" target="_blank">Love It! <span class="dashicons dashicons-thumbs-up"></span></a>
                            <a role="link" class="action-button" href="https://wordpress.org/support/plugin/trackmage-woo-shipment-tracking/" target="_blank">Needs work</a>
                        </p>
                    </div>
                </div>
                <div class="card p-0">
                    <img class="card-img-top" style="margin-bottom: -20px" src="<?php echo TRACKMAGE_URL . 'assets/dist/images/tm-support.png' ?>" alt="TrackMage">
                    <div class="card-body m-0 py-0">
                        <h5>Having Issues or Questions?</h5>
                        <div class="card-text">
                            <ul>
                                <li><a target="_blank" title="Create new topic if you didn't find issue" href="https://wordpress.org/support/plugin/trackmage-woo-shipment-tracking/">View tickets or create new</a></li>
                                <li><a target="_blank" title="TrackMage Knowledge Base" href="https://help.trackmage.com/en">TrackMage Knowledge Base</a></li>
                                <li><a target="_blank" title="" href="https://trackmage.com/blog/">TrackMage Blog</a></li>
                                <li><a target="_blank" title="" href="https://www.facebook.com/groups/trackmage/">Join TrackMage Community</a></li>
                                <li><a title="Write Us a letter" href="mailto:support@trackmage.com">Contact Us Directly</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
