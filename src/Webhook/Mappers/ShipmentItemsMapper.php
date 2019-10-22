<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use WC_Order_Item;
use WC_Data_Store;

class ShipmentItemsMapper extends AbstractMapper {

    protected $map = [
        "orderItem"         => "order_item_id",
        "qty"               => "qty",
    ];

    /**
     * ShipmentsMapper constructor.
     *
     * @param ShipmentItemRepository $shipmentItenRepo
     * @param string|null $source
     */
    public function __construct(ShipmentItemRepository $shipmentItemRepo, $source = null) {
        $this->repo = $shipmentItemRepo;
        parent::__construct($source);
    }

    /**
     * @param array $item
     *
     * @return bool
     */
    public function supports( array $item ) {
        return isset($item['entity']) && $item['entity'] == 'shipment_items';
    }

    /**
     * Handle updates for shipment items from TrackMage to local
     *
     * @param array $item
     */
    public function handle( array $item ) {
        try {
            $this->data = $item['data'];
            $this->updatedFields = $item['updatedFields'];
            $shipmentItemId = $this->data['externalSyncId'];
            $trackMageId = $this->data['id'];

            $this->loadEntity($shipmentItemId, $trackMageId);

            if($this->canHandle())
                return false;

            $data = $this->prepareData();

            $this->entity = $this->repo->update($data, ['id' => $shipmentItemId]);

        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update shipment from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function prepareData() {
        $data = parent::prepareData();

        if(isset($data["orderItem"])){
            $trackmageOrderItemId = str_replace('/order_items/','', $data["orderItem"]);
            global $wpdb;
            $row = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . 'woocommerce_order_itemmeta'." WHERE meta_key = '_trackmage_order_item_id' AND meta_value = '".$trackmageOrderItemId."'", ARRAY_A);
            if(is_array($row) && isset($row['order_item_id']))
                $data["orderItem"] = $row['order_item_id'];
            else
                throw new EndpointException('Order item was not found.',400);
        }
        return $data;
    }

}
