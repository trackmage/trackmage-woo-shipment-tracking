<?php
/**
 * This creates settings area in payments gateway section
 *
 * @since      1.0.0
 */

namespace TrackMage;

class WC_Settings {

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

}

WC_Settings::instance();