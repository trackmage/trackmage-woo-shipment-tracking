<?php
/**
 * The Orders class.
 *
 * @package TrackMage\WordPress\Admin
 * @author  TrackMage
 */

namespace TrackMage\WordPress\Admin;

use TrackMage\WordPress\Helper;
use TrackMage\WordPress\Synchronizer;
use TrackMage\WordPress\Plugin;

/**
 * The Orders class.
 *
 * @since 0.1.0
 */
class Orders {
    /**
     * The constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        add_filter( 'wc_order_statuses', [ $this, 'order_statuses' ], PHP_INT_MAX, 1 );
        add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_order_itemmeta' ], 10, 1 );
        add_filter( 'wc_order_is_editable', [ $this, 'custom_status_orders_are_editable' ], 20, 2 );
        add_filter( 'init', [ $this, 'register_order_statuses' ] );

        if (Helper::canSync()) {
            add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 10, 2 );
            add_action( 'save_post', [ $this, 'save_meta_box' ] );
        }
    }

    public function hide_order_itemmeta( $fields ) {
        $fields[] = '_trackmage_order_item_id';
        $fields[] = '_trackmage_hash';
        return $fields;
    }

    /**
     * Add shipment tracking metabox to the order page.
     *
     * @since 0.1.0
     */
    public function add_meta_box(string $post_type, ?\WP_Post $post = null): void {
        if($post_type === 'shop_order' && null !== $post && !in_array($trackmage_order_id = get_post_meta( $post->ID, '_trackmage_order_id', true ), [null, false, ''])) {
            add_meta_box(
                'trackmage-shipment-tracking',
                __( 'TrackMage Shipment Tracking', 'trackmage' ),
                [ $this, 'meta_box_html' ],
                'shop_order',
                'advanced',
                'high'
            );
        }
    }

    /**
     * Save meta box fields.
     *
     * @since 0.1.0
     * @param [int] $post_id Post ID.
     */
    public static function save_meta_box( $post_id ) {
        if ( array_key_exists( 'trackmage_carrier', $_POST ) ) {
            update_post_meta(
                $post_id,
                'trackmage_carrier',
                sanitize_key($_POST['trackmage_carrier'])
            );
        }

        if ( array_key_exists( 'trackmage_tracking_number', $_POST ) ) {
            update_post_meta(
                $post_id,
                'trackmage_tracking_number',
                sanitize_title($_POST['trackmage_tracking_number'])
            );
        }
    }

    /**
     * Render meta box.
     *
     * @todo Move the HTML code to a template file.
     *
     * @since 0.1.0
     * @param [object] $post Post object.
     */
    public function meta_box_html( $post ) {
        $orderId = $post->ID;
        include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
    }

    /**
     * Add/rename order statuses.
     *
     * @since 0.1.0
     *
     * @param array $order_statuses
     * @return array
     */
    public function order_statuses( $order_statuses ) {
        $custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
        // Register custom order statuses added by our plugin.
        return array_merge( $order_statuses, $custom_statuses );
    }

    /**
     * Register custom statuses
     */
    public function register_order_statuses(){
        $custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
        foreach($custom_statuses as $code => $title){
            Helper::registerCustomStatus($code, $title);
        }
    }

    public function custom_status_orders_are_editable( $editable, $order ) {
        $custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
        $editable_custom_statuses = array_map(function($key){return str_replace('wp-','',$key);}, array_keys($custom_statuses));

        if (in_array($order->get_status(), $editable_custom_statuses, true)) {
            $editable = true;
        }

        return $editable;
    }
}
