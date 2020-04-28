<?php

namespace TrackMage\WordPress\Tests\wpunit\Syncrhonization;

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleMockTrait;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Synchronization\OrderItemSync;
use WC_Product_Simple;
use WC_Product_Variation;
use WpunitTester;

class OrderItemSyncTest extends WPTestCase
{
    use GuzzleMockTrait;

    const QTY = 1;
    const TM_WS_ID = '1001';
    const TM_WEBHOOK_ID = '0110';
    const TM_ORDER_ID = 'tm-order-id';
    const TM_ORDER_ITEM_ID = 'tm-order-item-id';
    const PRODUCT_NAME = 'Test Product';
    const PRODUCT_SKU = 'TestSku';
    const PRICE = '100';
    const INTEGRATION = 'tm-integration-id';

    /** @var WpunitTester */
    protected $tester;

    /** @var WC_Product_Simple */
    private static $product;

    /** @var OrderItemSync */
    private $orderItemSync;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::instance()->getSynchronizer();
        $synchronizer->setDisableEvents(true);

        WC()->init();

        $product = new WC_Product_Simple();
        $product->set_name(self::PRODUCT_NAME);
        $product->set_sku(self::PRODUCT_SKU);
        $product->set_price(self::PRICE);
        $product->save();
        self::$product = $product;

        add_option('trackmage_workspace', self::TM_WS_ID);
        add_option('trackmage_webhook', self::TM_WEBHOOK_ID);
    }

    protected function _before()
    {
        $this->orderItemSync = new OrderItemSync(self::INTEGRATION);
    }

    public function testOrderItemIsNotPostedBecauseOrderMustBeSyncedFirst()
    {
        $this->expectException(SynchronizationException::class);
        $this->expectExceptionMessage('Unable to sync order item because order is not yet synced');

        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderItemSync->sync($wcItemId);
    }

    public function testNewOrderItemGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        //check this order is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/order_items', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'order' => '/orders/' . self::TM_ORDER_ID,
            'productName' => self::PRODUCT_NAME,
            'productSku' => self::PRODUCT_SKU,
            'imageUrl' => null,
            'qty' => self::QTY,
//            'price' => self::PRICE, TODO: price is empty
            'rowTotal' => self::PRICE,
            'externalSourceSyncId' => $wcItemId,
            'externalSourceIntegration' => '/workflows/'.self::INTEGRATION,
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC order item meta
        self::assertSame(self::TM_ORDER_ITEM_ID, wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }


    public function testNewOrderItemWithVariantProductGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);
        // create Variant Product
        // The variation data
        $variation_data =  array(
            'attributes' => array(
                'size'  => 'M',
                'color' => 'Green',
            ),
            'sku'           => 'VARIANT_SKU',
            'regular_price' => self::PRICE,
            'sale_price'    => '',
            'stock_qty'     => 10,
        );
        $variationProduct = $this->createProductVariation(self::$product->get_id(), $variation_data);
        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product($variationProduct, self::QTY);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        //check this order is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/order_items', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'order' => '/orders/' . self::TM_ORDER_ID,
            'productName' => self::PRODUCT_NAME,
            'productSku' => 'VARIANT_SKU',
            'imageUrl' => null,
            'qty' => self::QTY,
            'productOptions' => $variationProduct->get_attributes(),
            'rowTotal' => self::PRICE,
            'externalSourceSyncId' => $wcItemId,
            'externalSourceIntegration' => '/workflows/'.self::INTEGRATION,
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC order item meta
        self::assertSame(self::TM_ORDER_ITEM_ID, wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    public function testAlreadySyncedOrderItemSendsUpdateToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta($wcItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/order_items/'.self::TM_ORDER_ITEM_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'productName' => self::PRODUCT_NAME,
            'qty' => self::QTY,
//            'price' => self::PRICE, TODO: price is empty
            'rowTotal' => self::PRICE,
        ], $requests[0]['request']);
    }

    public function testAlreadySyncedOrderItemIsNotSentTwice()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta($wcItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        //WHEN
        $this->orderItemSync->sync($wcItemId);
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/order_items/'.self::TM_ORDER_ITEM_ID],
        ]);
        self::assertCount(1, $requests);
    }

    public function testIfSameExistsItLookUpIdByExternalSourceSyncId()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(400, ['hydra:description' => 'externalSourceSyncId: This value is already used.']),
            $this->createJsonResponse(200, ['hydra:member' => [['id' => self::TM_ORDER_ITEM_ID]]]),
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/order_items'],
            ['GET', '/orders/'.self::TM_ORDER_ID.'/items', ['externalSourceSyncId' => $wcItemId, 'externalSourceIntegration' => '/workflows/'.self::INTEGRATION]],
            ['PUT', '/order_items/'.self::TM_ORDER_ITEM_ID],
        ]);
        //make sure that TM ID is saved to WC order item meta
        self::assertSame(self::TM_ORDER_ITEM_ID, wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }


    public function testAlreadySyncedButDeletedOrderItemGetsPostedOnceAgain()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(404),
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order item in WC linked to not existing TM id
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta( $wcItemId, '_trackmage_order_item_id', 'tm-old-order-item-id', true );

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/order_items/tm-old-order-item-id'],
            ['POST', '/order_items'],
        ]);
        //make sure that TM ID is saved to WC order item meta
        self::assertSame(self::TM_ORDER_ITEM_ID, wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    public function testOrderItemIsNotPostedIfOrderCannotBeSynced()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        $wcOrder = wc_create_order(); //status is pending
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        self::assertCount(0, $requests);
    }

    public function testAlreadySyncedOrderItemSendsDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(204, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order();
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta($wcItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        //WHEN
        $this->orderItemSync->delete($wcItemId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/order_items/'.self::TM_ORDER_ITEM_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        self::assertSame('', wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    public function testNotSyncedOrderItemIgnoresDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        $wcOrder = wc_create_order();
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderItemSync->delete($wcItemId);

        //THEN
        self::assertCount(0, $requests);
    }


    private function initPlugin(ClientInterface $guzzleClient = null)
    {
        $client = Plugin::get_client();
        if ($guzzleClient !== null) {
            $client->setGuzzleClient($guzzleClient);
        }
    }

    private function createProductVariation( $product_id, $variation_data ){
        // Get the Variable product object (parent)
        $product = wc_get_product($product_id);

        $variation_post = array(
            'post_title'  => $product->get_name(),
            'post_name'   => 'product-'.$product_id.'-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type'   => 'product_variation',
            'guid'        => $product->get_permalink()
        );

        // Creating the product variation
        $variation_id = wp_insert_post( $variation_post );

        // Get an instance of the WC_Product_Variation object
        $variation = new WC_Product_Variation( $variation_id );

        // Iterating through the variations attributes
        foreach ($variation_data['attributes'] as $attribute => $term_name )
        {
            $taxonomy = 'pa_'.$attribute; // The attribute taxonomy

            // If taxonomy doesn't exists we create it (Thanks to Carl F. Corneil)
            if( ! taxonomy_exists( $taxonomy ) ){
                register_taxonomy(
                    $taxonomy,
                    'product_variation',
                    array(
                        'hierarchical' => false,
                        'label' => ucfirst( $attribute ),
                        'query_var' => true,
                        'rewrite' => array( 'slug' => sanitize_title($attribute) ), // The base slug
                    )
                );
            }

            // Check if the Term name exist and if not we create it.
            if( ! term_exists( $term_name, $taxonomy ) )
                wp_insert_term( $term_name, $taxonomy ); // Create the term

            $term_slug = get_term_by('name', $term_name, $taxonomy )->slug; // Get the term slug

            // Get the post Terms names from the parent variable product.
            $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );

            // Check if the post term exist and if not we set it in the parent variable product.
            if( ! in_array( $term_name, $post_term_names ) )
                wp_set_post_terms( $product_id, $term_name, $taxonomy, true );

            // Set/save the attribute data in the product variation
            update_post_meta( $variation_id, 'attribute_'.$taxonomy, $term_slug );
        }

        ## Set/save all other data

        // SKU
        if( ! empty( $variation_data['sku'] ) )
            $variation->set_sku( $variation_data['sku'] );

        // Prices
        if( empty( $variation_data['sale_price'] ) ){
            $variation->set_price( $variation_data['regular_price'] );
        } else {
            $variation->set_price( $variation_data['sale_price'] );
            $variation->set_sale_price( $variation_data['sale_price'] );
        }
        $variation->set_regular_price( $variation_data['regular_price'] );

        // Stock
        if( ! empty($variation_data['stock_qty']) ){
            $variation->set_stock_quantity( $variation_data['stock_qty'] );
            $variation->set_manage_stock(true);
            $variation->set_stock_status('');
        } else {
            $variation->set_manage_stock(false);
        }

        $variation->set_weight(''); // weight (reseting)

        $variation->save(); // Save the data

        return $variation;
    }
}
