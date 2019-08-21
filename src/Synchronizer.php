<?php

namespace TrackMage\WordPress;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use TrackMage\WordPress\Exception\SynchronizationException;
use WC_Order;

class Synchronizer
{
    /** @var bool ignore events */
    private $disableEvents = false;

    public function __construct()
    {
        $this->bindEvents();
    }

    /**
     * @param bool $disableEvents
     */
    public function setDisableEvents($disableEvents)
    {
        $this->disableEvents = $disableEvents;
    }

    private function bindEvents()
    {
        add_action( 'woocommerce_order_status_changed', [ $this, 'status_changed' ], 10, 3 );
        add_action( 'woocommerce_new_order', [ $this, 'new_order' ], 10, 1 );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'new_order' ], 10, 1 );
    }


    /**
     * Sync with TrackMage on status change.
     *
     * @param string $order_id
     * @return void
     */
    public function status_changed( $order_id, $old_status, $status ) {
        if ($this->disableEvents) {
            return;
        }
        $this->syncOrder($order_id);
    }

    /**
     * Sync with TrackMage on order creation.
     *
     * @param string $order_id
     * @return void
     */
    public function new_order( $order_id ) {
        if ($this->disableEvents) {
            return;
        }

        $order = wc_get_order( $order_id );

        // Exit if order meta has not been saved yet.
        // This will happen with the new orders created from the checkout page.
        // A second try will happen when the `woocommerce_checkout_update_order_meta` action is fired shortly.
        if ( empty( $order->get_items() ) ) {
            return;
        }

        $this->syncOrder($order_id);
    }

    public function syncOrder( $order_id ) {
        $order = wc_get_order( $order_id );
        if (!$this->isStatusSynced($order->get_status())) {
            return;
        }
        $workspace = get_option( 'trackmage_workspace' );
        $client = Plugin::get_client();
        $trackmage_order_id = get_post_meta( $order_id, '_trackmage_order_id', true );

        $guzzleClient = $client->getGuzzleClient();

        // Create order on TrackMage.
        try {
            if (empty($trackmage_order_id)) {
                try {
                    $response = $guzzleClient->post('/orders', [
                        'json' => [
                            'workspace' => '/workspaces/' . $workspace,
                            'externalSyncId' => $order_id,
                            'orderNumber' => $order->get_order_number(),
                            'status' => $order->get_status(),
                        ]
                    ]);
                    $result = json_decode( $response->getBody()->getContents(), true );
                    $trackmage_order_id = $result['id'];
                    add_post_meta( $order_id, '_trackmage_order_id', $trackmage_order_id, true );
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response
                        && null !== ($query = $this->matchSearchCriteriaFromValidationError($order, $response))
                        && null !== ($data = $this->lookupOrderByCriteria($query, $workspace))
                    ) {
                        add_post_meta( $order_id, '_trackmage_order_id', $data['id'], true );
                        $this->syncOrder($order_id);
                        return;
                    }
                    throw $e;
                }
            } else {
                try {
                    $guzzleClient->put("/orders/{$trackmage_order_id}", [
                        'json' => [
                            'externalSyncId' => $order_id,
                            'orderNumber' => $order->get_order_number(),
                            'status' => $order->get_status(),
                        ]
                    ]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response && 404 === $response->getStatusCode()) {
                        delete_post_meta( $order_id, '_trackmage_order_id');
                        $this->syncOrder($order_id);
                        return;
                    }
                    throw $e;
                }
            }


            /*
             * Create order items on TrackMage.
             */
            foreach( $order->get_items() as $id => $item ) {
                /** @var \WC_Product_Simple $product */
                $product = $item->get_product();

                $response = $guzzleClient->post(
                    '/order_items', [
                        'json' => [
                            'order' => '/orders/' . $trackmage_order_id,
                            'productName' => $item['name'],
                            'qty' => $item['quantity'],
                            'price' => (string) $product->get_price(),
                            'externalSyncId' => (string) $item->get_id(),
                            'rowTotal' => $item->get_total(),
                        ]
                    ]
                );

                if ( 201 === $response->getStatusCode() ) {
                    $result = json_decode( $response->getBody()->getContents(), true );
                    $trackmage_order_item_id = $result['id'];
                    wc_add_order_item_meta( $id, '_trackmage_order_item_id', $trackmage_order_item_id, true );
                }
            }
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param WC_Order $order
     * @param ResponseInterface $response
     * @return array|null
     */
    private function matchSearchCriteriaFromValidationError(WC_Order $order, ResponseInterface $response)
    {
        if (400 !== $response->getStatusCode()) {
            return null;
        }
        $query = [];
        $content = $response->getBody()->getContents();
        if (false !== strpos($content, 'externalSyncId')) {
            $query['externalSyncId'] = $order->get_id();
        } elseif (false !== strpos($content, 'orderNumber')) {
            $query['orderNumber'] = $order->get_order_number();
        } else {
            return null;
        }

        return $query;
    }

    public function deleteOrder( $order_item_id )
    {

    }

    public function syncOrderItem( $order_item_id )
    {

    }

    public function deleteOrderItem( $item_id )
    {

    }

    public function syncShipment()
    {
        
    }

    public function deleteShipment()
    {

    }

    public function syncShipmentItem()
    {

    }

    public function deleteShipmentItem()
    {

    }

    /**
     * @param $status
     * @return bool
     */
    private function isStatusSynced($status)
    {
        $sync_statuses = get_option('trackmage_sync_statuses', []);
        return empty($sync_statuses) || in_array('wc-' . $status, $sync_statuses, true);
    }

    /**
     * @param array $query
     * @param string $workspace
     * @return array|null
     */
    private function lookupOrderByCriteria(array $query, $workspace)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();
        $query['itemsPerPage'] = 1;
        $response = $guzzleClient->get("/workspaces/{$workspace}/orders", ['query' => $query]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);
        return isset($data['hydra:member'][0]) ? $data['hydra:member'][0] : null;
    }
}
