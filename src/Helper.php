<?php
/**
 * Utilities and helper functions.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

use GuzzleHttp\Exception\ClientException;
use TrackMage\Client\TrackMageClient;
use TrackMage\Client\Swagger\ApiException;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Exception\RuntimeException;

/**
 * Static functions that can be called without instantiation.
 *
 * @since   0.1.0
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */
class Helper {
    const CREDENTIALS_INVALID = 0;
    const CREDENTIALS_VALID = 1;
    const CREDENTIALS_ERROR = 2;

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
            $client->setHost('https://api.test.trackmage.com');
            $client->getGuzzleClient()->get('/workspaces');
            return self::CREDENTIALS_VALID;
        } catch( ClientException $e ) {
            if ($e->getResponse()->getStatusCode() === 401) {
                return self::CREDENTIALS_INVALID;
            }
        } catch( ApiException $e ) {
            if ( 'Authorization error' === $e->getMessage() ) {
                return self::CREDENTIALS_INVALID;
            }
        }
        return self::CREDENTIALS_ERROR;
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
            $response = $client->getGuzzleClient()->get('/workspaces');
            $contents = $response->getBody()->getContents();
            $data = json_decode($contents, true);
            $result = isset($data['hydra:member'])? $data['hydra:member'] : [];

            foreach ( $result as $workspace ) {
                $workspaces[] = [
                    'id' => $workspace['id'],
                    'title' => $workspace['title'],
                ];
            }
        } catch( ApiException $e ) {
        } catch( ClientException $e ) {
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

                $carriers = [
                    ['code' => 'auto', 'name' => 'Detect automatically'],
                ];
                foreach ( $result as $carrier ) {
                    $carriers[] = [
                        'code' => $carrier->getCode(),
                        'name' => $carrier->getName(),
                    ];
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
        $workspaceId = get_option( 'trackmage_workspace', 0 );
        $cachedAliases = get_transient('trackmage_order_statuses');
        $aliases = [];
        if($cachedAliases && isset($cachedAliases[$workspaceId])) {
            $aliases = $cachedAliases[$workspaceId];
        }elseif($workspaceId != 0){
            try {
                $client = Plugin::get_client();
                $guzzleClient = $client->getGuzzleClient();
                $response = $guzzleClient->get("/workspaces/{$workspaceId}/statuses?entity=orders");
                $content = $response->getBody()->getContents();
                $data = json_decode($content, true);
                $aliases = array_column($data['hydra:member'], 'title', 'code');
                $cachedAliases[$workspaceId] = $aliases;
                set_transient('trackmage_order_statuses', $cachedAliases, 3600);
            } catch( ApiException $e ) {

            }

        }
        return $aliases;
    }

    /**
     * Returns the used aliases.
     *
     * @return array List of aliases.
     */

    public static function get_used_aliases() {
        $usedAliases = get_option( 'trackmage_order_status_aliases', [] );
        return array_values(array_filter($usedAliases));
    }

    /**
     * @param int $orderId
     * @return array of shipments
     */
    public static function getOrderShipmentsWithJoinedItems($orderId)
    {
        $shipmentRepo = Plugin::instance()->getShipmentRepo();
        $shipmentItemRepo = Plugin::instance()->getShipmentItemsRepo();
        $shipments = $shipmentRepo->findBy(['order_id' => $orderId]);
        foreach ($shipments as &$shipment) {
            $shipment['items'] = $shipmentItemRepo->findBy(['shipment_id' => $shipment['id']]);
        }
        unset($shipment);

        return $shipments;
    }

    /**
     * @param int $id
     * @return array
     */
    public static function geShipmentWithJoinedItems($id)
    {
        $shipmentRepo = Plugin::instance()->getShipmentRepo();
        $shipmentItemRepo = Plugin::instance()->getShipmentItemsRepo();
        $shipment = $shipmentRepo->find($id);
        $shipment['items'] = $shipmentItemRepo->findBy(['shipment_id' => $shipment['id']]);

        return $shipment;
    }


    public static function isBulkSynchronizationInProcess()
    {
        global $wpdb;
        $activeTasks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".($wpdb->prefix.'trackmage_background_task')." WHERE action = %s AND (status = %s OR status = %s)","trackmage_bulk_orders_sync", "processing", "new" ) , ARRAY_A );
        return is_array($activeTasks) && count($activeTasks) > 0;
    }

    /**
     * @param array $shipment
     * @return array
     */
    public static function saveShipmentWithJoinedItems(array $shipment)
    {
        $shipmentRepo = Plugin::instance()->getShipmentRepo();

        $items = $shipment['items'];
        unset($shipment['items']);

        if (null !== $shipmentId = self::parseId($shipment)) {
            unset($shipment['id']);
            $shipment = $shipmentRepo->update($shipment, ['id' => $shipmentId]);
            do_action('trackmage_update_shipment', $shipmentId);
        } else {
            $shipment = $shipmentRepo->insert($shipment);
            do_action('trackmage_new_shipment', $shipment['id']);
        }
        if ($shipment === null) {
            throw new RuntimeException('Unable to save shipment');
        }
        $shipment['items'] = self::saveShipmentItems($shipment['id'], $items);

        return $shipment;
    }

    /**
     * @param int $shipmentId
     * @param array $items
     * @return array
     */
    public static function saveShipmentItems($shipmentId, $items)
    {
        $shipmentItemRepo = Plugin::instance()->getShipmentItemsRepo();

        //load existing shipment items before we create new ones
        $existingItems = $shipmentItemRepo->findBy(['shipment_id' => $shipmentId]);

        $items = array_map(function($item) use ($shipmentId, $shipmentItemRepo) {
            if (null !== $itemId = self::parseId($item)) {
                unset($item['id']);
                $item = $shipmentItemRepo->update($item, ['id' => $itemId]);
                do_action('trackmage_update_shipment_item', $itemId);
            } else {
                $item['shipment_id'] = $shipmentId;
                $item = $shipmentItemRepo->insert($item);
                do_action('trackmage_new_shipment_item', $item['id']);
            }
            if ($item === null) {
                throw new RuntimeException('Unable to save shipment item');
            }
            return $item;
        }, $items);

        //delete removed items
        $itemsIds = array_column($items, 'id');
        foreach ($existingItems as $existingItem) {
            $existingItemId = $existingItem['id'];
            if (in_array($existingItemId, $itemsIds, true)) {
                continue;
            }
            do_action('trackmage_delete_shipment_item', $existingItemId);
            $shipmentItemRepo->delete(['id' => $existingItemId]);
        }

        return $items;
    }

    /**
     * @param int $shipmentId
     */
    public static function deleteShipment($shipmentId)
    {
        $shipmentRepo = Plugin::instance()->getShipmentRepo();
        if (null === $shipmentRepo->find($shipmentId)) {
            throw new InvalidArgumentException('Unable to find shipment: '.$shipmentId);
        }
        self::saveShipmentItems($shipmentId, []);
        do_action('trackmage_delete_shipment', $shipmentId);
        $shipmentRepo->delete(['id' => $shipmentId]);
    }

    /**
     * @param array $data
     * @param string $idField
     * @return int|null
     */
    public static function parseId(array $data, $idField = 'id')
    {
        if (isset($data[$idField]) && is_numeric($data[$idField])) {
            return (int) $data[$idField];
        }
        return null;
    }

    /**
     * @param array $shipment
     * @param array $orderItems
     * @param array $existingShipments
     * @return void
     */
    public static function validateShipment(array $shipment, array $orderItems, array $existingShipments)
    {
        $items = $shipment['items'];
        // Check tracking number.
        if (empty($shipment['tracking_number'])) {
            throw new InvalidArgumentException(__('Tracking number cannot be left empty.', 'trackmage'));
        }

        // Check carrier.
        if (empty($shipment['carrier'])) {
            throw new InvalidArgumentException(__('Carrier cannot be left empty.', 'trackmage'));
        }

        // Check if no items added.
        if (!is_array($items) || empty($items)) {
            throw new InvalidArgumentException(__('No items added.', 'trackmage'));
        }

        foreach ($items as $item) {
            // Check if any of the selected items no longer exists.
            if (!array_key_exists($item['order_item_id'], $orderItems)) {
                throw new InvalidArgumentException(__('Order item does not exist.', 'trackmage'));
            }
        }

        foreach ($items as $item) {
            // Check if any of the items has non-positive quantities.
            if (0 >= $item['qty']) {
                throw new InvalidArgumentException(__('Item quantity must be a positive integer.', 'trackmage'));
            }

            // Check the available quantities for each item.
            $totalQty = $orderItems[$item['order_item_id']]->get_quantity();
            $usedQty = 0;
            foreach ($existingShipments as $existingShipment) {
                // Exclude the quantities of the items in the current shipment.
                if (isset($shipment['id']) &&  (int) $existingShipment['id'] === (int)$shipment['id']) {
                    continue;
                }

                foreach ($existingShipment['items'] as $existingItem) {
                    if ($item['order_item_id'] === $existingItem['order_item_id']) {
                        $usedQty += (int)$existingItem['qty'];
                    }
                }
            }
            $availQty = $totalQty - $usedQty;

            // Check the available quantities of each item.
            if ($availQty < $item['qty']) {
                throw new InvalidArgumentException(__('No available quantity.', 'trackmage'));
            }
        }
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
        $statuses = self::getOrderStatuses();
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

    /**
     * Schedule next background task.
     *
     * @param int   $delay  Delay before run scheduled task
     *
     * @return int|bool
     */
    public static function scheduleNextBackgroundTask($delay = 0)
    {
        $backgroundTaskRepo = Plugin::instance()->getBackgroundTaskRepo();
        $activeTask = $backgroundTaskRepo->findOneBy(['status'=>'processing']);
        if(isset($activeTask['id']))
            return false;
        $nextTask = $backgroundTaskRepo->getQuery('SELECT * FROM _TBL_ WHERE status="new" ORDER BY priority, id LIMIT 1');
        if(isset($nextTask[0]) && !isset($nextTask[0]->id))
            return false;
        $scheduled = wp_schedule_single_event( time() + $delay, $nextTask[0]->action, [ json_decode($nextTask[0]->params) , $nextTask[0]->id ] );
        return $nextTask[0]->id;
    }
}
