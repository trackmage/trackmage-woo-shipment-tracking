<?php
/**
 * The Templates class.
 *
 * @class   AjaxTemplates
 * @package TrackMage\WordPress
 * @Author  TrackMage
 */

namespace TrackMage\WordPress;

/**
 * Register custom page templates.
 *
 * @since 1.0.0
 */
class Templates {

    /**
     * The array of templates.
     *
     * @since 1.0.0
     * @var array
     */
    protected static $templates;

    /**
     * Init Templates class.
     *
     * @since 1.0.0
     */
    public static function init() {
        self::$templates = [];

        if (version_compare(floatval(get_bloginfo('version')), '4.7', '<')) {
            add_filter('page_attributes_dropdown_pages_args', [__CLASS__, 'register']);
        } else {
            add_filter('theme_page_templates', [__CLASS__, 'add']);
        }

        add_filter('wp_insert_post_data', [__CLASS__, 'register']);
        add_filter('template_include', [__CLASS__, 'display']);

        // Templates array.
        self::$templates = [
            'tracking-page.php' => __('TrackMage Tracking Page', 'trackmage'),
        ];
    }

    /**
     * Add the template to the page dropdown for v4.7+.
     *
     * @since 1.0.0
     *
     * @param array $tempaltes
     * @return array
     */
    public static function add($postsTemplates) {
        $postsTemplates = array_merge($postsTemplates, self::$templates);
        return $postsTemplates;
    }

    /**
     * Add the templates to the pages cache in order to trick WordPress
     * into thinking the template file exists where it doens't really exist.
     *
     * @since 1.0.0
     *
     * @param array $atts
     * @return array
     */
    public static function register($atts) {
        // Create the key used for the themes cache.
        $cacheKey = 'page_templates-' . md5(get_theme_root() . '/' . get_stylesheet());

        // Retrieve the cache list.
        // If it doesn't exist, or it's empty prepare an array.
        $templates = wp_get_theme()->get_page_templates();
        if (empty($templates)) {
            $templates = [];
        }

        // New cache, therefore remove the old one.
        wp_cache_delete($cacheKey , 'themes');

        // Now add our templates to the list of templates.
        $templates = array_merge($templates, self::$templates);

        // Add the modified cache to allow WordPress to pick it up for listing available templates.
        wp_cache_add($cacheKey, $templates, 'themes', 1800);

        return $atts;
    }

    /**
     * Check if the template is assigned to the page, then display it.
     *
     * @since 1.0.0
     */
    public static function display($template) {
        global $post;

        // Return the search template if we're searching (instead of the template for the first result).
        if (is_search()) {
            return $template;
        }

        // Return template if post is empty.
        if (! $post) {
            return $template;
        }

        // Return default template if we don't have a custom one defined.
        if (! isset(self::$templates[get_post_meta($post->ID, '_wp_page_template', true)])) {
            return $template;
        }

        // Allows filtering of file path.
        $filePath = apply_filters('page_templater_plugin_dir_path', TRACKMAGE_DIR . 'views/');

        $file =  $filePath . get_post_meta($post->ID, '_wp_page_template', true);

        // Just to be safe, we check if the file exist first.
        if (file_exists($file)) {
            return $file;
        }

        // Return template.
        return $template;
    }
}
