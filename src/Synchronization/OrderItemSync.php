<?php

namespace TrackMage\WordPress\Synchronization;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use WC_Order;
use WC_Order_Item;

class OrderItemSync implements EntitySyncInterface
{
    use SyncSharedTrait;

    /** @var ChangesDetector */
    private $changesDetector;

    /** @var string|null */
    private $source;

    /**
     * @param string|null $source
     */
    public function __construct($source = null)
    {
        $this->source = $source;
    }

    /**
     * @return ChangesDetector
     */
    private function getChangesDetector()
    {
        if (null === $this->changesDetector) {
            $detector = new ChangesDetector([
                '[name]', '[quantity]', '[price]', '[total]',
            ], function(WC_Order_Item $item) {
                return wc_get_order_item_meta( $item->get_id(), '_trackmage_hash', true );
            }, function(WC_Order_Item $item, $hash) {
                wc_add_order_item_meta( $item->get_id(), '_trackmage_hash', $hash, true )
                    || wc_update_order_item_meta($item->get_id(), '_trackmage_hash', $hash);
                return $item;
            });
            $this->changesDetector = $detector;
        }

        return $this->changesDetector;
    }

    /**
     * @param int $orderItemId
     * @param WC_Order $order
     * @return WC_Order_Item|\WC_Order_Item_Product
     */
    private function getOrderItem($orderItemId, WC_Order $order)
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

        $trackmage_order_item_id = wc_get_order_item_meta( $orderItemId, '_trackmage_order_item_id', true );

        if (!($this->canSyncOrder($order) && (empty($trackmage_order_item_id) || $this->getChangesDetector()->isChanged($item)))) {
            return;
        }

        $trackmage_order_id = get_post_meta( $orderId, '_trackmage_order_id', true );
        if (empty($trackmage_order_id)) {
            throw new SynchronizationException('Unable to sync order item because order is not yet synced');
        }

        $workspace = get_option( 'trackmage_workspace' );

        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        $product = $item->get_product();

        try {
            if (empty($trackmage_order_item_id)) {
                try {
                    $response = $guzzleClient->post('/order_items', [
                        'query' => ['ignoreWebhookId' => $workspace],
                        'json' => [
                            'order' => '/orders/' . $trackmage_order_id,
                            'productName' => $item['name'],
                            'qty' => $item['quantity'],
                            'price' => $product->get_price(),
                            'rowTotal' => $item->get_total(),
                            'externalSyncId' => (string)$orderItemId,
                            'externalSource' => $this->source,
                        ]
                    ]);
                    $result = json_decode( $response->getBody()->getContents(), true );
                    $trackmage_order_item_id = $result['id'];
                    wc_add_order_item_meta($orderItemId, '_trackmage_order_item_id', $trackmage_order_item_id, true )
                        || wc_update_order_item_meta($orderItemId, '_trackmage_order_item_id', $trackmage_order_item_id);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response
                        && null !== ($query = $this->matchSearchCriteriaFromValidationError($item, $response))
                        && null !== ($data = $this->lookupByCriteria($query, $trackmage_order_id))
                    ) {
                        wc_add_order_item_meta($orderItemId, '_trackmage_order_item_id', $data['id'], true )
                            || wc_update_order_item_meta($orderItemId, '_trackmage_order_item_id', $data['id']);
                        $this->sync($orderItemId);
                        return;
                    }
                    throw $e;
                }
            } else {
                try {
                    $guzzleClient->put('/order_items/'.$trackmage_order_item_id, [
                        'query' => ['ignoreWebhookId' => $workspace],
                        'json' => [
                            'productName' => $item['name'],
                            'qty' => $item['quantity'],
                            'price' => $product->get_price(),
                            'rowTotal' => $item->get_total(),
                        ]
                    ]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response && 404 === $response->getStatusCode()) {
                        wc_delete_order_item_meta( $orderItemId, '_trackmage_order_item_id');
                        $this->sync($orderItemId);
                        return;
                    }
                    throw $e;
                }
            }
            $this->getChangesDetector()->lockChanges($item);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param WC_Order_Item $item
     * @param ResponseInterface $response
     * @return array|null
     */
    private function matchSearchCriteriaFromValidationError(WC_Order_Item $item, ResponseInterface $response)
    {
        if (400 !== $response->getStatusCode()) {
            return null;
        }
        $query = [];
        $content = $response->getBody()->getContents();
        if (false !== strpos($content, 'externalSyncId')) {
            $query['externalSyncId'] = $item->get_id();
            $query['externalSource'] = $this->source;
        } else {
            return null;
        }

        return $query;
    }

    /**
     * @param array $query
     * @param string $orderId
     * @return array|null
     */
    private function lookupByCriteria(array $query, $orderId)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();
        $query['itemsPerPage'] = 1;
        $response = $guzzleClient->get("/orders/{$orderId}/items", ['query' => $query]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);
        return isset($data['hydra:member'][0]) ? $data['hydra:member'][0] : null;
    }

    public function delete($id)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        $trackmage_order_item_id = wc_get_order_item_meta( $id, '_trackmage_order_item_id', true );
        if (empty($trackmage_order_item_id)) {
            return;
        }
        $workspace = get_option( 'trackmage_workspace' );

        try {
            $guzzleClient->delete('/order_items/'.$trackmage_order_item_id, ['query' => ['ignoreWebhookId' => $workspace]]);
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete order item: '.$e->getMessage(), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        } finally {
            wc_delete_order_item_meta($id, '_trackmage_order_item_id');
        }
    }

    public function unlink($id)
    {
        wc_delete_order_item_meta($id, '_trackmage_order_item_id');
    }
}
