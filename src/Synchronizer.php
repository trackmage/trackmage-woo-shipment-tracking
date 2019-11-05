<?php

namespace TrackMage\WordPress;

use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Throwable;
use TrackMage\WordPress\Exception\RuntimeException;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Repository\BackgroundTaskRepository;
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
    private $shipmentRepository;
    private $shipmentItemRepository;
    private $backgroundTaskRepository;

    private $logger;

    public function __construct(LoggerInterface $logger, OrderSync $orderSync, OrderItemSync $orderItemSync,
                                ShipmentSync $shipmentSync, ShipmentItemSync $shipmentItemSync,
                                ShipmentRepository $shipmentRepository, ShipmentItemRepository $shipmentItemRepository, BackgroundTaskRepository $backgroundTaskRepository)
    {
        $this->logger = $logger;
        $this->orderSync = $orderSync;
        $this->orderItemSync = $orderItemSync;
        $this->shipmentSync = $shipmentSync;
        $this->shipmentItemSync = $shipmentItemSync;
        $this->shipmentRepository = $shipmentRepository;
        $this->shipmentItemRepository = $shipmentItemRepository;
        $this->backgroundTaskRepository = $backgroundTaskRepository;
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

        add_action( 'trackmage_bulk_orders_sync', [$this, 'bulkOrdersSync'], 10, 2);
        add_action( 'trackmage_delete_data', [$this, 'deleteData'], 10, 2);

    }

    public function bulkOrdersSync($orderIds = [], $taskId = null){
        try{
            $this->logger->info(self::TAG.'Start to processing orders', ['orderIds'=>$orderIds,'taskId'=>$taskId]);
            if($taskId !== null)
                $this->backgroundTaskRepository->update(['status'=>'processing'],['id'=>$taskId]);

            foreach ($orderIds as $orderId){
                $this->syncOrder($orderId);
            }

            $this->logger->info(self::TAG.'Processing orders is completed', ['orderIds'=>$orderIds]);
            if($taskId !== null)
                $this->backgroundTaskRepository->update(['status'=>'processed'],['id'=>$taskId]);
            Helper::scheduleNextBackgroundTask();
        }catch (RuntimeException $e){
            $this->logger->warning(self::TAG.'Unable to bulk sync orders', array_merge([
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function syncOrder($orderId ) {
        if ($this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
            return;
        }
        try {
            $this->orderSync->sync($orderId);

            foreach ($this->shipmentRepository->findBy(['order_id' => $orderId]) as $shipment) {
                $this->syncShipment($shipment['id']);
            }

            $order = wc_get_order( $orderId );
            foreach ($order->get_items() as $item) {
                $this->syncOrderItem($item->get_id());
            }
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote order', array_merge([
                'order_id' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function deleteData($orderIds = [], $taskId = null){
        try{
            $this->logger->info(self::TAG.'Start to delete orders on TrackMage Workspace', ['orderIds'=>$orderIds,'taskId'=>$taskId]);
            if($taskId !== null)
                $this->backgroundTaskRepository->update(['status'=>'processing'],['id'=>$taskId]);

            foreach ($orderIds as $orderId){
                $this->deleteData($orderId);
            }

            $this->logger->info(self::TAG.'Orders deletion is completed', ['orderIds'=>$orderIds]);
            if($taskId !== null)
                $this->backgroundTaskRepository->update(['status'=>'processed'],['id'=>$taskId]);
            Helper::scheduleNextBackgroundTask();
        }catch (RuntimeException $e){
            $this->logger->warning(self::TAG.'Unable to delete orders from TrackMage', array_merge([
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function deleteOrder($orderId)
    {
        if ($this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
            return;
        }
        $order = wc_get_order( $orderId );
        foreach ($order->get_items() as $item) { //woocommerce_delete_order_item is not fired on order delete
            $this->deleteOrderItem($item->get_id());
        }

        foreach ($this->shipmentRepository->findBy(['order_id' => $orderId]) as $shipment) {
            $this->deleteShipment($shipment['id']);
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
        if ( $this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
            return;
        }
        try {
            $this->orderItemSync->sync($itemId);

            foreach ($this->shipmentItemRepository->findBy(['order_item_id' => $itemId]) as $shipmentItem) {
                $this->syncShipmentItem($shipmentItem['id']);
            }
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote order item', array_merge([
                'item_id' => $itemId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], $this->grabGuzzleData($e)));
        }
    }

    public function deleteOrderItem($itemId)
    {
        if ($this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
            return;
        }
        try {
            foreach ($this->shipmentItemRepository->findBy(['order_item_id' => $itemId]) as $shipmentItem) {
                $this->deleteShipmentItem($shipmentItem['id']);
            }

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
        if ($this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
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
        if ($this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
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
        if ($this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
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
        if ($this->disableEvents || Helper::isBulkSynchronizationInProcess()) {
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
