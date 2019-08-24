<?php
/**
 * Ajax event handlers
 *
 * @class   Ajax
 * @package TrackMage\WordPress
 * @Author  TrackMage
 */

namespace TrackMage\WordPress;

defined('WPINC') || exit;

/**
 * TrackMage\WordPress\Ajax class.
 *
 * All methods in this class should be static.
 *
 * @since 1.0.0
 */
class Ajax {

    /**
     * Init Ajax class.
     *
     * @since 1.0.0
     */
    public static function init() {
        self::addAjaxEvents();
    }

    /**
     * Hook in methods.
     *
     * @since 1.0.0
     */
    public static function addAjaxEvents() {
        $ajaxEventsNopriv = [
            // nopriv ajax events
        ];

        foreach ($ajaxEventsNopriv as $name => $method) {
            add_action('wp_ajax_trackmage_' . $name, [__CLASS__, $method]);
            add_action('wp_ajax_nopriv_trackmage_' . $name, [__CLASS__, $method]);
        }

        $ajaxEvents = [
            'get_order_statuses' => 'getOrderStatuses',
            'get_order_items' => 'getOrderItems',
            'get_view' => 'getView',
            'edit_shipment' => 'editShipment',
            'add_shipment' => 'addShipment',
            'update_shipment' => 'updateShipment',
            'delete_shipment' => 'deleteShipment',
        ];

        foreach ($ajaxEvents as $name => $method) {
            add_action('wp_ajax_trackmage_' . $name, [__CLASS__, $method]);
        }
    }

    /**
     * selectWoo: get the registered order statuses.
     *
     * @since 1.0.0
     * @todo Return plain resutls. Do specific selectWoo stuff in the front-end.
     */
    public static function getOrderStatuses() {
        $statuses = Utils::getOrderStatuses();
        $results = [];

        foreach ($statuses as $slug => $status) {
            array_push($results, [
                'id' => $slug,
                'text' => $status['name'],
            ]);
        }

        wp_send_json($results);
    }

    /**
     * Return the order items of a specific order.
     *
     * @since 1.0.0
     * @return void
     */
    public static function getOrderItems() {
        $orderId = $_POST['orderId'];
        $order = wc_get_order($orderId);

        $results = [];
        
        foreach ($order->get_items() as $id => $item) {
            array_push($results, [
                'id' => $id,
                'name' => $item->get_name(),
            ]);
        }

        wp_send_json($results);
    }

    /**
     * Returns the content of a view file.
     *
     * @since 1.0.0
     * @param $path string The relative view path.
     */
    public static function getView() {
        $path = $_POST['path'];

        try {
            // Get HTML to return.
            ob_start();
            include  TRACKMAGE_VIEWS_DIR . $path;
            $html = ob_get_clean();
        } catch (\Exception $e) {
            wp_send_json_error(['error' => $e->getMessage()]);
        }

        wp_send_json_success([
            'html' => $html,
        ]);
    }

    /**
     * Returns the edit shipment form for a specific shipment.
     *
     * @since 1.0.0
     */
    public static function editShipment() {
        check_ajax_referer('edit-shipment', 'security');

        if (! current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $orderId = $_POST['orderId'];
        $order = wc_get_order($orderId);
        $orderItems = $order->get_items();
        $metaId = $_POST['metaId'];
        $shipment = get_post_meta_by_id($metaId) ? get_post_meta_by_id($metaId)->meta_value : false;

        if ($shipment) {
            try {
                // Get HTML to return.
                ob_start();
                include  TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-edit-shipment.php';
                $html = ob_get_clean();
            } catch (\Exception $e) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }

            wp_send_json_success([
                'html' => $html,
                'tracking_number' => $shipment['tracking_number'],
                'carrier' => $shipment['carrier'],
                'items' => array_map(function($item) use ($orderItems) {
                    return [
                        'name' => $orderItems[$item['order_item_id']]['name'],
                        'order_item_id' => $item['order_item_id'],
                        'qty' => $item['qty'],
                    ];
                }, $shipment['items']),
            ]);
        }

        wp_send_json_error();
    }

    /**
     * Add new shipment to a specific order.
     *
     * @since 1.0.0
     */
    public static function addShipment() {
        check_ajax_referer('add-shipment', 'security');

        if (! current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        // Request data.
        $orderId = $_POST['orderId'];
        $trackingNumber = $_POST['trackingNumber'];
        $carrier = $_POST['carrier'];
        $addAllOrderItems = $_POST['addAllOrderItems'] === 'true' ? true : false;
        $items = $_POST['items'];

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = $order->get_items();
        $_trackmage_order_id = get_post_meta($orderId, '_trackmage_order_id', true);
        $_trackmage_shipment = get_post_meta($orderId, '_trackmage_shipment', false);

        try {
            // Check tracking number.
            if (empty($trackingNumber)) {
                throw new \Exception(__('Tracking number cannot be left empty.', 'trackmage'));
            }

            // Check carrier.
            if (empty($carrier)) {
                throw new \Exception(__('Carrier cannot be left empty.', 'trackmage'));
            }

            // Check if no items added.
            if (! is_array($items) || empty($items)) {
                throw new \Exception(__('No items added.', 'trackmage'));
            }

            if ($addAllOrderItems) {
                if (! empty($_trackmage_shipment)) {
                    throw new \Exception(__('Other shipments have already been created, please delete them first or uncheck “Add all order items”.','trackmage'));
                }

                $items = [];
                array_walk($orderItems, function(&$item, $key) use (&$items) {
                    array_push($items, [
                        'order_item_id' => $key,
                        'qty' => $item->get_quantity(),
                    ]);
                });
            }

            foreach ($items as $item) {
                // Check if any of the selected items no longer exists.
                if (! array_key_exists($item['order_item_id'], $orderItems)) {
                    throw new \Exception(__('Order item does not exist.', 'trackmage'));
                }
            }

            foreach ($items as $item) {
                // Check if any of the items has non-positive quantities.
                if (0 >= $item['qty']) {
                    throw new \Exception(__('Item quantity must be a positive integer.', 'trackmage'));
                }

                // Check the available quantities for each item.
                $totalQty = $orderItems[$item['order_item_id']]->get_quantity();
                $usedQty = 0;
                foreach ($_trackmage_shipment as $shipment) {
                    foreach ($shipment['items'] as $shipmentItem) {
                        if ($item['order_item_id'] === $shipmentItem['order_item_id']) {
                            $usedQty += (int) $shipmentItem['qty'];
                        }
                    }
                }
                $availQty = $totalQty - $usedQty;

                // Check the available quantities of each item.
                if ($availQty < $item['qty']) {
                    throw new \Exception(__('No available quantity.', 'trackmage'));
                }
            }


            // Insert shipment details into the database.
            add_post_meta(
                $orderId,
                '_trackmage_shipment',
                [
                    'id' => '1333-test-guid-from-api',
                    'tracking_number' => $trackingNumber,
                    'carrier' => $carrier,
                    'items' => $items,
                ],
                false
            );

            try {
                // Get HTML to return.
                ob_start();
                include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
                $html = ob_get_clean();
            } catch (\Exception $e) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }

            wp_send_json_success([
                'message' => __('Shipment added successfully!', 'trackmage'),
                'html' => $html,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Update shipment details.
     *
     * @since 1.0.0
     */
    public static function updateShipment() {
        check_ajax_referer('update-shipment', 'security');

        if (! current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        // Request data.
        $orderId = $_POST['orderId'];
        $metaId = $_POST['metaId'];
        $trackingNumber = $_POST['trackingNumber'];
        $carrier = $_POST['carrier'];
        $items = $_POST['items'];

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = $order->get_items();
        $_trackmage_order_id = get_post_meta($orderId, '_trackmage_order_id', true);
        $_trackmage_shipment = Utils::get_post_meta($orderId, '_trackmage_shipment');

        try {
            // Check tracking number.
            if (empty($trackingNumber)) {
                throw new \Exception(__('Tracking number cannot be left empty.', 'trackmage'));
            }

            // Check carrier.
            if (empty($carrier)) {
                throw new \Exception(__('Carrier cannot be left empty.', 'trackmage'));
            }

            // Check if no items added.
            if (! is_array($items) || empty($items)) {
                throw new \Exception(__('No items added.', 'trackmage'));
            }

            foreach ($items as $item) {
                // Check if any of the selected items no longer exists.
                if (! array_key_exists($item['order_item_id'], $orderItems)) {
                    throw new \Exception(__('Order item does not exist.', 'trackmage'));
                }
            }

            foreach ($items as $item) {
                // Check if any of the items has non-positive quantities.
                if (0 >= $item['qty']) {
                    throw new \Exception(__('Item quantity must be a positive integer.', 'trackmage'));
                }

                // Check the available quantities for each item.
                $totalQty = $orderItems[$item['order_item_id']]->get_quantity();
                $usedQty = 0;
                foreach ($_trackmage_shipment as $id => $shipment) {
                    // Exclude the quantities of the items in the current shipment.
                    if ((int) $id === (int) $metaId) {
                        continue;
                    }

                    foreach ($shipment['items'] as $shipmentItem) {
                        if ($item['order_item_id'] === $shipmentItem['order_item_id']) {
                            $usedQty += (int) $shipmentItem['qty'];
                        }
                    }
                }
                $availQty = $totalQty - $usedQty;

                // Check the available quantities of each item.
                if ($availQty < $item['qty']) {
                    throw new \Exception(__('No available quantity.', 'trackmage'));
                }
            }

            // Update shipment details in the database.
            global $wpdb;
            $wpdb->update(
                $wpdb->postmeta,
                ['meta_value' => maybe_serialize(
                    [
                        'id' => '1337-test-guid-from-api',
                        'tracking_number' => $trackingNumber,
                        'carrier' => $carrier,
                        'items' => $items,
                    ]
                )],
                ['meta_id' => $metaId]
            );

            try {
                // Get HTML to return.
                ob_start();
                include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
                $html = ob_get_clean();
            } catch (\Exception $e) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }

            wp_send_json_success([
                'message' => __('Shipment updated successfully!', 'trackmage'),
                'html' => $html,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Delete shipment.
     *
     * @since 1.0.0
     */
    public static function deleteShipment() {
        check_ajax_referer('delete-shipment', 'security');

        if (! current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        // Request data.
        $orderId = $_POST['orderId'];
        $metaId = $_POST['metaId'];

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = $order->get_items();
        $_trackmage_order_id = get_post_meta($orderId, '_trackmage_order_id', true);
        $_trackmage_shipment = Utils::get_post_meta($orderId, '_trackmage_shipment');

        // Delete shipment record from the database.
        delete_metadata_by_mid('post', $metaId);

        try {
            // Get HTML to return.
            ob_start();
            include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
            $html = ob_get_clean();
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        wp_send_json_success([
            'message' => __('Shipment deleted successfully!', 'trackmage'),
            'html' => $html,
        ]);
    }
}
