<?php


namespace TrackMage\WordPress\Webhook\Mappers;

use TrackMage\WordPress\Repository\ShipmentRepository;

class ShipmentsMapper extends AbstractMapper {

    protected $map = [
        "trackingNumber"            =>  "tracking_number",
        "status"                    =>  "status",
        "originCarrier"             =>  "carrier",
    ];

    /**
     * ShipmentsMapper constructor.
     *
     * @param ShipmentRepository $shipmentRepo
     * @param string|null $source
     */
    public function __construct(ShipmentRepository $shipmentRepo, $source = null) {
        $this->repo = $shipmentRepo;
        parent::__construct($source);
    }

    /**
     * @param array $item
     *
     * @return bool
     */
    public function supports( array $item ) {
        return isset($item['entity']) && $item['entity'] == 'shipments';
    }

    /**
     * Handle updates for shipment from TrackMage to local
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

            if( ! $this->canHandle() )
                throw new InvalidArgumentException('Shipment cannot be updated: '. $shipmentId);

            $data = $this->prepareData();

            $this->entity = $this->repo->update($data, ['id' => $shipmentId]);

        }catch (\Throwable $e){
            throw new EndpointException('An error happened during update shipment from TrackMage: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

}
