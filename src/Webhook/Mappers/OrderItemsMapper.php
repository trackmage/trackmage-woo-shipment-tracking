<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\EndpointException;
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
     */
    public function handle( array $item ) {
        try {
            $this->data = $item['data'];
            $this->updatedFields = $item['updatedFields'];
            $shipmentId = $this->data['externalSyncId'];
            $trackMageId = $this->data['id'];

            $this->loadEntity($shipmentId, $trackMageId);

            if(!$this->canHandle())
                return false;



            //$data = $this->prepareData();

            //$this->entity = $this->repo->update($data, ['id' => $shipmentId]);

        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update order items from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

}
