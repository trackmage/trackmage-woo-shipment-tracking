<?php
/**
 * This is responsible for processing AJAX or other requests
 *
 * @since 1.0.0
 */

namespace TrackMage;


//prevent direct access data leaks
defined( 'ABSPATH' ) || exit;


class Routes {

  private static $root = '';


  function __construct() {

    self::$root = get_bloginfo( 'url' ) . '/wp-json/' . PREFIX . '/v1/';

    add_action( 'rest_api_init', __CLASS__ . '::register_routes' );

  }

  public static function get_path( $endpoint ) {
    return self::$root . trim($endpoint, '/');
  }

  public static function register_routes() {

    register_rest_route( PREFIX . '/v1', '/subscription', array(
      'methods' => \WP_REST_Server::ALLMETHODS,
      'callback' => __CLASS__ . '::update_subscription',
    ));

  }

  /**
   * @param $request
   * @return object
   */
  public static function update_subscription( $request ) {

    $parameters = $request->get_params();

    if ( !isset( $parameters['authToken'] ) ) :
      return new \WP_Error( 'missed_authToken', 'Missed value authToken', array( 'status' => 400 ) );
    endif;

    return new \WP_REST_Response( [
      '$parameters' => $parameters,
    ], 200 );

  }

  /**
   * The instance of this class
   *
   * @since 1.0.0
   * @var null|object
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

}