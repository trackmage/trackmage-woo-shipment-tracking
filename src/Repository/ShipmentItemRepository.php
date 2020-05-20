<?php

namespace TrackMage\WordPress\Repository;

use TrackMage\WordPress\Synchronization\EntitySyncInterface;

class ShipmentItemRepository extends AbstractApiRepository
{
    const API_ENDPOINT = '/shipment_items';

    public function __construct(EntitySyncInterface $entitySync) {
        parent::__construct($entitySync);
        $this->apiEndpoint = self::API_ENDPOINT;
    }
}
