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

    private $source;
    private $shipmentItemRepo;
    private $shipmentRepo;

    /** @var ChangesDetector */
    private $changesDetector;

    /**
     * @param string|null $source
     */
    public function __construct(ShipmentItemRepository $shipmentItemRepo,
                                ShipmentRepository $shipmentRepo,
                                $source = null)
    {
        $this->shipmentItemRepo = $shipmentItemRepo;
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
                '[order_item_id]', '[qty]',
            ], function(array $shipmentItem) {
                return $shipmentItem['hash'];
            }, function(array $shipmentItem, $hash) {
                return $this->shipmentItemRepo->update(['hash' => $hash], ['id' => $shipmentItem['id']]);
            });
            $this->changesDetector = $detector;
        }

        return $this->changesDetector;
    }

    public function sync($shipmentItemId)
    {
        $shipmentItem = $this->shipmentItemRepo->find($shipmentItemId);
        if ($shipmentItem === null) {
            throw new InvalidArgumentException('Unable to find shipmentItem: '. $shipmentItemId);
        }
        $shipment = $this->shipmentRepo->find($shipmentItem['shipment_id']);
        if ($shipment === null) {
            throw new InvalidArgumentException('Unable to find shipment: '. $shipmentItem['shipment_id']);
        }
        $orderId = $shipment['order_id'];
        $order = wc_get_order($orderId);
        $trackmage_id = $shipmentItem['trackmage_id'];
        if (!($this->canSyncOrder($order) && (empty($trackmage_id) || $this->getChangesDetector()->isChanged($shipmentItem)))) {
            return;
        }
        $trackmageShipmentId = $shipment['trackmage_id'];
        if (empty($trackmageShipmentId)) {
            throw new SynchronizationException('Unable to sync shipment item because shipment is not yet synced');
        }
        $trackmageOrderItemId = wc_get_order_item_meta($shipmentItem['order_item_id'], '_trackmage_order_item_id', true);
        if (empty($trackmageOrderItemId)) {
            throw new SynchronizationException('Unable to sync shipment item because order item is not yet synced');
        }

        $workspace = get_option('trackmage_workspace');
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        try {
            if (empty($trackmage_id)) {
                try {
                    $response = $guzzleClient->post('/shipment_items', [
                        'query' => ['ignoreWebhookId' => $workspace],
                        'json' => [
                            'shipment' => '/shipments/' . $trackmageShipmentId,
                            'orderItem' => '/order_items/'.$trackmageOrderItemId,
                            'qty' => (int)$shipmentItem['qty'],
                            'externalSyncId' => (string)$shipmentItemId,
                            'externalSource' => $this->source,
                        ]
                    ]);
                    $result = json_decode( $response->getBody()->getContents(), true );
                    $shipmentItem = $this->shipmentItemRepo->update(['trackmage_id' => $result['id']], ['id' => $shipmentItemId]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response
                        && null !== ($query = $this->matchSearchCriteriaFromValidationError($shipmentItem, $response))
                        && null !== ($data = $this->lookupByCriteria($query, $workspace))
                    ) {
                        $shipmentItem = $this->shipmentItemRepo->update(['trackmage_id' => $data['id']], ['id' => $shipmentItemId]);
                        $this->sync($shipmentItemId);
                        return;
                    }
                    throw $e;
                }
            } else {
                try {
                    $guzzleClient->put('/shipment_items/'.$trackmage_id, [
                        'query' => ['ignoreWebhookId' => $workspace],
                        'json' => [
                            'orderItem' => '/order_items/'.$trackmageOrderItemId,
                            'qty' => (int)$shipmentItem['qty'],
                        ]
                    ]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response && 404 === $response->getStatusCode()) {
                        $shipmentItem = $this->shipmentItemRepo->update(['trackmage_id' => null], ['id' => $shipmentItemId]);
                        $this->sync($shipmentItemId);
                        return;
                    }
                    throw $e;
                }
            }
            $this->getChangesDetector()->lockChanges($shipmentItem);
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
        if (false !== strpos($content, 'externalSyncId')) {
            $query['externalSyncId'] = $shipmentItem['id'];
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

        $shipmentItem = $this->shipmentItemRepo->find($id);

        $trackmage_id = $shipmentItem['trackmage_id'];
        if (empty($trackmage_id)) {
            return;
        }
        $workspace = get_option('trackmage_workspace');

        try {
            $guzzleClient->delete('/shipment_items/'.$trackmage_id, ['query' => ['ignoreWebhookId' => $workspace]]);
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete shipmentItem: '.$e->getMessage(), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        } finally {
            $shipmentItem = $this->shipmentItemRepo->update(['trackmage_id' => null], ['id' => $id]);
        }
    }

    public function unlink($id)
    {
        $shipmentItem = $this->shipmentItemRepo->update([ 'trackmage_id' => null, 'hash' => null ], ['id' => $id]);
    }
}
