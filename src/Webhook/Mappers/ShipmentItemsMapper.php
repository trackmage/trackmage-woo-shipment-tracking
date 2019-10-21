<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Repository\ShipmentItemRepository;

class ShipmentItemsMapper extends AbstractMapper {

    protected $map = [
        //"shipment"          => "/shipments/26a5b79a-d8c2-4830-b872-9248e1f6b954",
        //"orderItem"         => "/order_items/dd314342-d9af-4431-a3fe-66916663e748",
        "qty"               => "qty",
        //"externalSyncId"    => "1",
        //"externalSource"    => "wp-5d9da5faf010c",
        //"id"                => "trackmage_id",
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

            if(($res = $this->canHandle()) < 0)
                return $res;

            $data = $this->prepareData();

            $this->entity = $this->repo->update($data, ['id' => $shipmentItemId]);

            return $this->entity;
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update shipment from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

}
