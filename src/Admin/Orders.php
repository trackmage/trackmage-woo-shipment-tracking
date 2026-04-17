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
            $hposScreenId = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : '';
            if ($hposScreenId && $hposScreenId !== 'shop_order') {
                add_action( 'add_meta_boxes_' . $hposScreenId, [ $this, 'add_meta_box_hpos' ], 10, 2 );
            }
            // Fires on both classic and HPOS order saves (since WC 3.0).
            add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save_meta_box' ] );
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
        if ($post_type !== 'shop_order' || null === $post || !Helper::canSync()) {
            return;
        }
        $this->register_meta_box($post->ID, 'shop_order');
    }

    /**
     * Register the meta box on the HPOS order edit screen.
     *
     * The HPOS "add_meta_boxes_{screen_id}" hook provides a WC_Order object
     * as its second argument, unlike the classic post-based hook.
     *
     * @param string          $screen_id HPOS screen id.
     * @param \WC_Order|mixed $order     Order instance.
     */
    public function add_meta_box_hpos( $screen_id, $order = null ): void {
        if (!$order instanceof \WC_Order || !Helper::canSync()) {
            return;
        }
        $this->register_meta_box($order->get_id(), $screen_id);
    }

    /**
     * Shared registration for classic and HPOS order screens.
     */
    private function register_meta_box( int $orderId, string $screen_id ): void {
        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }
        $trackmage_order_id = $order->get_meta('_trackmage_order_id', true);
        if (in_array($trackmage_order_id, [null, false, ''], true)) {
            return;
        }
        add_meta_box(
            'trackmage-shipment-tracking',
            __( 'TrackMage Shipment Tracking', 'trackmage' ),
            [ $this, 'meta_box_html' ],
            $screen_id,
            'advanced',
            'high'
        );
    }

    /**
     * Save meta box fields.
     *
     * Hooked on "woocommerce_process_shop_order_meta", which fires on both
     * classic and HPOS order saves. Accepts either a post/order id or an
     * order object depending on the storage mode.
     *
     * @since 0.1.0
     * @param int|\WC_Order $post_or_order_id
     */
    public static function save_meta_box( $post_or_order_id ) {
        $orderId = $post_or_order_id instanceof \WC_Order
            ? $post_or_order_id->get_id()
            : (int) $post_or_order_id;
        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        $changed = false;
        if ( array_key_exists( 'trackmage_carrier', $_POST ) ) {
            $order->update_meta_data(
                'trackmage_carrier',
                sanitize_key($_POST['trackmage_carrier'])
            );
            $changed = true;
        }

        if ( array_key_exists( 'trackmage_tracking_number', $_POST ) ) {
            $order->update_meta_data(
                'trackmage_tracking_number',
                sanitize_title($_POST['trackmage_tracking_number'])
            );
            $changed = true;
        }

        if ($changed) {
            $order->save();
        }
    }

    /**
     * Render meta box.
     *
     * Called with either a WP_Post (classic) or a WC_Order (HPOS) depending
     * on which screen the meta box is rendered on.
     *
     * @since 0.1.0
     * @param \WP_Post|\WC_Order $post_or_order
     */
    public function meta_box_html( $post_or_order ) {
        $orderId = $post_or_order instanceof \WC_Order
            ? $post_or_order->get_id()
            : (int) $post_or_order->ID;
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
