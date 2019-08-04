<?php
/**
 * The Templates class.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

/**
 * Register custom page templates.
 *
 * @since 0.1.0
 */
class Templates {

	/**
	 * The array of templates.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $templates;

	/**
	 * The constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {

		$this->templates = array();

		if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {
			add_filter( 'page_attributes_dropdown_pages_args', [ $this, 'register_templates' ] );
		} else {
			add_filter( 'theme_page_templates', [ $this, 'add_new_template' ] );
		}

		add_filter( 'wp_insert_post_data', [ $this, 'register_templates' ] );
		add_filter( 'template_include', [ $this, 'view_template' ] );

		// Templates array.
		$this->templates = [
			'tracking-page.php' => __( 'TrackMage Tracking Page', 'trackmage' ),
		];
	}

	/**
	 * Add the template to the page dropdown for v4.7+.
	 *
	 * @since 0.1.0
	 *
	 * @param array $post_tempaltes
	 * @return array
	 */
	public function add_new_template( $posts_templates ) {
		$posts_templates = array_merge( $posts_templates, $this->templates );
		return $posts_templates;
	}

	/**
	 * Adds the templates to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doens't really exist.
	 *
	 * @since 0.1.0
	 *
	 * @param array $atts
	 * @return array
	 */
	public function register_templates( $atts ) {
		// Create the key used for the themes cache.
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list.
		// If it doesn't exist, or it's empty prepare an array.
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		}

		// New cache, therefore remove the old one.
		wp_cache_delete( $cache_key , 'themes');

		// Now add our templates to the list of templates.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing available templates.
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;
	}

	/**
	 * Checks if the template is assigned to the page.
	 * 
	 * @since 0.1.0
	 */
	public function view_template( $template ) {
		global $post;

		// Return the search template if we're searching (instead of the template for the first result).
		if ( is_search() ) {
			return $template;
		}

		// Return template if post is empty.
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined.
		if ( ! isset( $this->templates[ get_post_meta( $post->ID, '_wp_page_template', true ) ] ) ) {
			return $template;
		}

		// Allows filtering of file path.
		$filepath = apply_filters( 'page_templater_plugin_dir_path', TRACKMAGE_DIR . 'templates/' );

		$file =  $filepath . get_post_meta( $post->ID, '_wp_page_template', true );

		// Just to be safe, we check if the file exist first.
		if ( file_exists( $file ) ) {
			return $file;
		}

		// Return template.
		return $template;
	}
}
