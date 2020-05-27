<?php

namespace TrackMage\WordPress;

use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Throwable;
use TrackMage\WordPress\Exception\RuntimeException;
use TrackMage\WordPress\Repository\BackgroundTaskRepository;
use TrackMage\WordPress\Synchronization\OrderItemSync;
use TrackMage\WordPress\Synchronization\OrderSync;

class Synchronizer
{
    const TAG = '[Synchronizer]';

    /** @var bool ignore events */
    private $disableEvents = false;

    private $orderSync;
    private $orderItemSync;
    private $backgroundTaskRepository;

    private $logger;

    public function __construct(LoggerInterface $logger, OrderSync $orderSync, OrderItemSync $orderItemSync,
                                BackgroundTaskRepository $backgroundTaskRepository)
    {
        $this->logger = $logger;
        $this->orderSync = $orderSync;
        $this->orderItemSync = $orderItemSync;
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

        add_action( 'trackmage_bulk_orders_sync', [$this, 'bulkOrdersSync'], 10, 2);
        add_action( 'trackmage_delete_data', [$this, 'deleteData'], 10, 2);

    }

    public function bulkOrdersSync($orderIds = [], $taskId = null){
        try{
            $this->logger->info(self::TAG.'Start to processing orders', ['orderIds'=>$orderIds,'taskId'=>$taskId]);
            if($taskId !== null)
                $this->backgroundTaskRepository->update(['status'=>'processing'],['id'=>$taskId]);

            foreach ($orderIds as $orderId){
                delete_post_meta( $orderId, '_trackmage_hash');
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

    public function syncOrder($orderId, $forse = false ) {
        $this->logger->info(self::TAG.'Try to sync order.', [
            'order_id' => $orderId,
            'forse' => $forse
        ]);
        if ($this->disableEvents) {
            $this->logger->info(self::TAG.'Events are disabled. Sync is skipped.', [
                'order_id' => $orderId,
            ]);
            return;
        }
        try {
            $this->orderSync->sync($orderId, $forse);

            $order = wc_get_order( $orderId );
            foreach ($order->get_items() as $item) {
                $this->syncOrderItem($item->get_id(), $forse);
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
                $this->deleteOrder($orderId);
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

    public function unlinkOrder($orderId)
    {
        if ($this->disableEvents) {
            return;
        }
        $order = wc_get_order( $orderId );
        foreach ($order->get_items() as $item) {
            $this->unlinkOrderItem($item->get_id());
        }
        try {
            $this->orderSync->unlink($orderId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to delete remote order', [
                'order_id' => $orderId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function syncOrderItem($itemId, $forse = false)
    {
        if ( $this->disableEvents) {
            return;
        }
        try {
            $this->orderItemSync->sync($itemId, $forse);
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

    public function unlinkOrderItem($itemId)
    {
        if ($this->disableEvents) {
            return;
        }
        try{
            $this->orderItemSync->unlink($itemId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to unlink order item', [
                'item_id' => $itemId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
