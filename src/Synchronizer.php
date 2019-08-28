<?php

namespace TrackMage\WordPress;

use Psr\Log\LoggerInterface;
use TrackMage\WordPress\Exception\RuntimeException;
use TrackMage\WordPress\Syncrhonization\OrderItemSync;
use TrackMage\WordPress\Syncrhonization\OrderSync;

class Synchronizer
{
    const SOURCE = 'wp';
    const TAG = '[Synchronizer]';

    /** @var bool ignore events */
    private $disableEvents = false;

    /** @var OrderSync|null */
    private $orderSync;

    /** @var OrderItemSync|null */
    private $orderItemSync;

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
    }

    public function syncOrder($orderId ) {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->getOrderSync()->sync($orderId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote order', ['order_id' => $orderId]);
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
            $this->getOrderSync()->delete($orderId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to delete remote order', ['order_id' => $orderId]);
        }
    }

    public function syncOrderItem($itemId )
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->getOrderItemSync()->sync($itemId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to sync remote order item', ['item_id' => $itemId]);
        }
    }

    public function deleteOrderItem($itemId )
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->getOrderItemSync()->delete($itemId);
        } catch (RuntimeException $e) {
            $this->logger->warning(self::TAG.'Unable to delete remote order item', ['item_id' => $itemId]);
        }
    }

    public function syncShipment($shipment_id)
    {
        
    }

    public function deleteShipment($shipment_id)
    {

    }

    public function syncShipmentItem($shipment_item_id)
    {

    }

    public function deleteShipmentItem($shipment_item_id)
    {

    }

    /**
     * @return OrderSync
     */
    private function getOrderSync()
    {
        if (null === $this->orderSync) {
            $this->orderSync = new OrderSync(self::SOURCE);
        }
        return $this->orderSync;
    }

    /**
     * @return OrderItemSync
     */
    private function getOrderItemSync()
    {
        if (null === $this->orderItemSync) {
            $this->orderItemSync = new OrderItemSync(self::SOURCE);
        }
        return $this->orderItemSync;
    }
}
