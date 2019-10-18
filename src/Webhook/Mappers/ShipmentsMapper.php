<?php


namespace TrackMage\WordPress\Webhook\Mappers;


use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Repository\ShipmentRepository;

class ShipmentsMapper extends AbstractMapper {

    protected $map = [
        "id"                        =>  "trackmage_id",
        "trackingNumber"            =>  "tracking_number",
        "status"                    =>  "status",
        "email"                     =>  "",
        "daysInTransit"             =>  "",
        "originCarrier"             =>  "carrier",
        "destinationCarrier"        =>  "",
        "originCountry"             =>  "",
        "destinationCountry"        =>  "",
        "originCountryIso2"         =>  "",
        "destinationCountryIso2"    =>  "",
        "shippedAt"                 =>  "",
        "expectedDeliveryDate"      =>  "",
        "createdAt"                 =>  "",
        "updatedAt"                 =>  "",
        "lastStatusUpdate"          =>  "",
        "workspace"                 =>  "",
        "review"                    =>  "",
        "reviewTotalScore"          =>  "",
        "externalSource"            =>  "",
        "externalSyncId"            =>  "id",
        "address"                   =>  ""
    ];

    /**
     * ShipmentsMapper constructor.
     *
     * @param ShipmentRepository $shipmentRepo
     * @param null $source
     */
    public function __construct(ShipmentRepository $shipmentRepo, $source = null) {
        $this->repo = $shipmentRepo;
        $this->source = $source;
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

            if(!$this->canHandle())
                return null;

            $data = $this->prepareData();

            $this->entity = $this->repo->update($data, ['id' => $shipmentId]);

            return $this->entity;
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

}
