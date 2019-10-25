<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use WC_Order;
use WC_Order_Item;

class OrderItemsMapper extends AbstractMapper {

    protected $map = [
        "qty" => "_qty",
        "rowTotal" => "_line_total",
    ];

    /**
     * @param array $item
     *
     * @return bool
     */
    public function supports( array $item ) {
        return isset($item['entity']) && $item['entity'] == 'order_items';
    }

    /**
     * Handle updates for order items from TrackMage to local
     *
     * @param array $item
     *
     * @throws InvalidArgumentException|EndpointException
     */
    public function handle( array $item ) {

        $this->data = isset( $item['data'] ) ? $item['data'] : [];
        if ( empty( $this->data ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because data is empty' );
        }
        $this->updatedFields = isset( $item['updatedFields'] ) ? $item['updatedFields'] : [];
        if ( empty( $this->updatedFields ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because there are no updated fields' );
        }

        $trackMageOrderItemId = isset($this->data['id'])?$this->data['id']:'';
        if ( empty( $trackMageOrderItemId ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because there is no TrackMage Order Item Id' );
        }

        $trackMageOrderId = str_replace('/orders/','', $this->data['order']);
        if ( empty( $trackMageOrderId ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because there is no TrackMage Order Id' );
        }

        $orderItemId = isset( $this->data['externalSyncId'] ) ? $this->data['externalSyncId'] : '';
        if ( empty( $orderId ) ) {
            throw new InvalidArgumentException( 'Unable to handle order because there is no externalSyncId' );
        }

        if($trackMageOrderItemId !== wc_get_order_item_meta($orderItemId, '_trackmage_order_item_id', true)) {
            throw new EndpointException( 'Unable to handle order item because TrackMage Order Item Id does not match' );
        }


        $this->entity = $this->getOrderItem($orderItemId);

        if($trackMageOrderId === get_post_meta($this->entity->get_order_id(),'_trackmage_order_id', 'true')) {
            throw new EndpointException('Unable to handle order item because TrackMage Order Id does not match');
        }

        $this->canHandle();

        try {
            foreach ($this->updatedFields as $field) {
                if ( isset( $this->map[ $field ] ) ) {
                    wc_update_order_item_meta($orderItemId, $this->map[ $field ], $this->data[ $field ]);
                }
            }
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update order items from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param int $orderItemId
     * @param WC_Order $order
     * @return WC_Order_Item|\WC_Order_Item_Product
     */
    private function findOrderItemInOrder($orderItemId, WC_Order $order)
    {
        foreach( $order->get_items() as $id => $item ) {
            if ($id === $orderItemId) {
                return $item;
            }
        }
        return null;
    }

    public function getOrderItem($orderItemId)
    {
        $orderId = wc_get_order_id_by_order_item_id($orderItemId);
        $order = wc_get_order($orderId);
        $item = $this->findOrderItemInOrder($orderItemId, $order);
        if ($item === null) {
            throw new InvalidArgumentException('Unable to find order item id: '. $orderItemId);
        }
        return $item;
    }
}
