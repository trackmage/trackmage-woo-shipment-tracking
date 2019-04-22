<?php
/**
 * Main class which sets all together
 *
 * @since      1.0.0
 */

namespace TrackMage;


class Core {

  protected static $instance = null;

  /**
   * @return Core|null
   * @throws \Exception
   */
  public static function instance() {

    // If the single instance hasn't been set, set it now.
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }


  /**
   * @since 1.0.0
   * @throws \Exception
   */
  public function __construct(){

    //autoload files from `/autoload`
    spl_autoload_register( __CLASS__ . '::autoload' );

    //include files from `/includes`
    self::includes();

    //enqueue css and js files
    Assets::enqueue();

    // PostTypes::instance();

    // Shortcodes::instance();

    // Requests::ajax();

    // Routes::instance();

    // AutoUpdate::init();

    /**
     * add plugin action and meta links
     */
    self::plugin_links(array(
      'actions' => array(
        PLUGIN_SETTINGS_URL => __('Settings', TEXT_DOMAIN),
        admin_url('admin.php?page=wc-status&tab=logs') => __('Logs', TEXT_DOMAIN),
        // admin_url('plugins.php?action='.PREFIX.'_check_updates') => __('Check for Updates', TEXT_DOMAIN)
      ),
      'meta' => array(
        // '#1' => __('Docs', TEXT_DOMAIN),
        // '#2' => __('Visit website', TEXT_DOMAIN)
      ),
    ));

    //---------------

  }



  /**
   * Include files
   *
   * @since 1.0.0
   * @return void
   */
  public static function includes(){

    include_once PLUGIN_DIR . '/vendor/autoload.php';

    include_once PLUGIN_DIR . '/includes/woocommerce/class-woocommerce.php';

    include_once PLUGIN_DIR . '/includes/option-pages/class-settings-page.php';
  }


  /**
   * Includes all files with "class-" prefix
   *
   * @since 1.0.0
   */
  public static function autoload() {

    $dir = PLUGIN_DIR . '/autoload/class-*.php';
    $paths = glob('{'.$dir.'}', GLOB_BRACE);

    if( is_array($paths) && count($paths) > 0 ){
      foreach( $paths as $file ) {
        if ( file_exists( $file ) ) {
          include_once $file;
        }
      }
    }
  }



  /**
   * Add plugin action and meta links
   *
   * @since 1.0.0
   * @param array $sections
   * @return void
   */
  public static function plugin_links($sections = array()) {

    //actions
    if(isset($sections['actions'])){

      $actions = $sections['actions'];
      $links_hook = is_multisite() ? 'network_admin_plugin_action_links_' : 'plugin_action_links_';

      add_filter($links_hook.PLUGIN_BASENAME, function($links) use ($actions){

        foreach(array_reverse($actions) as $url => $label){
          $link = '<a href="'.$url.'">'.$label.'</a>';
          array_unshift($links, $link);
        }

        return $links;

      });
    }

    //meta row
    if (isset($sections['meta'])) {

      $meta = $sections['meta'];

      add_filter( 'plugin_row_meta', function($links, $file) use ($meta){

        if(PLUGIN_BASENAME == $file){

          foreach($meta as $url => $label){
            $link = '<a href="'.$url.'">'.$label.'</a>';
            array_push($links, $link);
          }
        }

        return $links;

      }, 10, 2 );
    }

  }



  /**
   * Run on plugin activation
   *
   * @since 1.0.0
   * @return void
   */
  public static function on_activation(){

    if (version_compare(phpversion(), '7.0', '<')){
      wp_die(sprintf(
        __('Your server must have at least PHP 7.0! Please upgrade! %sGo back%s', TEXT_DOMAIN),
        '<a href="'.admin_url('plugins.php').'">',
        '</a>'
      ));
    }

    if (version_compare(get_bloginfo('version'), '4.5', '<')){
      wp_die(sprintf(
        __('You need at least Wordpress 4.5! Please upgrade! %sGo back%s', TEXT_DOMAIN),
        '<a href="'.admin_url('plugins.php').'">',
        '</a>'
      ));
    }
  }



  /**
   * Run on plugin deactivation
   *
   * @since 1.0.0
   * @return void
   */
  public static function on_deactivation(){
  }


}
Core::instance();