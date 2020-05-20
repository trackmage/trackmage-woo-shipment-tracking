<?php

namespace TrackMage\WordPress\Synchronization;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use TrackMage\Client\Swagger\ApiException;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentRepository;

class ShipmentSync implements EntitySyncInterface
{
    use SyncSharedTrait;

    private $integration;
    private $shipmentRepo;

    /** @var ChangesDetector */
    private $changesDetector;

    /**
     * @param string|null $integration
     */
    public function __construct($integration = null)
    {
        $this->integration = '/workflows/'.$integration;
    }

    public function sync($shipment)
    {
        if (empty($shipment)) {
            throw new InvalidArgumentException('Shipment should not be empty');
        }
        $orderId = $shipment['order_id'];
        $order = wc_get_order($orderId);
        $trackmage_id = isset($shipment['id']) ? $shipment['id'] : null;

        $workspace = get_option('trackmage_workspace');
        $webhookId = get_option('trackmage_webhook', '');

        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        $trackmage_order_id = get_post_meta( $orderId, '_trackmage_order_id', true );

        try {
            if (empty($trackmage_id)) {
                try {
                    $data = [
                        'workspace' => '/workspaces/' . $workspace,
                        'trackingNumber' => $shipment['tracking_number'] === '' ? null : $shipment['tracking_number'],
                        'originCarrier' => $shipment['carrier'] === 'auto' ? null : $shipment['carrier'],
                        'externalSourceIntegration' => $this->integration,
                        'email' => $order->get_billing_email(),
                        'phoneNumber' => $order->get_billing_phone(),
                        'orders' => ['/orders/'.$trackmage_order_id],
                    ];
                    if(isset($shipment['items'])){
                        $data['shipmentItems'] = $this->getShipmentItemsForSync($shipment['items'], $order->get_items());
                    }
                    $response = $guzzleClient->post('/shipments', [
                        'headers' => [
                            'Content-Type' => 'application/ld+json'
                        ],
                        'body' => \GuzzleHttp\json_encode($data)
                    ]);
                    return json_decode( $response->getBody()->getContents(), true );
                } catch (ClientException $e) {
                    $contents = $e->getResponse()->getBody()->getContents();
                    $data = \json_decode($contents, true);
                    throw new SynchronizationException($data['hydra:description'], $e->getCode(), $e);
                }
            } else {
                try {
                    $data = [
                        'trackingNumber' => $shipment['tracking_number'],
                        'email'          => $order->get_billing_email(),
                        'phoneNumber'    => $order->get_billing_phone(),
                        'orders'         => [ '/orders/' . $trackmage_order_id ],
                    ];
                    if ( isset( $shipment['items'] ) ) {
                        $data['shipmentItems'] = $this->getShipmentItemsForSync( $shipment['items'], $order->get_items() );
                    }
                    $response = $guzzleClient->put( '/shipments/' . $trackmage_id, [
                        'headers' => [
                            'Content-Type' => 'application/ld+json'
                        ],
                        'body' => \GuzzleHttp\json_encode($data)
                    ] );

                    return json_decode( $response->getBody()->getContents(), true );
                } catch (ClientException $e) {
                    $contents = $e->getResponse()->getBody()->getContents();
                    $data = \json_decode($contents, true);
                    throw new SynchronizationException($data['hydra:description'], $e->getCode(), $e);
                }
            }
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public function delete($id)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();
        try {
            $guzzleClient->delete('/shipments/'.$id);
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete shipment: '.$e->getMessage(), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    private function getShipmentItemsForSync( array $items, array $orderItems ) {
        return array_map(function($item) use ($orderItems){
            $newItem = [];
            $newItem['qty'] = (int)$item['qty'];
            if (isset($item['id']) && !empty($item['id'])){
                $newItem['@id'] = "/shipment_items/{$item['id']}";
            }
            $tmOrderItemId = wc_get_order_item_meta($item['order_item_id'], '_trackmage_order_item_id', true);
            if (empty($tmOrderItemId)){
                throw new SynchronizationException('Unable to sync shipment item because order item is not yet synced');
            }
            $newItem['orderItem'] = "/order_items/{$tmOrderItemId}";
            return $newItem;
        }, $items);

    }
}
