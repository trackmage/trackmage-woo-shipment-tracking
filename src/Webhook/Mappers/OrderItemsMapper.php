<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use WC_Order;
use WC_Order_Item;
use WC_Data_Store;

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
     */
    public function handle( array $item ) {
        try {
            $this->data = $item['data'];
            $this->updatedFields = $item['updatedFields'];
            $orderItemId = $this->data['externalSyncId'];
            $trackMageOrderId = str_replace('/orders/','', $this->data['order']);
            $trackMageOrderItemId = $this->data['id'];

            //$this->loadEntity($shipmentId, $trackMageId);

            $this->entity = $this->getOrderItem($orderItemId);

            if(!($this->canHandle() && $trackMageOrderItemId === wc_get_order_item_meta($orderItemId, '_trackmage_order_item_id', true)
                && $trackMageOrderId === get_post_meta($this->entity->get_order_id(),'_trackmage_order_id', 'true')))
                    throw new InvalidArgumentException('Order Item cannot be updated: '. $trackMageOrderItemId);

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
