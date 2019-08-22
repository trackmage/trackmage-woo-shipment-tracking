<?php
/**
 * Load public assets
 *
 * @class   Assets
 * @package TrackMage\WordPress
 * @Author  TrackMage
 */

namespace TrackMage\WordPress;

defined('WPINC') || exit;

/**
 * TrackMage\WordPress\Assets class.
 *
 * @since 1.0.0
 */
class Assets {
    /**
     * Init the TrackMage\WordPress\Assets class.
     *
     * @since 1.0.0
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueStyles']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueScripts']);
    }

    /**
     * Enqueue frontend styles.
     *
     * @since 1.0.0
     */
    public static function enqueueStyles() {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        // Register styles.
        wp_register_style('trackmage', TRACKMAGE_URL . 'assets/dist/css/frontend/main' . $suffix . '.css', [], TRACKMAGE_VERSION, 'all');

        // Main styles.
        wp_enqueue_style('trackmage');

    }

    /**
     * Enqueue frontend scripts.
     *
     * @since 1.0.0
     */
    public static function enqueueScripts() {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        // Register public scripts.
        wp_register_script('trackmage', TRACKMAGE_URL . 'assets/dist/js/frontend/main' . $suffix . '.js', ['jquery'], TRACKMAGE_VERSION, true);
        
        // Enqueue public scripts.
        wp_enqueue_script('trackmage');
        wp_localize_script('trackmage', 'trackmage', [
            'urls' => [
                'ajax' => admin_url('admin-ajax.php'),
            ],
        ]);
    }
}
