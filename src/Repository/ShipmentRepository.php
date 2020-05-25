<?php

namespace TrackMage\WordPress\Repository;

use TrackMage\WordPress\Synchronization\EntitySyncInterface;

class ShipmentRepository extends AbstractApiRepository
{
      const API_ENDPOINT = '/shipments';

      public function __construct(EntitySyncInterface $entitySync) {
          parent::__construct($entitySync);
          $this->apiEndpoint = self::API_ENDPOINT;
      }
}
