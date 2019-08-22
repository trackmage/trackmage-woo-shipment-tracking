<?php

namespace TrackMage\WordPress\Syncrhonization;

use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;

class OrderItemSync implements EntitySyncInterface
{
    use SyncSharedTrait;

    /** @var ChangesDetector */
    private $changesDetector;

    /**
     * @return ChangesDetector
     */
    private function getChangesDetector()
    {
        if (null === $this->changesDetector) {
            $detector = new ChangesDetector([
                '[order_number]', '[status]',
            ], function(\WC_Order_Item $item) {
                return wc_get_order_item_meta( $item->get_id(), '_trackmage_hash', true );
            }, function(\WC_Order_Item $item, $hash) {
                wc_add_order_item_meta( $item->get_id(), '_trackmage_hash', $hash, true );
                return $item;
            });
            $this->changesDetector = $detector;
        }

        return $this->changesDetector;
    }

    /**
     * @param int $orderItemId
     * @param \WC_Order $order
     * @return \WC_Order_Item|\WC_Order_Item_Product
     */
    private function getOrderItem($orderItemId, \WC_Order $order)
    {
        foreach( $order->get_items() as $id => $item ) {
            if ($id === $orderItemId) {
                return $item;
            }
        }
        return null;
    }

    public function sync($orderItemId)
    {
        $orderId = wc_get_order_id_by_order_item_id($orderItemId);
        $order = wc_get_order($orderId);
        $item = $this->getOrderItem($orderItemId, $order);
        if ($item === null) {
            throw new InvalidArgumentException('Unable to find order item id: '. $orderItemId);
        }
        if (!$this->canSyncOrder($order) || !$this->getChangesDetector()->isChanged($item)) {
            return;
        }

        $trackmage_order_id = get_post_meta( $orderId, '_trackmage_order_id', true );
        if (empty($trackmage_order_id)) {
            throw new SynchronizationException('Unable to sync order item because order is not yet synced');
        }
        $trackmage_order_item_id = wc_get_order_item_meta( $orderItemId, '_trackmage_order_item_id', true );

        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        $product = $item->get_product();

        try {
            if (empty($trackmage_order_item_id)) {
                $response = $guzzleClient->post('/order_items', [
                    'json' => [
                        'order' => '/orders/' . $trackmage_order_id,
                        'productName' => $item['name'],
                        'qty' => $item['quantity'],
                        'price' => $product->get_price(),
                        'rowTotal' => $item->get_total(),
                        'externalSyncId' => $item->get_id(),
                    ]
                ]);
                $result = json_decode( $response->getBody()->getContents(), true );
                $trackmage_order_item_id = $result['id'];
                wc_add_order_item_meta($orderItemId, '_trackmage_order_item_id', $trackmage_order_item_id, true );
            } else {
                $guzzleClient->put('/order_items/'.$trackmage_order_item_id, [
                    'json' => [
                        'productName' => $item['name'],
                        'qty' => $item['quantity'],
                        'price' => $product->get_price(),
                        'rowTotal' => $item->get_total(),
                        'externalSyncId' => $item->get_id(),
                    ]
                ]);
            }

            $this->getChangesDetector()->lockChanges($item);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public function delete($id)
    {
        // TODO: Implement delete() method.
    }
}
