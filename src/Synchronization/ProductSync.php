<?php

namespace TrackMage\WordPress\Synchronization;

use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use TrackMage\Client\TrackMageClient;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;

class ProductSync implements EntitySyncInterface
{
    const TRACKMAGE_PRODUCT_ID_META_KEY = '_trackmage_product_id';

    /** @var ChangesDetector */
    private $changesDetector;

    /** @var string|null */
    private $integration;

    /**
     * @param string|null $integration
     */
    public function __construct($integration = null)
    {
        $this->integration = '/workflows/'.$integration;
    }

    /**
     * @return ChangesDetector
     */
    private function getChangesDetector()
    {
        if (null === $this->changesDetector) {
            $detector = new ChangesDetector([
                '[name]', '[slug]', '[sku]', '[image_id]',
            ], function($product) {
                return get_post_meta( $product['id'], '_trackmage_hash', true );
            }, function($product, $hash) {
                add_post_meta( $product['id'], '_trackmage_hash', $hash, true )
                || update_post_meta($product['id'], '_trackmage_hash', $hash);
                return $product;
            });
            $this->changesDetector = $detector;
        }

        return $this->changesDetector;
    }

	public function sync( $id, $force = false ) {
        /** @var \WC_Product $product */
        $product = $this->getProduct($id);
        $team = get_option( 'trackmage_team' );
        if (!$product) {
            throw new SynchronizationException("An error happened during product synchronization. Product with ID: {$id} not found", 404);
        }
        if (!$team) {
            throw new SynchronizationException("An error happened during product synchronization. TrackMage Team Id is not set. Please reconnect your workspace.", 400);
        }
        if ($force !== true && !$this->getChangesDetector()->isChanged(new ArrayAccessDecorator($product))) {
            return;
        }
        $productId = $product->get_id();
        $webhookId = get_option('trackmage_webhook', '');
        $trackmage_product_id = get_post_meta( $productId, self::TRACKMAGE_PRODUCT_ID_META_KEY, true );
        $client = Plugin::get_client();

        // Create product on TrackMage.
        try {
            if (empty($trackmage_product_id)) {
                try {
                    $productImageUrl = $this->getProductImageUrl($product);
                    $response = $client->post('/products', [
                        'query' => ['ignoreWebhookId' => $webhookId],
                        'json' => [
                            'team' => $team,
                            'externalSourceSyncId' => (string)$productId,
                            'externalSourceIntegration' => $this->integration,
                            'name' => $product->get_name(),
                            'sku' => $product->get_sku(),
                            'originUrl' => get_the_permalink($productId),
                            'imageUrl' => $productImageUrl !== false ? $productImageUrl : null,
                        ]
                    ]);
                    $result = TrackMageClient::item($response);
                    $trackmage_product_id = $result['id'];
                    add_post_meta( $productId, self::TRACKMAGE_PRODUCT_ID_META_KEY, $trackmage_product_id, true )
                    || update_post_meta($productId, self::TRACKMAGE_PRODUCT_ID_META_KEY, $trackmage_product_id);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response
                        && null !== ($query = $this->matchSearchCriteriaFromValidationError($product, $response))
                        && null !== ($data = $this->lookupByCriteria($query))
                    ) {
                        add_post_meta( $productId, self::TRACKMAGE_PRODUCT_ID_META_KEY, $data['id'], true )
                        || update_post_meta($productId, self::TRACKMAGE_PRODUCT_ID_META_KEY, $data['id']);
                        $this->sync($productId);
                        return;
                    }
                    throw new SynchronizationException(TrackMageClient::error($e), $e->getCode(), $e);
                }
            } else {
                try {
                    $productImageUrl = $this->getProductImageUrl($product);
                    $client->put("/products/{$trackmage_product_id}", [
                        'query' => ['ignoreWebhookId' => $webhookId],
                        'json' => [
                            'name' => $product->get_name(),
                            'sku' => $product->get_sku(),
                            'originUrl' => get_the_permalink($productId),
                            'imageUrl' => $productImageUrl !== false ? $productImageUrl : null,
                        ]
                    ]);
                } catch (ClientException $e) {
                    $response = $e->getResponse();
                    if (null !== $response && 404 === $response->getStatusCode()) {
                        delete_post_meta( $productId, self::TRACKMAGE_PRODUCT_ID_META_KEY );
                        $this->sync($productId);
                        return;
                    }
                    throw new SynchronizationException(TrackMageClient::error($e), $e->getCode(), $e);
                }
            }
            $this->getChangesDetector()->lockChanges(new ArrayAccessDecorator($product));

        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        }
	}

    public function delete( $id ) {
        $client = Plugin::get_client();

        $trackmage_product_id = get_post_meta( $id, self::TRACKMAGE_PRODUCT_ID_META_KEY, true );
        if (empty($trackmage_product_id)) {
            return;
        }
        $webhookId = get_option('trackmage_webhook', '');

        try {
            $client->delete('/products/'.$trackmage_product_id, ['query' => ['ignoreWebhookId' => $webhookId]]);
        } catch ( ClientException $e ) {
            throw new SynchronizationException('Unable to delete product: '.TrackMageClient::error($e), $e->getCode(), $e);
        } catch ( \Throwable $e ) {
            throw new SynchronizationException('An error happened during synchronization: '.$e->getMessage(), $e->getCode(), $e);
        } finally {
            delete_post_meta($id, self::TRACKMAGE_PRODUCT_ID_META_KEY );
        }
	}


    public function unlink($id)
    {
        delete_post_meta( $id, self::TRACKMAGE_PRODUCT_ID_META_KEY );
        delete_post_meta( $id, '_trackmage_hash');
    }

    /**
     * @return array|null
     */
    private function matchSearchCriteriaFromValidationError(\WC_Product $product, ResponseInterface $response)
    {
        if (400 !== $response->getStatusCode()) {
            return null;
        }
        $query = [];
        $content = $response->getBody()->getContents();
        if (false !== strpos($content, 'externalSourceSyncId')) {
            $query['externalSourceSyncId'] = $product->get_id();
            $query['externalSourceIntegration'] = $this->integration;
        } else {
            return null;
        }

        return $query;
    }

    /**
     * @param array $query
     * @return array|null
     */
    private function lookupByCriteria(array $query)
    {
        $client = Plugin::get_client();
        $query['itemsPerPage'] = 1;
        $items = [];
        try {
            $response = $client->get("/products", ['query' => $query]);
            $items = TrackMageClient::collection($response);
        } catch ( ClientException $e) {
            throw new \RuntimeException(TrackMageClient::error($e));
        }
        return isset($items[0]) ? $items[0] : null;
    }

    /**
     * @param $product_id
     *
     * @return false|\WC_Product|null
     */
    private function getProduct($product_id)
    {
        $product = wc_get_product($product_id);
        if ($product && $product->get_type() === 'variation' && $product->get_parent_id()) {
           return wc_get_product($product->get_parent_id());
        }
        return $product;
    }

    /**
     * @param \WC_Product $product
     *
     * @return string|bool
     */
    private function getProductImageUrl( \WC_Product $product ) {
        if (null === $imageId = $product->get_image_id()) {
            $imageIds = $product->get_gallery_image_ids();
            return is_array($imageIds) && count($imageIds) > 0 ?
                wp_get_attachment_image_url((int) array_values($imageIds)[0]) : false;
        }
        return wp_get_attachment_image_url((int) $imageId);
    }


}
