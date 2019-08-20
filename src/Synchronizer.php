<?php

namespace TrackMage\WordPress;

use TrackMage\Client\Swagger\ApiException;

class Synchronizer
{
    public function __construct()
    {
        $this->bindEvents();
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
     * @return void
     */
    public function status_changed( $order_id, $old_status, $status ) {
        $sync_statuses = get_option( 'trackmage_sync_statuses', [] );

        update_option( 'trackmage_test', json_encode( [
            'order_id' => $order_id,
            'status' => $status,
            'sync_statuses' => $sync_statuses
        ] ) );

        if ( in_array( 'wc-' . $status, $sync_statuses, true ) ) {
            $this->syncOrder($order_id);
        }
    }

    /**
     * Sync with TrackMage on order creation.
     *
     * @return void
     */
    public function new_order( $order_id ) {
        $order = wc_get_order( $order_id );

        // Exit if order meta has not been saved yet.
        // This will happen with the new orders created from the checkout page.
        // A second try will happen when the `woocommerce_checkout_update_order_meta` action is fired shortly.
        if ( empty( $order->get_items() ) ) {
            return;
        }

        $status = $order->get_status();
        $sync_statuses = get_option( 'trackmage_sync_statuses', [] );

        if ( empty( $sync_statuses ) || in_array( 'wc-' . $status, $sync_statuses ) ) {
            $this->syncOrder($order_id);
        }
    }

    public function syncOrder( $order_id ) {
        $workspace = get_option( 'trackmage_workspace' );
        $order = wc_get_order( $order_id );
        $client = Plugin::get_client();
        $_trackmage_order_id = get_post_meta( $order_id, '_trackmage_order_id', 0 );

        if ( $_trackmage_order_id ) {
            return;
        }
        $guzzleClient = $client->getGuzzleClient();

        // Create order on TrackMage.
        try {
            $response = $guzzleClient->get("/workspaces/{$workspace}/orders", [
                'query' => [
                    'externalSyncId' => $order_id,
//                    'orderNumber' => $order->get_order_number(),
                    'itemsPerPage' => 1,
                ]
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            $tmOrderId = isset($data['hydra:member'][0]) ? $data['hydra:member'][0]['id'] : null;

            if (null === $tmOrderId) {
                $response = $guzzleClient->post('/orders', [
                    'json' => [
                        'workspace' => '/workspaces/' . $workspace,
                        'externalSyncId' => (string) $order_id,
                        'orderNumber' => $order->get_order_number(),
                        'status' => $order->get_status(),
                    ]
                ]);
            } else {
                $response = $guzzleClient->put("/orders/{$tmOrderId}", [
                    'json' => [
                        'orderNumber' => $order->get_order_number(),
                        'status' => $order->get_status(),
                    ]
                ]);
            }

            if ( 201 === $response->getStatusCode() ) {
                $result = json_decode( $response->getBody()->getContents(), true );
                $trackmage_order_id = $result['id'];
                add_post_meta( $order_id, '_trackmage_order_id', $trackmage_order_id, true );

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
            }
        } catch ( ApiException $e ) {
            // Do nothing for now.
        }
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
}
