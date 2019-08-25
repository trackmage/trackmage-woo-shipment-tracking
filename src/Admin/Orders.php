<?php
/**
 * The Orders class.
 *
 * @package TrackMage\WordPress\Admin
 * @author  TrackMage
 */

namespace TrackMage\WordPress\Admin;

use TrackMage\WordPress\Synchronizer;
use TrackMage\WordPress\Plugin;

/**
 * The Orders class.
 *
 * @since 0.1.0
 */
class Orders {

    private $synchronizer;

    /**
     * The constructor.
     *
     * @since 0.1.0
     */
    public function __construct(Synchronizer $synchronizer) {
        $this->synchronizer = $synchronizer;
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box'] );
        add_action( 'save_post', [ $this, 'save_meta_box'] );
        add_filter( 'wc_order_statuses', [ $this, 'order_statuses' ], 999999, 1 );
        add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_order_itemmeta' ], 10, 1 );
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
    public function add_meta_box() {
        add_meta_box(
            'trackmage-shipment-tracking',
            __( 'TrackMage Shipment Tracking', 'trackmage' ),
            [ $this, 'meta_box_html' ],
            'shop_order',
            'advanced',
            'high'
        );
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
                $_POST['trackmage_carrier']
            );
        }

        if ( array_key_exists( 'trackmage_tracking_number', $_POST ) ) {
            update_post_meta(
                $post_id,
                'trackmage_tracking_number',
                $_POST['trackmage_tracking_number']
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
        $modified_statuses = get_option( 'trackmage_modified_order_statuses', [] );

        // Register custom order statuses added by our plugin.
        $order_statuses = array_merge( $order_statuses, $custom_statuses );

        // Update the registered statuses.
        foreach ( $order_statuses as $key => $name ) {
            if ( array_key_exists( $key, $modified_statuses ) ) {
                $order_statuses[ $key ] = __( $modified_statuses[ $key ], 'trackmage' );
            }
        }

        return $order_statuses;
    }
}
