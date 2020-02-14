<?php


namespace TrackMage\WordPress\Webhook\Mappers;

use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Repository\ShipmentRepository;

class ShipmentsMapper extends AbstractMapper {

    protected $map = [
        "trackingNumber"            =>  "tracking_number",
        "trackingStatus"            =>  "status",
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
        return isset($item['entity']) && $item['entity'] === 'shipments';
    }

    /**
     * Handle updates for shipment from TrackMage to local
     *
     * @param array $item
     */
    public function handle( array $item ) {
        $this->data = isset( $item['data'] ) ? $item['data'] : [];
        if ( empty( $this->data ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment because data is empty' );
        }
        $this->updatedFields = isset( $item['updatedFields'] ) ? $item['updatedFields'] : [];
        if ( empty( $this->updatedFields ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment because there are no updated fields' );
        }

        $trackMageId = $this->data['id'];
        if ( empty( $trackMageId ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment because there is no TrackMage Id' );
        }

        $shipmentId = isset( $this->data['externalSyncId'] ) ? $this->data['externalSyncId'] : '';
        if ( empty( $shipmentId ) ) {
            throw new InvalidArgumentException( 'Unable to handle shipment because there is no externalSyncId' );
        }

        $this->loadEntity( $shipmentId, $trackMageId );

        $this->validateData();

        $data = $this->prepareData();

        try{
            $this->entity = $this->repo->update( $data, [ 'id' => $shipmentId ] );
        }catch (\Throwable $e){
            throw new EndpointException('An error happened during handle: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function validateData() {
        // check if workspace is correct
        if(!isset($this->data['workspace']) || "/workspaces/".$this->workspace !== $this->data['workspace'])
            throw new InvalidArgumentException('Unable to handle because workspace is not correct');

        parent::validateData();
    }

}
