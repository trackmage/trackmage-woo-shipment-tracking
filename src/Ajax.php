<?php
/**
 * Ajax event handlers
 *
 * @class   Ajax
 * @package TrackMage\WordPress
 * @Author  TrackMage
 */

namespace TrackMage\WordPress;

use GuzzleHttp\Exception\ClientException;
use TrackMage\Client\TrackMageClient;

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
            'get_bg_stats' => 'getBgStats',
            'get_order_items' => 'getOrderItems',
            'get_view' => 'getView',
            'edit_shipment' => 'editShipment',
            'add_shipment' => 'addShipment',
            'update_shipment' => 'updateShipment',
            'merge_shipments' => 'mergeShipments',
            'delete_shipment' => 'deleteShipment',
            'add_status' => 'addStatus',
            'update_status' => 'updateStatus',
            'delete_status' => 'deleteStatus',
        ];

        foreach ($ajaxEvents as $name => $method) {
            add_action('wp_ajax_trackmage_' . $name, [__CLASS__, $method]);
        }
    }

    public static function getBgStats() {
        wp_send_json([
            'ordersCount' => Helper::getBgOrdersAmountToProcess(),
        ]);
    }

    /**
     * selectWoo: get the registered order statuses.
     *
     * @since 1.0.0
     * @todo Return plain resutls. Do specific selectWoo stuff in the front-end.
     */
    public static function getOrderStatuses() {
        $statuses = Helper::getOrderStatuses();
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
        if( !isset($_POST['orderId']) || ( isset( $_POST['orderId'] ) && ! is_numeric( $_POST['orderId'] ) ) ) {
            wp_send_json([]);
        }
        $orderId = absint($_POST['orderId']);
        $order = wc_get_order($orderId);
        if(!$order) {
            wp_send_json([]);
        }
        $results = [];

        foreach (Helper::getOrderItems($order) as $id => $item) {
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
        if(!isset($_POST['path']) || (isset($_POST['path']) && empty($_POST['path']))){
            wp_send_json_error(['error' => esc_attr__('View file was not found','trackmage')]);
        }
        $path = sanitize_file_name( $_POST['path'] );
        $html = '';
        try {
            // Get HTML to return.
            ob_start();
            include  TRACKMAGE_VIEWS_DIR . 'meta-boxes/' . $path;
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

        if( ! isset( $_POST['orderId'], $_POST['id'] ) || ( isset( $_POST['orderId'] ) && ! is_numeric( $_POST['orderId'] ) ) || ( isset( $_POST['id'] ) && empty( $_POST['id'] ) ) ) {
            wp_send_json_error();
        }
        $orderId = absint($_POST['orderId']);
        $order = wc_get_order($orderId);
        $orderItems = Helper::getOrderItems($order);
        $shipmentId = sanitize_key($_POST['id']);
        $shipment = Helper::geShipmentWithJoinedItems($shipmentId, $orderId);

        if ($shipment) {
            $html = '';
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
                'tracking_number' => esc_textarea($shipment['tracking_number']),
                'carrier' => esc_textarea($shipment['carrier']),
                'items' => array_map(function($item) use ($orderItems) {
                    return array_merge($item, ['name' => $orderItems[$item['order_item_id']]['name']]);
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
        if( !isset($_POST['orderId']) || ( isset( $_POST['orderId'] ) && ! is_numeric( $_POST['orderId'] ) ) ) {
            wp_send_json([]);
        }
        $orderId = absint($_POST['orderId']);
        $addAllOrderItems = isset($_POST['addAllOrderItems']) && $_POST['addAllOrderItems'] === 'true';

        $trackingNumber = isset($_POST['trackingNumber']) ? sanitize_title($_POST['trackingNumber']) : '';
        $carrier = isset($_POST['carrier']) ? sanitize_key($_POST['carrier']) : '';
        $shipmentItems = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

        $shipmentItems = array_map(function($item) {
            $sanitizedItem = [];
            $sanitizedItem['id'] = isset($item['id']) ? sanitize_key($item['id']) : null;
            $sanitizedItem['order_item_id'] = isset($item['order_item_id']) && is_numeric($item['order_item_id']) ? absint($item['order_item_id']) : null;
            $sanitizedItem['qty'] = isset($item['qty']) && is_numeric($item['qty']) ? absint($item['qty']) : 0;
            return $sanitizedItem;
        }, $shipmentItems);

        $shipment = [
            'order_id' => $orderId,
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
            'items' => array_filter($shipmentItems, function($item){ return null !== $item['order_item_id'] && $item['qty'] > 0;})
        ];

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = Helper::getOrderItems($order);
        $orderNotes          = [];

        try {
            if ($addAllOrderItems) {
                $shipment['items'] = array_map(function(\WC_Order_Item $item) {
                    return [
                        'order_item_id' => $item->get_id(),
                        'qty' => $item->get_quantity(),
                    ];
                }, $orderItems);
            } else {
                $mergedItems = [];
                foreach ($shipment['items'] as $item) {
                    if(isset($item['qty']) && !empty($item['qty'])) {
                        $orderItemId = absint($item['order_item_id']);
                        if ( isset( $mergedItems[ $orderItemId ] ) ) {
                            foreach ( $item as $key => $value ) {
                                if ( $key === 'qty' ) {
                                    $mergedItems[ $orderItemId ]['qty'] += (int) $value;
                                } elseif ( ! empty( $value ) ) {
                                    $mergedItems[ $orderItemId ][ $key ] = $value;
                                }
                            }
                        } else {
                            $mergedItems[ $orderItemId ]        = $item;
                            $mergedItems[ $orderItemId ]['qty'] = (int) $mergedItems[ $orderItemId ]['qty'];
                        }
                    }
                }
                $shipment['items'] = array_values($mergedItems);
            }

            $synchronizer = Plugin::instance()->getSynchronizer();
            $synchronizer->syncOrder($orderId, true);

            $shipment = Helper::saveShipmentWithJoinedItems($shipment);

            $orderNotes = array_map(function(\WC_Order_Item $item) {
                return $item->get_name();
            }, $orderItems);
            /* translators: %s item name. */
            $order->add_order_note( sprintf( __( 'Added shipment %s for order items: %s', 'trackmage' ), $shipment['tracking_number'], implode( ', ', $orderNotes ) ), false, true );

            // Get HTML to return.
            ob_start();
            include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
            $html = ob_get_clean();

            ob_start();
            $notes = wc_get_order_notes( array( 'order_id' => $orderId ) );
            include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-notes.php';
            $notes_html = ob_get_clean();

            wp_send_json_success([
                'message' => __('Shipment added successfully!', 'trackmage'),
                'html' => $html,
                'notes' => $notes_html
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
        if( !isset($_POST['orderId'], $_POST['id']) || (!is_numeric($_POST['orderId'])) || empty($_POST['id'])) {
            wp_send_json([]);
        }
        $orderId = absint($_POST['orderId']);

        $trackingNumber = isset($_POST['trackingNumber']) ? sanitize_title($_POST['trackingNumber']) : '';
        $carrier = isset($_POST['carrier']) ? sanitize_key($_POST['carrier']) : '';
        $shipmentItems = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];

        $shipmentItems = array_map(function($item) {
            $sanitizedItem = [];
            $sanitizedItem['id'] = isset($item['id']) ? sanitize_key($item['id']) : null;
            $sanitizedItem['order_item_id'] = isset($item['order_item_id']) && is_numeric($item['order_item_id']) ? absint($item['order_item_id']) : null;
            $sanitizedItem['qty'] = isset($item['qty']) && is_numeric($item['qty']) ? absint($item['qty']) : 0;
            return $sanitizedItem;
        }, $shipmentItems);

        $shipmentId = sanitize_key($_POST['id']);

        $shipment = [
            'id' => $shipmentId,
            'order_id' => $orderId,
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier,
            'items' => array_filter($shipmentItems, function($item){ return null !== $item['order_item_id'] && $item['qty'] > 0;})
        ];

        // Order data.
        $order = wc_get_order($orderId);
        try {
            $orderItems = Helper::getOrderItems($order);
            $mergedItems = [];
            foreach ($shipment['items'] as $item) {
                $orderItemId = absint($item['order_item_id']);
                if ( isset( $mergedItems[ $orderItemId ] ) ) {
                    foreach ( $item as $key => $value ) {
                        if ( $key === 'qty' ) {
                            $mergedItems[ $orderItemId ]['qty'] += (int) $value;
                        } elseif ( ! empty( $value ) ) {
                            $mergedItems[ $orderItemId ][ $key ] = $value;
                        }
                    }
                } else {
                    $mergedItems[ $orderItemId ]        = $item;
                    $mergedItems[ $orderItemId ]['qty'] = (int) $mergedItems[ $orderItemId ]['qty'];
                }
            }
            $shipment['items'] = array_values($mergedItems);

            $synchronizer = Plugin::instance()->getSynchronizer();
            $synchronizer->syncOrder($orderId, true);

            // Update shipment details in the database.
            $shipment = Helper::saveShipmentWithJoinedItems($shipment);
            $order->add_order_note( sprintf( __( 'Shipment %s was updated', 'trackmage' ), $shipment['tracking_number']), false, true );

            // Get HTML to return.
            ob_start();
            include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
            $html = ob_get_clean();

            ob_start();
            $notes = wc_get_order_notes( array( 'order_id' => $orderId ) );
            include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-notes.php';
            $notes_html = ob_get_clean();

            wp_send_json_success([
                'message' => __('Shipment updated successfully!', 'trackmage'),
                'html' => $html,
                'notes' => $notes_html
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage(), 'shipmentId' => $shipmentId, 'trackingNumber' => $trackingNumber]);
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
        if( !isset($_POST['orderId'], $_POST['id']) || (!is_numeric($_POST['orderId'])) || empty($_POST['id'])) {
            wp_send_json([]);
        }

        $orderId = absint($_POST['orderId']);
        $shipmentId = sanitize_key($_POST['id']);

        // Delete shipment record from the database.
        Helper::deleteShipment($shipmentId);

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = Helper::getOrderItems($order);

        /** @var \WP_User $user */
        $user = wp_get_current_user();
        $userName = null !== $user ? $user->user_login : 'unknown';

        $order->add_order_note( sprintf( __( 'Shipment %s was deleted by %s.', 'trackmage' ), $shipmentId, $userName), false, true );

        try {
            // Get HTML to return.
            ob_start();
            include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
            $html = ob_get_clean();

            ob_start();
            $notes = wc_get_order_notes( array( 'order_id' => $orderId ) );
            include WC()->plugin_path().'/includes/admin/meta-boxes/views/html-order-notes.php';
            $notes_html = ob_get_clean();

            wp_send_json_success([
                'message' => __('Shipment deleted successfully!', 'trackmage'),
                'html' => $html,
                'notes' => $notes_html
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Status manager: update status.
     *
     * @since 1.0.0
     * @todo Refactor error handling using Exceptions instead of $errors array.
     */
    public function updateStatus() {
        check_ajax_referer('update-status', 'security');

        if (! current_user_can('manage_woocommerce')) {
            wp_die(-1);
        }

        $name = isset($_POST['name']) ? sanitize_title($_POST['name']) : '';
        $current_slug = isset($_POST['currentSlug']) ? sanitize_key($_POST['currentSlug']) : '';
        $slug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        $alias = isset($_POST['alias']) ? sanitize_title($_POST['alias']) : '';
        $is_custom = isset($_POST['isCustom']) && '1' === $_POST['isCustom'];

        $custom_statuses = get_option('trackmage_custom_order_statuses', []);
        $modified_statuses = get_option('trackmage_modified_order_statuses', []);
        $status_aliases = get_option('trackmage_order_status_aliases', []);
        $aliases = Helper::get_aliases();
        $get_statuses = Helper::getOrderStatuses();


        // Errors array.
        $errors = [];

        if (empty ($name)) {
            $errors[] = __('Status name cannot be empty.', 'trackmage');
        }

        if (empty ($slug)) {
            $errors[] = __('Status slug cannot be empty.', 'trackmage');
        }

        if ($current_slug !== $slug && isset($get_statuses[$slug])) {
            $errors[] = sprintf(__('The slug <em>“%1$s”</em> is already used by another status.', 'trackmage'), $slug);
        }

        if ($is_custom && $current_slug !== $slug) {
            unset($custom_statuses[$current_slug]);
        }

        if (! $is_custom && $current_slug !== $slug) {
            $errors[] = __('The slug of core statuses and statuses created by other plugins and themes cannot be changed.', 'trackmage');
        }

        if ($is_custom) {
            $custom_statuses[$slug] = __($name, 'trackmage');
        } else {
            $modified_statuses[$slug] = __($name, 'trackmage');
        }

        if (! empty($alias)
            && in_array($alias, $status_aliases, true)
            && isset($status_aliases[$current_slug])
            && $alias !== $status_aliases[$current_slug])
        {
            $errors[] = sprintf(__('The alias <em>“%1$s”</em> is already assigned to another status.', 'trackmage'), $aliases[$alias]);
        } else {
            $status_aliases[$slug] = $alias;
        }

        if (! empty($errors)) {
            wp_send_json_error([
                'message' => $errors,
            ]);
        }

        update_option('trackmage_custom_order_statuses', $custom_statuses);
        update_option('trackmage_modified_order_statuses', $modified_statuses);
        update_option('trackmage_order_status_aliases', $status_aliases);

        $used_aliases = Helper::get_used_aliases();

        wp_send_json_success([
            'message' => __('Status updated successfully!', 'trackmage'),
            'result' => [
                'name'  => $name,
                'slug'  => $slug,
                'alias' => $alias,
                'used'  => $used_aliases
            ]
        ]);
    }

    /**
     * Status manager: add new status.
     *
     * @since 1.0.0
     * @todo Refactor error handling using Exceptions instead of $errors array.
     */
    public function addStatus() {
        check_ajax_referer('add-status', 'security');

        if (! current_user_can('manage_woocommerce')) {
            wp_die(-1);
        }

        $name  = isset($_POST['name']) ? sanitize_title($_POST['name']) : '';
        $slug  = isset($_POST['slug']) ? strtolower('wc-' . sanitize_key($_POST['slug'])) : '';
        $alias = isset($_POST['alias']) ? sanitize_title($_POST['alias']) : '';

        $custom_statuses = get_option('trackmage_custom_order_statuses', []);
        $status_aliases = get_option('trackmage_order_status_aliases', []);
        $aliases = Helper::get_aliases();
        $get_statuses = Helper::getOrderStatuses();

        // Errors array.
        $errors = [];

        if (empty ($name)) {
            $errors[] = __('Status name cannot be empty.', 'trackmage');
        }

        if  (empty($_POST['slug']) && !empty($name)) {
            $slug = 'wc-' . preg_replace('#\s+#', '-', strtolower($name));
        }

        if (isset($get_statuses[$slug])) {
            $errors[] = sprintf(__('The slug <em>“%1$s”</em> is already used by another status.', 'trackmage'), $slug);
        }

        if (! empty($alias) && in_array($alias, $status_aliases, true)) {
            $errors[] = sprintf(__('The alias <em>“%1$s”</em> is already assigned to another status.', 'trackmage'), $aliases[$alias]);
        } else if (! empty($alias)) {
            $status_aliases[$slug] = $alias;
        }

        if (! empty($errors)) {
            wp_send_json_error([
                'message' => $errors,
            ]);
        }

        $custom_statuses[$slug] = __($name, 'trackmage');

        update_option('trackmage_custom_order_statuses', $custom_statuses);
        update_option('trackmage_order_status_aliases', $status_aliases);

        $used_aliases = Helper::get_used_aliases();

        wp_send_json_success([
            'message' => __('Status added successfully!', 'trackmage'),
            'result' => [
                'name'  => $name,
                'slug'  => $slug,
                'alias' => $alias,
                'used'  => $used_aliases
            ]
        ]);
    }

    /**
     * Status manager: delete status.
     *
     * @since 1.0.0
     * @todo Refactor error handling using Exceptions instead of $errors array.
     */
    public function deleteStatus() {
        check_ajax_referer('delete-status', 'security');

        if (! current_user_can('manage_woocommerce')) {
            wp_die(-1);
        }

        $slug = isset($_POST['slug']) ? sanitize_key($_POST['slug']) : '';
        $name = isset($_POST['name']) ? sanitize_title($_POST['name']) : '';
        $custom_statuses = get_option('trackmage_custom_order_statuses', []);
        $status_aliases = get_option('trackmage_order_status_aliases', []);

        // Errors array.
        $errors = [];

        if (empty ($slug)) {
            $errors[] = __('Could not delete the selected status.', 'trackmage');
        }

        if (! array_key_exists($slug, $custom_statuses)) {
            $errors[] = __('Core statuses and statuses created by other plugins and themes cannot be deleted.', 'trackmage');
        }

        if (! empty($errors)) {
            wp_send_json_error([
                'message' => $errors,
            ]);
        }

        unset( $custom_statuses[ $slug ], $status_aliases[ $slug ] );

        update_option('trackmage_custom_order_statuses', $custom_statuses);
        update_option('trackmage_order_status_aliases', $status_aliases);

        $used_aliases = Helper::get_used_aliases();

        wp_send_json_success([
            'message' => __('Status deleted successfully', 'trackmage'),
            'result' => [
                'name'  => $name,
                'used'  => $used_aliases
            ]
        ]);
    }

    /**
     * Merge shipments.
     *
     * @since 1.2.0
     */
    public static function mergeShipments() {
        check_ajax_referer('merge-shipments', 'security');

        if (! current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        // Request data.
        $trackingNumber = isset($_POST['trackingNumber']) ? sanitize_title($_POST['trackingNumber']) : null;
        $shipmentId = isset($_POST['shipmentId']) ? sanitize_key($_POST['shipmentId']) : null;
        if(in_array($trackingNumber, ['', null], true) || in_array($shipmentId, ['', null], true) || !isset($_POST['orderId']) || (!is_numeric($_POST['orderId']))) {
            wp_send_json_error(['message' => 'trackingNumber and shipmentId should be set']);
        }
        $orderId = absint($_POST['orderId']);
        $data = [
            'workspaceId' => get_option('trackmage_workspace'),
            'shipmentId' => $shipmentId,
            'trackingNumber' => strtoupper($trackingNumber),
        ];
        $order = wc_get_order($orderId);

        try {
            $synchronizer = Plugin::instance()->getSynchronizer();
            $synchronizer->syncOrder($orderId, true);

            // Update shipment details in the database.
            $shipment = Helper::mergeShipments($data);
            $order->add_order_note(sprintf(__('Shipments were merged', 'trackmage')), false, true);

            // Get HTML to return.
            ob_start();
            include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-shipments.php';
            $html = ob_get_clean();

            ob_start();
            $notes = wc_get_order_notes(array('order_id' => $orderId));
            include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-notes.php';
            $notes_html = ob_get_clean();

            wp_send_json_success([
                'message' => __('Shipment updated successfully!', 'trackmage'),
                'html' => $html,
                'notes' => $notes_html
            ]);
        } catch (ClientException $e) {
            wp_send_json_error(['message' => TrackMageClient::error($e), 'shipmentId' => $shipmentId, 'trackingNumber' => $trackingNumber]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage(), 'shipmentId' => $shipmentId, 'trackingNumber' => $trackingNumber]);
        }
    }
}
