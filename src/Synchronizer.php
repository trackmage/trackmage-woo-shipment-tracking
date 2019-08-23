<?php

namespace TrackMage\WordPress;

use TrackMage\WordPress\Exception\RuntimeException;
use TrackMage\WordPress\Syncrhonization\OrderItemSync;
use TrackMage\WordPress\Syncrhonization\OrderSync;

class Synchronizer
{
    /** @var bool ignore events */
    private $disableEvents = false;

    /** @var OrderSync|null */
    private $orderSync;

    /** @var OrderItemSync|null */
    private $orderItemSync;

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

    public function syncOrder( $order_id ) {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->getOrderSync()->sync($order_id);
        } catch (RuntimeException $e) {
            //log error
        }
    }

    public function deleteOrder( $order_id )
    {
        if ($this->disableEvents) {
            return;
        }
        $order = wc_get_order( $order_id );
        foreach ($order->get_items() as $item) { //woocommerce_delete_order_item is not fired on order delete
            $this->deleteOrderItem($item->get_id());
        }
        try {
            $this->getOrderSync()->delete($order_id);
        } catch (RuntimeException $e) {
            //log error
        }
    }

    public function syncOrderItem( $order_item_id )
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->getOrderItemSync()->sync($order_item_id);
        } catch (RuntimeException $e) {
            //log error
        }
    }

    public function deleteOrderItem( $item_id )
    {
        if ($this->disableEvents) {
            return;
        }
        try {
            $this->getOrderItemSync()->delete($item_id);
        } catch (RuntimeException $e) {
            //log error
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
            $this->orderSync = new OrderSync();
        }
        return $this->orderSync;
    }

    /**
     * @return OrderItemSync
     */
    private function getOrderItemSync()
    {
        if (null === $this->orderItemSync) {
            $this->orderItemSync = new OrderItemSync();
        }
        return $this->orderItemSync;
    }
}
