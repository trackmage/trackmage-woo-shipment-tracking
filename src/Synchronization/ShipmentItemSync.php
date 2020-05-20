<?php

namespace TrackMage\WordPress\Synchronization;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use TrackMage\WordPress\Repository\ShipmentRepository;

class ShipmentItemSync implements EntitySyncInterface
{
    use SyncSharedTrait;

    private $integration;

    /** @var ChangesDetector */
    private $changesDetector;

    /**
     * @param string|null $source
     */
    public function __construct($integration = null)
    {
        $this->integration = '/workflows/'.$integration;
    }

    public function sync($shipmentItem)
    {
        if (empty($shipmentItem)) {
            throw new InvalidArgumentException('Unable to find shipmentItem: '. $shipmentItem);
        }
        $shipment = isset($shipmentItem['shipment']) ? $shipmentItem['shipment'] : null;
        if ($shipment === null) {
            throw new InvalidArgumentException('Unable to find shipment: '. $shipmentItem['shipment']);
        }
        $orderId = $shipment['order_id'];
        $order = wc_get_order($orderId);
        $trackmage_id = $shipmentItem['trackmage_id'];
        $trackmageShipmentId = $shipment['id'];
        $trackmageOrderItemId = wc_get_order_item_meta($shipmentItem['order_item_id'], '_trackmage_order_item_id', true);
        if (empty($trackmageOrderItemId)) {
            throw new SynchronizationException('Unable to sync shipment item because order item is not yet synced');
        }

        $workspace = get_option('trackmage_workspace');
        $webhookId = get_option('trackmage_webhook', '');

        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        try {
            if (empty($trackmage_id)) {
                try {
                    $response = $guzzleClient->post('/shipment_items', [
                        'query' => ['ignoreWebhookId' => $webhookId],
                        'json' => [
                            'shipment' => '/shipments/' . $trackmageShipmentId,
                            'orderItem' => '/order_items/'.$trackmageOrderItemId,
                            'qty' => (int)$shipmentItem['qty'],
                            'externalSourceIntegration' => $this->integration,
                        ]
                    ]);
                    return json_decode( $response->getBody()->getContents(), true );
                } catch (ClientException $e) {
                    throw $e;
                }
            } else {
                try {
                    $response = $guzzleClient->put('/shipment_items/'.$trackmage_id, [
                        'query' => ['ignoreWebhookId' => $webhookId],
                        'json' => [
                            'orderItem' => '/order_items/'.$trackmageOrderItemId,
                            'qty' => (int)$shipmentItem['qty'],
                        ]
                    ]);
                    return json_decode( $response->getBody()->getContents(), true );
                } catch (ClientException $e) {
                    throw $e;
                }
            }
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * @param array $shipmentItem
     * @param ResponseInterface $response
     * @return array|null
     */
    private function matchSearchCriteriaFromValidationError(array $shipmentItem, ResponseInterface $response)
    {
        if (400 !== $response->getStatusCode()) {
            return null;
        }
        $query = [];
        $content = $response->getBody()->getContents();
        if (false !== strpos($content, 'externalSourceSyncId')) {
            $query['externalSourceSyncId'] = $shipmentItem['id'];
            $query['externalSourceIntegration'] = $this->integration;
        } else {
            return null;
        }

        return $query;
    }

    /**
     * @param array $query
     * @param string $workspace
     * @return array|null
     */
    private function lookupByCriteria(array $query, $workspace)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();
        $query['workspace.id'] = $workspace;
        $query['itemsPerPage'] = 1;
        $response = $guzzleClient->get('/shipment_items', ['query' => $query]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);
        return isset($data['hydra:member'][0]) ? $data['hydra:member'][0] : null;
    }


    public function delete($id)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        if (empty($id)) {
            return;
        }
        $webhookId = get_option('trackmage_webhook', '');

        try {
            $guzzleClient->delete('/shipment_items/'.$id, ['query' => ['ignoreWebhookId' => $webhookId]]);
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete shipmentItem: '.$e->getMessage(), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }
}
