<?php
/**
 * Created by PhpStorm.
 * User: apple
 * Date: 2/9/19
 * Time: 5:50 PM
 */

namespace TrackMage;

class Shortcodes {

  /**
   * @var null
   */
  protected static $instance = null;

  /**
   * Return an instance of this class.
   *
   * @since     1.0.0
   *
   * @return    object    A single instance of this class.
   */
  public static function instance() {

    // If the single instance hasn't been set, set it now.
    if ( null == self::$instance ) {
      self::$instance = new self;
    }

    return self::$instance;
  }

  /**
   * Fields constructor.
   */
  function __construct () {

    add_shortcode( PREFIX . '-subscribe-button', __CLASS__ . '::subscribe_button' );

  }

  public function subscribe_button($atts) {
    $atts = shortcode_atts([
      'label' => 'Subscribe for notifications',
      'class' => ''
    ], $atts);

    // return Utility::get_tpl( $atts );
  }


}