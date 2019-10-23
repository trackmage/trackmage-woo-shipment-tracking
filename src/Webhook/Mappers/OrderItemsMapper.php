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

            $this->entity = null;

            if(!$this->canHandle())
                throw new InvalidArgumentException('Order Item cannot be updated: '. $trackMageOrderItemId);



            //$data = $this->prepareData();

            //$this->entity = $this->repo->update($data, ['id' => $shipmentId]);

        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update order items from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string $trackMageOrderItemId
     * @return WC_Order_Item|\WC_Order_Item_Product
     */
    private function getOrderItem($trackMageOrderItemId)
    {
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . 'woocommerce_order_itemmeta'." WHERE meta_key = '_trackmage_order_item_id' AND meta_value = '".$trackMageOrderItemId."'", ARRAY_A);
        if(is_array($row) && isset($row['order_item_id']))
            return $data["order_item_id"] = $row['order_item_id'];
        else
            throw new EndpointException('Order item was not found.',400);
    }
}
