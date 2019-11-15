<?php
/**
 * Ajax event handlers
 *
 * @class   Ajax
 * @package TrackMage\WordPress
 * @Author  TrackMage
 */

namespace TrackMage\WordPress;

use TrackMage\WordPress\Repository\ShipmentRepository;

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
            'add_status' => 'addStatus',
            'update_status' => 'updateStatus',
            'delete_status' => 'deleteStatus',
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
        $shipmentId = $_POST['id'];
        $shipment = Helper::geShipmentWithJoinedItems($shipmentId);

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
        $orderId = $_POST['orderId'];
        $addAllOrderItems = $_POST['addAllOrderItems'] === 'true' ? true : false;

        $shipment = [
            'order_id' => $_POST['orderId'],
            'tracking_number' => $_POST['trackingNumber'],
            'carrier' => $_POST['carrier'],
            'items' => $_POST['items'],
        ];

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = $order->get_items();
        $existingShipments   = Helper::getOrderShipmentsWithJoinedItems($orderId);
        $orderNotes          = [];

        try {
            if ($addAllOrderItems) {
                if (! empty($existingShipments)) {
                    throw new \Exception(__('Other shipments have already been created, please delete them first or uncheck “Add all order items”.','trackmage'));
                }

                $shipment['items'] = array_map(function(\WC_Order_Item $item) {
                    return [
                        'order_item_id' => $item->get_id(),
                        'qty' => $item->get_quantity(),

                    ];
                }, $orderItems);
            }

            Helper::validateShipment($shipment, $orderItems, $existingShipments);
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
        $orderId = $_POST['orderId'];

        $shipment = [
            'id' => $_POST['id'],
            'order_id' => $_POST['orderId'],
            'tracking_number' => $_POST['trackingNumber'],
            'carrier' => $_POST['carrier'],
            'items' => $_POST['items'],
        ];

        $existingShipments = Helper::getOrderShipmentsWithJoinedItems($orderId);

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = $order->get_items();

        try {
            Helper::validateShipment($shipment, $orderItems, $existingShipments);

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
        $shipmentId = $_POST['id'];

        $shipment = Helper::geShipmentWithJoinedItems($shipmentId);
        // Delete shipment record from the database.
        Helper::deleteShipment($shipmentId);

        // Order data.
        $order               = wc_get_order($orderId);
        $orderItems          = $order->get_items();

        $order->add_order_note( sprintf( __( 'Shipment %s was deleted', 'trackmage' ), $shipment['tracking_number']), false, true );

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

        $name = $_POST['name'];
        $current_slug = $_POST['currentSlug'];
        $slug = $_POST['slug'];
        $alias = $_POST['alias'];
        $is_custom = '1' === $_POST['isCustom'] ? true : false;

        $custom_statuses = get_option('trackmage_custom_order_statuses', []);
        $modified_statuses = get_option('trackmage_modified_order_statuses', []);
        $status_aliases = get_option('trackmage_order_status_aliases', []);
        $aliases = Helper::get_aliases();
        $get_statuses = Helper::getOrderStatuses();


        // Errors array.
        $errors = [];

        if (empty ($name)) {
            array_push($errors, __('Status name cannot be empty.', 'trackmage'));
        }

        if (empty ($slug)) {
            array_push($errors, __('Status slug cannot be empty.', 'trackmage'));
        }

        if ($current_slug !== $slug && isset($get_statuses[$slug])) {
            array_push($errors, sprintf(__('The slug <em>“%1$s”</em> is already used by another status.', 'trackmage'), $slug));
        }

        if ($is_custom && $current_slug !== $slug) {
            unset($custom_statuses[$current_slug]);
        }

        if (! $is_custom && $current_slug !== $slug) {
            array_push($errors, __('The slug of core statuses and statuses created by other plugins and themes cannot be changed.', 'trackmage'));
        }

        if ($is_custom) {
            $custom_statuses[$slug] = __($name, 'trackmage');
        } else {
            $modified_statuses[$slug] = __($name, 'trackmage');
        }

        if (! empty($alias)
            && in_array($alias, $status_aliases)
            && isset($status_aliases[$current_slug])
            && $alias !== $status_aliases[$current_slug])
        {
            array_push($errors, sprintf(__('The alias <em>“%1$s”</em> is already assigned to another status.', 'trackmage'), $aliases[$alias]));
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

        $name  = $_POST['name'];
        $slug  = strtolower('wc-' . $_POST['slug']);
        $alias = $_POST['alias'];

        $custom_statuses = get_option('trackmage_custom_order_statuses', []);
        $status_aliases = get_option('trackmage_order_status_aliases', []);
        $aliases = Helper::get_aliases();
        $get_statuses = Helper::getOrderStatuses();

        // Errors array.
        $errors = [];

        if (empty ($name)) {
            array_push($errors, __('Status name cannot be empty.', 'trackmage'));
        }

        if  (empty($_POST['slug'])) {
            $slug = 'wc-' . preg_replace('#\s+#', '-', strtolower($name));
        }

        if (isset($get_statuses[$slug])) {
            array_push($errors, sprintf(__('The slug <em>“%1$s”</em> is already used by another status.', 'trackmage'), $slug));
        }

        if (! empty($alias) && in_array($alias, $status_aliases)) {
            array_push($errors, sprintf(__('The alias <em>“%1$s”</em> is already assigned to another status.', 'trackmage'), $aliases[$alias]));
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

        $slug = $_POST['slug'];
        $name  = $_POST['name'];
        $custom_statuses = get_option('trackmage_custom_order_statuses', []);
        $status_aliases = get_option('trackmage_order_status_aliases', []);

        // Errors array.
        $errors = [];

        if (empty ($slug)) {
            array_push($errors, __('Could not delete the selected status.', 'trackmage'));
        }

        if (! array_key_exists($slug, $custom_statuses)) {
            array_push($errors, __('Core statuses and statuses created by other plugins and themes cannot be deleted.', 'trackmage'));
        }

        if (! empty($errors)) {
            wp_send_json_error([
                'message' => $errors,
            ]);
        }

        unset($custom_statuses[$slug]);
        unset($status_aliases[$slug]);

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
}
