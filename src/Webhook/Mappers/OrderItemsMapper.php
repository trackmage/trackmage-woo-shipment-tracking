<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\EndpointException;
use WC_Order;
use WC_Order_Item;

class OrderItemsMapper extends AbstractMapper {

    protected $map = [
        //"order" => "/orders/232ce7eb-64a9-476d-bf75-3a1803ed1ea9",
        //"productName" => "Test Product 1",
        "qty" => "_qty",
        //"price" => "_",
        "rowTotal" => "_line_total",
        //"externalSyncId" => "id",
        //"externalSource" => "wp-5d9da5faf010c",
        //"id" => "_trackmage_order_item_id"
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
            $shipmentId = $this->data['externalSyncId'];
            $trackMageId = $this->data['id'];

            $this->loadEntity($shipmentId, $trackMageId);

            if(($res = $this->canHandle()) < 0)
                return $res;

            $data = $this->prepareData();

            $this->entity = $this->repo->update($data, ['id' => $shipmentId]);

            return $this->entity;
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update shipment from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

}
