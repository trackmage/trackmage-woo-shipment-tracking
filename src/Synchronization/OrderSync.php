<?php

namespace TrackMage\WordPress\Synchronization;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use WC_Order;

class OrderSync implements EntitySyncInterface
{
    use SyncSharedTrait;

    /** @var ChangesDetector */
    private $changesDetector;

    /** @var string|null */
    private $source;

    /**
     * @param string|null $source
     */
    public function __construct($source = null)
    {
        $this->source = $source;
    }

    /**
     * @return ChangesDetector
     */
    private function getChangesDetector()
    {
        if (null === $this->changesDetector) {
            $detector = new ChangesDetector([
                '[order_number]', '[status]', '[shipping_address_1]',
            ], function($order) {
                return get_post_meta( $order['id'], '_trackmage_hash', true );
            }, function($order, $hash) {
                add_post_meta( $order['id'], '_trackmage_hash', $hash, true )
                    || update_post_meta( $order['id'], '_trackmage_hash', $hash);
                return $order;
            });
            $this->changesDetector = $detector;
        }

        return $this->changesDetector;
    }

    public function sync($order_id ) {
        $order = wc_get_order( $order_id );
        if (!$this->canSyncOrder($order) || !$this->getChangesDetector()->isChanged(new ArrayAccessDecorator($order))) {
            return;
        }
        $workspace = get_option( 'trackmage_workspace' );
        $trackmage_order_id = get_post_meta( $order_id, '_trackmage_order_id', true );
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        // Create order on TrackMage.
        try {
            if (empty($trackmage_order_id)) {
                try {
                    $response = $guzzleClient->post('/orders', [
                        'json' => [
                            'workspace' => '/workspaces/' . $workspace,
                            'externalSyncId' => (string)$order_id,
                            'externalSource' => $this->source,
                            'orderNumber' => $order->get_order_number(),
                            'address' => $order->get_shipping_address_1(),
                            'status' => ['name' => $order->get_status()],
                        ]
                    ]);
                    $result = json_decode( $response->getBody()->getContents(), true );
                    $trackmage_order_id = $result['id'];
                    add_post_meta( $order_id, '_trackmage_order_id', $trackmage_order_id, true )
                        || update_post_meta($order_id, '_trackmage_order_id', $trackmage_order_id);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response
                        && null !== ($query = $this->matchSearchCriteriaFromValidationError($order, $response))
                        && null !== ($data = $this->lookupByCriteria($query, $workspace))
                    ) {
                        add_post_meta( $order_id, '_trackmage_order_id', $data['id'], true )
                            || update_post_meta($order_id, '_trackmage_order_id', $data['id']);
                        $this->sync($order_id);
                        return;
                    }
                    throw $e;
                }
            } else {
                try {
                    $guzzleClient->put("/orders/{$trackmage_order_id}", [
                        'json' => [
                            'status' => ['name' => $order->get_status()],
                        ]
                    ]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response && 404 === $response->getStatusCode()) {
                        delete_post_meta( $order_id, '_trackmage_order_id');
                        $this->sync($order_id);
                        return;
                    }
                    throw $e;
                }
            }
            $this->getChangesDetector()->lockChanges(new ArrayAccessDecorator($order));
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param WC_Order $order
     * @param ResponseInterface $response
     * @return array|null
     */
    private function matchSearchCriteriaFromValidationError(WC_Order $order, ResponseInterface $response)
    {
        if (400 !== $response->getStatusCode()) {
            return null;
        }
        $query = [];
        $content = $response->getBody()->getContents();
        if (false !== strpos($content, 'externalSyncId')) {
            $query['externalSyncId'] = $order->get_id();
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
        $response = $guzzleClient->get("/workspaces/{$workspace}/orders", ['query' => $query]);
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);
        return isset($data['hydra:member'][0]) ? $data['hydra:member'][0] : null;
    }

    public function delete($id)
    {
        $client = Plugin::get_client();
        $guzzleClient = $client->getGuzzleClient();

        $trackmage_order_id = get_post_meta( $id, '_trackmage_order_id', true );
        if (empty($trackmage_order_id)) {
            return;
        }
        try {
            $guzzleClient->delete('/orders/'.$trackmage_order_id);
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete order: '.$e->getMessage(), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        } finally {
            delete_post_meta($id, '_trackmage_order_id');
        }
    }
}
