<?php

namespace TrackMage\WordPress;

use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Throwable;
use TrackMage\WordPress\Exception\RuntimeException;
use TrackMage\WordPress\Synchronization\OrderItemSync;
use TrackMage\WordPress\Synchronization\OrderSync;
use TrackMage\WordPress\Synchronization\ShipmentItemSync;
use TrackMage\WordPress\Synchronization\ShipmentSync;

class Synchronizer
{
    const TAG = '[Synchronizer]';

    /** @var bool ignore events */
    private $disableEvents = false;

    private $orderSync;
    private $orderItemSync;
    private $shipmentSync;
    private $shipmentItemSync;

    private $logger;

    public function __construct(LoggerInterface $logger, OrderSync $orderSync, OrderItemSync $orderItemSync,
                                ShipmentSync $shipmentSync, ShipmentItemSync $shipmentItemSync)
    {
        $this->logger = $logger;
        $this->orderSync = $orderSync;
        $this->orderItemSync = $orderItemSync;
        $this->shipmentSync = $shipmentSync;
        $this->shipmentItemSync = $shipmentItemSync;
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
        add_action( 'woocommerce_order_status_changed', [ $this, 'syncOrder' ], 10, 3 );
        add_action( 'woocommerce_new_order', [ $this, 'syncOrder' ], 10, 1 );
        add_action( 'woocommerce_update_order', [ $this, 'syncOrder' ], 10, 1 );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'syncOrder' ], 10, 1 );

        add_action('wp_trash_post', function ($postId) {//woocommerce_trash_order is not fired
            $type = get_post_type($postId);
            if ($type === 'shop_order'){
                // This doesn't work whatsoever:
                // the trashed order status remains the same
                // and change detector doesn't find the difference.
                // So OrderSync skips this update.
                $this->syncOrder($postId);
            }
        }, 10, 1);
        add_action('before_delete_post', function ($postId) { //woocommerce_delete_order is not fired
            $type = get_post_type($postId);
            if ($type === 'shop_order'){
                $this->deleteOrder($postId);
            }
        }, 10, 1);

        add_action( 'woocommerce_new_order_item', [ $this, 'syncOrderItem' ], 10, 1 );
        add_action( 'woocommerce_update_order_item', [ $this, 'syncOrderItem' ], 10, 1 );
        add_action( 'woocommerce_delete_order_item', [ $this, 'deleteOrderItem' ], 10, 1 );

        add_action( 'trackmage_new_shipment', [ $this, 'syncShipment' ], 10, 1 );
        add_action( 'trackmage_update_shipment', [ $this, 'syncShipment' ], 10, 1 );
        add_action( 'trackmage_delete_shipment', [ $this, 'deleteShipment' ], 10, 1 );

        add_action( 'trackmage_new_shipment_item', [ $this, 'syncShipmentItem' ], 10, 1 );
        add_action( 'trackmage_update_shipment_item', [ $this, 'syncShipmentItem' ], 10, 1 );
        add_action( 'trackmage_delete_shipment_item', [ $this, 'deleteShipmentItem' ], 10, 1 );
    }

    public function syncOrder($orderId ) {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->orderSync->sync($orderId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote order', array_merge([
                'order_id' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function deleteOrder($orderId)
    {
        if ($this->disableEvents) {
            return;
        }
        $order = wc_get_order( $orderId );
        foreach ($order->get_items() as $item) { //woocommerce_delete_order_item is not fired on order delete
            $this->deleteOrderItem($item->get_id());
        }
        try {
            $this->orderSync->delete($orderId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to delete remote order', array_merge([
                'order_id' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function syncOrderItem($itemId )
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->orderItemSync->sync($itemId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote order item', array_merge([
                'item_id' => $itemId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function deleteOrderItem($itemId )
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->orderItemSync->delete($itemId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to delete remote order item', array_merge([
                'item_id' => $itemId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function syncShipment($shipment_id)
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->shipmentSync->sync($shipment_id);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote shipment', array_merge([
                'shipment_id' => $shipment_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function deleteShipment($shipment_id)
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->shipmentSync->delete($shipment_id);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to delete remote shipment', array_merge([
                'shipment_id' => $shipment_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function syncShipmentItem($shipment_item_id)
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->shipmentItemSync->sync($shipment_item_id);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote shipment item', array_merge([
                'shipment_item_id' => $shipment_item_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function deleteShipmentItem($shipment_item_id)
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->shipmentItemSync->delete($shipment_item_id);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to delete remote shipment item', array_merge([
                'shipment_item_id' => $shipment_item_id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    /**
     * @param Throwable $e
     * @return array
     */
    private function grabGuzzleData(Throwable $e)
    {
        if ($e instanceof RequestException) {
            $result = [];
            if (null !== $request = $e->getRequest()) {
                $request->getBody()->rewind();
                $content = $request->getBody()->getContents();
                $data = json_decode($content, true);
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $data = $content;
                }
                $result['request'] = [
                    'method' => $request->getMethod(),
                    'uri' => $request->getUri()->__toString(),
                    'body' => $data,
                ];
            }
            if (null !== $response = $e->getResponse()) {
                $response->getBody()->rewind();
                $content = $response->getBody()->getContents();
                $data = json_decode($content, true);
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $data = $content;
                }
                $result['response'] = [
                    'status' => $response->getStatusCode(),
                    'body' => $data,
                ];
            }
            return $result;
        }
        $prev = $e->getPrevious();
        if (null !== $prev) {
            return $this->grabGuzzleData($prev);
        }

        return [];
    }
}
