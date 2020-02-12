<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use WC_Order_Item;
use WC_Data_Store;
use TrackMage\WordPress\Exception\EndpointException;

class ShipmentItemsMapper extends AbstractMapper {

    protected $map = [
        "orderItem"         => "order_item_id",
        "qty"               => "qty",
    ];

    /**
     * ShipmentsMapper constructor.
     *
     * @param ShipmentItemRepository $shipmentItenRepo
     * @param string|null $integration
     */
    public function __construct(ShipmentItemRepository $shipmentItemRepo, $integration = null) {
        $this->repo = $shipmentItemRepo;
        parent::__construct($integration);
    }

    /**
     * @param array $item
     *
     * @return bool
     */
    public function supports( array $item ) {
        return isset($item['entity']) && $item['entity'] === 'shipment_items';
    }

    /**
     * Handle updates for shipment items from TrackMage to local
     *
     * @param array $item
     */
    public function handle( array $item ) {
        $this->data = isset( $item['data'] ) ? $item['data'] : [];
        if ( empty( $this->data ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment item because data is empty' );
        }
        $this->updatedFields = isset( $item['updatedFields'] ) ? $item['updatedFields'] : [];
        if ( empty( $this->updatedFields ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment item because there are no updated fields' );
        }
        $shipmentItemId = isset( $this->data['externalSyncId'] ) ? $this->data['externalSyncId'] : '';
        if ( empty( $shipmentItemId ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment item because there is no externalSyncId' );
        }
        $trackMageId = $this->data['id'];
        if ( empty( $trackMageId ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment item because there is no TrackMage Id' );
        }

        $this->loadEntity( $shipmentItemId, $trackMageId );

        $this->validateData();

        $data = $this->prepareData();

        try{
            $this->entity = $this->repo->update( $data, [ 'id' => $shipmentItemId ] );
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during handle: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function prepareData() {
        $data = parent::prepareData();

        if(isset($data["order_item_id"])){
            $data["order_item_id"] = $this->getOrderItemIdByTrackMageId($data["order_item_id"]);
        }

        return $data;
    }

    /**
     * @param string $trackMageOrderItemId
     *
     * @return int
     */

    private function getOrderItemIdByTrackMageId($trackMageOrderItemId){
        $trackmageOrderItemId = str_replace('/order_items/','', $trackMageOrderItemId);
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . 'woocommerce_order_itemmeta'." WHERE meta_key = '_trackmage_order_item_id' AND meta_value = '".$trackmageOrderItemId."'", ARRAY_A);
        if(is_array($row) && isset($row['order_item_id']))
            return (int) $row['order_item_id'];
        else
            throw new EndpointException('Order item was not found.');
    }

}
