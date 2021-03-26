<?php

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Synchronization\ProductSync;

class ProductSyncTest extends WPTestCase {
    use GuzzleMockTrait;
    const TM_PRODUCT_ID = 'tm-product-id';
    const TM_WS_ID = '1001';
    const TM_TEAM_ID = '/teams/1000';
    const TM_WEBHOOK_ID = '0110';
    const INTEGRATION = 'tm-integration-id';
    const PRODUCT_NAME = 'Test Product';
    const PRODUCT_SKU = 'TestSku';
    const PRICE = '100';

    /** @var WpunitTester */
    protected $tester;

    /** @var ProductSync */
    private $productSync;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::instance()->getSynchronizer();
        $synchronizer->setDisableEvents(true);

        WC()->init();
        add_option('trackmage_webhook', self::TM_WEBHOOK_ID);
    }

    protected function _before()
    {
        $this->productSync = new ProductSync(self::INTEGRATION);
        add_option('trackmage_workspace', self::TM_WS_ID);
        add_option('trackmage_team', self::TM_TEAM_ID);
    }

    public function testProductGetsPosted()
    {
        //GIVEN

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_PRODUCT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an product in WC
        $product = $this->createProduct();
        $wcId = $product->get_id();

        //WHEN
        $this->productSync->sync($wcId);

        //THEN
        //check this product is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/products', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);

        $this->assertSubmittedJsonIncludes([
            'team' => self::TM_TEAM_ID,
            'externalSourceSyncId' => (string) $wcId,
            'externalSourceIntegration' => '/workflows/'.self::INTEGRATION,
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'originUrl' => get_the_permalink($wcId),
            'imageUrl' => null,
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC product meta
        self::assertSame(self::TM_PRODUCT_ID, get_post_meta($wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, true));
    }

    public function testParentProductGetsPosted()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_PRODUCT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an product in WC
        $product = $this->createProduct();
        $wcId = $product->get_id();

        // create Variant Product
        // The variation data
        $variation_data =  array(
            'attributes' => array(
                'size'  => 'M',
                'color' => 'Green',
            ),
            'sku'           => 'OI_VARIANT_SKU',
            'regular_price' => self::PRICE,
            'sale_price'    => '',
            'stock_qty'     => 10,
        );
        $variationProduct = self::createProductVariation($wcId, $variation_data);

        //WHEN
        $this->productSync->sync($variationProduct->get_id());

        //THEN
        //check this product is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/products', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);

        $this->assertSubmittedJsonIncludes([
            'team' => self::TM_TEAM_ID,
            'externalSourceSyncId' => (string) $wcId,
            'externalSourceIntegration' => '/workflows/'.self::INTEGRATION,
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'originUrl' => get_the_permalink($wcId),
            'imageUrl' => null,
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC product meta
        self::assertSame(self::TM_PRODUCT_ID, get_post_meta($wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, true));
    }

    public function testAlreadySyncedProductSendsUpdateToTrackMage()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_PRODUCT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        $product = $this->createProduct();
        $wcId = $product->get_id();
        add_post_meta( $wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, self::TM_PRODUCT_ID, true );

        //WHEN
        $this->productSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/products/'.self::TM_PRODUCT_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'originUrl' => get_the_permalink($wcId),
            'imageUrl' => null,
        ], $requests[0]['request']);
    }

    public function testAlreadySyncedOrderIsNotSentTwice()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_PRODUCT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create product in TM
        $product = $this->createProduct();
        $wcId = $product->get_id();
        add_post_meta( $wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, self::TM_PRODUCT_ID, true );

        //WHEN
        $this->productSync->sync($wcId);
        $this->productSync->sync($wcId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/products/'.self::TM_PRODUCT_ID],
        ]);
        self::assertCount(1, $requests);
    }

    public function testIfSameExistsItLookUpIdByExternalSyncId()
    {
        //GIVEN

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(400, ['hydra:description' => 'externalSourceSyncId: This value is already used.']),
            $this->createJsonResponse(200, ['hydra:member' => [['id' => self::TM_PRODUCT_ID]]]),
            $this->createJsonResponse(201, ['id' => self::TM_PRODUCT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create product in WC
        $product = $this->createProduct();
        $wcId = $product->get_id();

        //WHEN
        $this->productSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/products'],
            ['GET', '/products', ['externalSourceSyncId' => (string) $wcId, 'externalSourceIntegration' => '/workflows/'.self::INTEGRATION]],
            ['PUT', '/products/'.self::TM_PRODUCT_ID],
        ]);

        self::assertSame(self::TM_PRODUCT_ID, get_post_meta( $wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, true ));
    }

    public function testAlreadySyncedButDeletedOrderGetsPostedOnceAgain()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(404),
            $this->createJsonResponse(201, ['id' => self::TM_PRODUCT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in WC linked to not existing TM id
        $product = $this->createProduct();
        $wcId = $product->get_id();
        add_post_meta( $wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, 'tm-old-product-id', true );

        //WHEN
        $this->productSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/products/tm-old-product-id'],
            ['POST', '/products'],
        ]);

        self::assertSame(self::TM_PRODUCT_ID, get_post_meta( $wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, true ));
    }

    public function testAlreadySyncedOrderSendsDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(204),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create product in TM
        $product = $this->createProduct();
        $wcId = $product->get_id();
        add_post_meta( $wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, self::TM_PRODUCT_ID, true );

        //WHEN
        $this->productSync->delete($wcId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/products/'.self::TM_PRODUCT_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        self::assertSame('', get_post_meta( $wcId, ProductSync::TRACKMAGE_PRODUCT_ID_META_KEY, true ));
    }

    public function testNotSyncedOrderIgnoresDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create product in TM
        $product = $this->createProduct();
        $wcId = $product->get_id();

        //WHEN
        $this->productSync->delete($wcId);

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

    /**
     * @return \WC_Product
     * @throws WC_Data_Exception
     */
    private function createProduct()
    {
        $product = new WC_Product_Simple();
        $product->set_name( self::PRODUCT_NAME );
        $product->set_sku( self::PRODUCT_SKU.time() );
        $product->set_price( self::PRICE );
        $product->save();
        return $product;
    }
}
