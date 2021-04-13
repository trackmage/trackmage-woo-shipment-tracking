<?php

namespace TrackMage\WordPress\Synchronization;

use GuzzleHttp\Exception\ClientException;
use TrackMage\Client\TrackMageClient;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;

class ShipmentItemSync implements EntitySyncInterface
{
    use SyncSharedTrait;

    /** @var ChangesDetector */
    private $changesDetector;

    public function sync($shipmentItem)
    {
        if (empty($shipmentItem)) {
            throw new InvalidArgumentException('Unable to find shipmentItem: '. $shipmentItem);
        }
        $shipment = isset($shipmentItem['shipment']) ? $shipmentItem['shipment'] : null;
        if ($shipment === null) {
            throw new InvalidArgumentException('Unable to find shipment ');
        }
        $trackmage_id = isset($shipmentItem['id']) ? $shipmentItem['id'] : null;
        $trackmageShipmentId = $shipment['id'];
        $trackmageOrderItemId = wc_get_order_item_meta($shipmentItem['order_item_id'], '_trackmage_order_item_id', true);
        if (empty($trackmageOrderItemId)) {
            throw new SynchronizationException('Unable to sync shipment item because order item is not yet synced');
        }

        $client = Plugin::get_client();
        try {
            if (empty($trackmage_id)) {
                $response = $client->post('/shipment_items', [
                    'json' => [
                        'shipment' => '/shipments/' . $trackmageShipmentId,
                        'orderItem' => '/order_items/'.$trackmageOrderItemId,
                        'qty' => (int)$shipmentItem['qty']
                    ]
                ]);
                return TrackMageClient::item($response);
            } else {
                $response = $client->put('/shipment_items/'.$trackmage_id, [
                    'json' => [
                        'qty' => (int)$shipmentItem['qty'],
                    ]
                ]);
                return TrackMageClient::item($response);
            }
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }


    public function delete($id)
    {
        $client = Plugin::get_client();

        if (empty($id)) {
            return;
        }

        try {
            $client->delete('/shipment_items/'.$id );
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete shipmentItem: '.TrackMageClient::error($e), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }
}
