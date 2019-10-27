<?php

namespace TrackMage\WordPress\Synchronization;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentRepository;

class ShipmentSync implements EntitySyncInterface
{
    use SyncSharedTrait;

    private $source;
    private $shipmentRepo;

    /** @var ChangesDetector */
    private $changesDetector;

    /**
     * @param string|null $source
     */
    public function __construct(ShipmentRepository $shipmentRepo, $source = null)
    {
        $this->shipmentRepo = $shipmentRepo;
        $this->source = $source;
    }

    /**
     * @return ChangesDetector
     */
    private function getChangesDetector()
    {
        if (null === $this->changesDetector) {
            $detector = new ChangesDetector([
                '[tracking_number]',
            ], function(array $shipment) {
                return $shipment['hash'];
            }, function(array $shipment, $hash) {
                return $this->shipmentRepo->update(['hash' => $hash], ['id' => $shipment['id']]);
            });
            $this->changesDetector = $detector;
        }

        return $this->changesDetector;
    }

    public function sync($shipmentId)
    {
        $shipment = $this->shipmentRepo->find($shipmentId);
        if ($shipment === null) {
            throw new InvalidArgumentException('Unable to find shipment: '. $shipmentId);
        }
        $orderId = $shipment['order_id'];
        $order = wc_get_order($orderId);
        $trackmage_id = $shipment['trackmage_id'];
        if (!($this->canSyncOrder($order) && (empty($trackmage_id) || $this->getChangesDetector()->isChanged($shipment)))) {
            return;
        }
        $workspace = get_option('trackmage_workspace');
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        $trackmage_order_id = get_post_meta( $orderId, '_trackmage_order_id', true );

        try {
            if (empty($trackmage_id)) {
                try {
                    $response = $guzzleClient->post('/shipments', [
                        'query' => ['ignoreWebhookId' => $workspace],
                        'json' => [
                            'workspace' => '/workspaces/' . $workspace,
                            'trackingNumber' => $shipment['tracking_number'],
                            'originCarrier' => $shipment['carrier'] === 'auto' ? null : $shipment['carrier'],
                            'externalSyncId' => (string)$shipmentId,
                            'externalSource' => $this->source,
                            'orders' => ['/orders/'.$trackmage_order_id],
                        ]
                    ]);
                    $result = json_decode( $response->getBody()->getContents(), true );
                    $shipment = $this->shipmentRepo->update(['trackmage_id' => $result['id']], ['id' => $shipmentId]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response
                        && null !== ($query = $this->matchSearchCriteriaFromValidationError($shipment, $response))
                        && null !== ($data = $this->lookupByCriteria($query, $workspace))
                    ) {
                        $shipment = $this->shipmentRepo->update(['trackmage_id' => $data['id']], ['id' => $shipmentId]);
                        $this->sync($shipmentId);
                        return;
                    }
                    throw $e;
                }
            } else {
                try {
                    $guzzleClient->put('/shipments/'.$trackmage_id, [
                        'query' => ['ignoreWebhookId' => $workspace],
                        'json' => [
                            'trackingNumber' => $shipment['tracking_number'],
                            'orders' => ['/orders/'.$trackmage_order_id],
                        ]
                    ]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response && 404 === $response->getStatusCode()) {
                        $shipment = $this->shipmentRepo->update(['trackmage_id' => null], ['id' => $shipmentId]);
                        $this->sync($shipmentId);
                        return;
                    }
                    throw $e;
                }
            }
            $this->getChangesDetector()->lockChanges($shipment);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * @param array $shipment
     * @param ResponseInterface $response
     * @return array|null
     */
    private function matchSearchCriteriaFromValidationError(array $shipment, ResponseInterface $response)
    {
        if (400 !== $response->getStatusCode()) {
            return null;
        }
        $query = [];
        $content = $response->getBody()->getContents();
        if (false !== strpos($content, 'externalSyncId')) {
            $query['externalSyncId'] = $shipment['id'];
            $query['externalSource'] = $this->source;
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
        $query['itemsPerPage'] = 1;
        $response = $guzzleClient->get("/workspaces/{$workspace}/shipments", ['query' => $query]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);
        return isset($data['hydra:member'][0]) ? $data['hydra:member'][0] : null;
    }


    public function delete($id)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        $shipment = $this->shipmentRepo->find($id);

        $trackmage_id = $shipment['trackmage_id'];
        if (empty($trackmage_id)) {
            return;
        }
        $workspace = get_option('trackmage_workspace');

        try {
            $guzzleClient->delete('/shipments/'.$trackmage_id, ['query' => ['ignoreWebhookId' => $workspace]]);
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete shipment: '.$e->getMessage(), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        } finally {
            $shipment = $this->shipmentRepo->update(['trackmage_id' => null], ['id' => $id]);
        }
    }
}
