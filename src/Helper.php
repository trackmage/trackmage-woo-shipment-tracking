<?php
/**
 * Utilities and helper functions.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

use TrackMage\Client\TrackMageClient;
use TrackMage\Client\Swagger\ApiException;

/**
 * Static functions that can be called without instantiation.
 *
 * @since   0.1.0
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */
class Helper {

    /**
     * Check the validity of API credentials
     *
     * @param string $client_id     Client ID (default: '').
     * @param string $client_secret Client secret (default: '').
     *
     * @return int 0 if invalid, 1 if valid or 2 otherwise.
     */
    public static function check_credentials( $client_id = '', $client_secret = '' ) {
        $client_id = ! empty( $client_id ) ? $client_id : get_option( 'trackmage_client_id', '' );
        $client_secret = ! empty( $client_secret ) ? $client_secret : get_option( 'trackmage_client_secret', '' );

        try {
            $client = new TrackMageClient( $client_id, $client_secret );
            $client->setHost('https://api.stage.trackmage.com');
            $workspaces = $client->getWorkspaceApi()->getWorkspaceCollection();
        } catch( ApiException $e ) {
            if ( 'Authorization error' === $e->getMessage() ) {
                return 0;
            }

            return 2;
        }
        
        return 1;
    }

    /**
     * Returns a list of the workspaces created by the current user.
     *
     * @since 0.1.0
     * @return array Of workspaces, or an empty array if no workspaces found.
     */
    public static function get_workspaces() {
        $workspaces = [];

        try {
            $client = Plugin::get_client();
            $result = $client->getWorkspaceApi()->getWorkspaceCollection();

            foreach ( $result as $workspace ) {
                array_push( $workspaces, [
                    'id'    => $workspace->getId(),
                    'title' => $workspace->getTitle(),
                ] );
            }
        } catch( ApiException $e ) {
            // Do nothing. We will return an empty array.
        }

        return $workspaces;
    }

    /**
     * Returns carriers.
     *
     * @since 0.1.0
     * @return array List of carriers.
     */
    public static function get_shipment_carriers() {
        $carriers = get_transient( 'trackmage_carriers' );

        if ( false === $carriers ) {
            try {
                $client = Plugin::get_client();
                $result = $client->getCarrierApi()->getCarrierCollection();

                $carriers = [];
                foreach ( $result as $carrier ) {
                    array_push( $carriers, [
                        'code' => $carrier->getCode(),
                        'name' => $carrier->getName(),
                    ] );
                }

                set_transient( 'trackmage_carriers', $carriers, 0 );
            } catch( ApiException $e ) {
                $carriers = [];
            }
        }

        return $carriers;
    }

    /**
     * Returns the available aliases.
     *
     * @since 0.1.0
     * @return array List of aliases.
     */
    public static function get_aliases() {
        return [
            'delivered' => __( 'Delivered', 'trackmage' ),
            'shipped'   => __( 'Shipped', 'trackamge' ),
        ];
    }

    public static function getOrderStatuses() {
        $statuses = [];
        $get_statuses = wc_get_order_statuses();

        $custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
        $aliases = get_option( 'trackmage_order_status_aliases', [] );

        foreach ( $get_statuses as $slug => $name ) {
            $statuses[ $slug ] = [
                'name' => $name,
                'is_custom' => array_key_exists( $slug, $custom_statuses ),
                'alias' => array_key_exists( $slug, $aliases ) ? $aliases[ $slug ] : '',
            ];
        }

        return $statuses;
    }

    /**
     * Get order status details by slug.
     *
     * @since 0.1.0
     *
     * @param $slug Status slug.
     * @return array|null Status details or null if not found.
     */
    public static function get_order_status_by_slug( $slug ) {
        $statuses = self::get_order_statuses();
        return isset( $statuses[ $slug ] ) ? $statuses[ $slug ] : null;
    }

    /**
     * Returns all the sent HTTP hearders.
     *
     * @since 0.1.0
     * @return array Array of headers.
     */
    public static function getallheaders() {
        $headers = array();

        foreach ( $_SERVER as $name => $value ) { 
            if ( substr( $name, 0, 5 ) == 'HTTP_' ) { 
                $headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value; 
            }
        }

        return $headers;
    }

    /**
     * Returns endpoint URL.
     *
     * @since 0.1.0
     * @return string Endpoint URL.
     */
    public static function get_endpoint() {
        return get_site_url( null, '/?trackmage=callback' );
    }

    /**
     * Prints out CSS classes if a condition is met.
     *
     * @since 0.1.0
     *
     * @param boolean $condition     The condition to check against (default: false).
     * @param string  $class         Classes to print out (default: '').
     * @param bool    $leading_space Whether to add a leading space (default: false).
     * @param bool    $echo          Whether to echo or return the output (default: false).
     */
    public static function add_css_class( $condition = false, $class = '', $leading_space = false, $echo = false ) {
        if ( $condition ) {
            $output = ( $leading_space ? ' ' : '' ) . $class;
            
            if ( $echo ) {
                echo $output;
            } else {
                return  $output;
            }
        }
    }

    /**
     * Generates HTML tag attributes if their value is not empty.
     *
     * The leading and trailing spaces will not be printed out if all attributes have empty values.
     *
     * @since 0.1.0
     *
     * @param array $atts           Attributes and their values.
     * @param bool  $leading_space  Whether to add a leading space (default: false).
     * @param bool  $trailing_space Whether to add a trailing space (default: false).
     * @param bool  $echo           Whether to echo or return the output (default: false).
     * @return string Tag attributes.
     */
    public static function generate_html_tag_atts( $atts, $leading_space = false, $trailing_space = false, $echo = false ) {
        $output =  '';
        $atts_count = 0;

        foreach ( $atts as $attr => $value ) {
            if ( ! empty( $value ) ) {
                $atts_count++;
                $output .=  $attr . '="' . $value . '"';
            }
        }

        if ( 0 < $atts_count ) {
            $output = ( $leading_space ? ' ' : '' ) . $output . ( $trailing_space ? ' ' : '' );

            if ( $echo ) {
                echo $output;
            } else {
                return $output;
            }
        }
    }

    /**
     * Generates inline style string.
     *
     * @since 0.1.0
     *
     * @param array $props Array of CSS properties and their values.
     * @param bool  $echo  Whether to echo or return the output (default: false).
     * @return string Inline style string.
     */
    public static function generate_inline_style( $props, $echo = false ) {
        $output = '';
        foreach( $props as $prop => $value ) {
            if ( ! empty( $value ) ) {
                $output .= "{$prop}:{$value};";
            }
        }

        if ( $echo ) {
            echo $output;
        } else {
            return $output;
        }
    }

    /**
     * Return all screen ids of the pages created or modified by the plugin.
     *
     * @since 1.0.0
     * @return array
     */
    public static function getScreenIds() {
        $screenIds = [
            'toplevel_page_trackmage-settings',
            'trackmage_page_trackmage-status-manager',
            'shop_order'
        ];

        return $screenIds;
    }

    /**
     * Retrieves a post meta field for the given post ID.
     *
     * This helper function works like the native `get_post_meta()`, but
     * also returns the post meta ID.
     *
     * @param int    $post_id    The post ID.
     * @param string $meta_key   The meta key.
     * @param string $meta_value Optional. The meta value (default: '').
     *
     * @since 1.0.0
     *
     * @return array|bool Array of meta fields or false if no results found.
     */
    public static function get_post_meta( $post_id, $meta_key, $meta_value = '' ) {
        global $wpdb;
        if ( '' !== $meta_value ) {
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s AND meta_value = %s", $post_id, $meta_key, $meta_value ) );
        } else {
            $results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $meta_key ) );
        }
        $post_meta = [];
        foreach ( $results as $pm ) {
            $post_meta[ $pm->meta_id ] = maybe_unserialize( $pm->meta_value );
        }
        return $post_meta;
    }
}