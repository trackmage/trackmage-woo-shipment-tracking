<?php

namespace TrackMage\WordPress;

use TrackMage\WordPress\Syncrhonization\OrderSync;

class Synchronizer
{
    /** @var bool ignore events */
    private $disableEvents = false;

    /** @var OrderSync|null */
    private $orderSync;

    public function __construct()
    {
        $this->bindEvents();
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
        $this->getOrderSync()->sync($order_id);
    }

    public function deleteOrder( $order_item_id )
    {
//        $this->getOrderSync()->delete($order_id);
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
