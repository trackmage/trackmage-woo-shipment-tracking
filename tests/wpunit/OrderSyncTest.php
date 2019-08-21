<?php

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Synchronizer;

class OrderSyncTest extends WPTestCase {
    use GuzzleMockTrait;

    /** @var WpunitTester */
    protected $tester;

    /** @var WC_Product_Simple */
    private static $product;

    /** @var Synchronizer */
    private static $synchronizer;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::get_synchronizer();
        $synchronizer->setDisableEvents(true);
        self::$synchronizer = $synchronizer;

        WC()->init();

        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_price(100.00);
        $product->save();
        self::$product = $product;
    }

    public function testNewOrderGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => 'o-id']),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcId = $wcOrder->get_id();

        //WHEN
        self::$synchronizer->syncOrder($wcId);

        //THEN
        //check this order is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/orders'],
        ]);
        $this->assertSubmittedJsonIncludes([
            'externalSyncId' => (string) $wcId,
            'orderNumber' => $wcOrder->get_order_number(),
            'status' => 'completed',
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC order meta
        self::assertSame('o-id', get_post_meta($wcId, '_trackmage_order_id', true));
    }

    public function testAlreadySyncedOrderSendsUpdateToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => 'tm-order-id']),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', 'tm-order-id', true );

        //WHEN
        self::$synchronizer->syncOrder($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/orders/tm-order-id'],
        ]);
        $this->assertSubmittedJsonIncludes([
            'externalSyncId' => (string) $wcId,
            'orderNumber' => $wcOrder->get_order_number(),
            'status' => 'completed',
        ], $requests[0]['request']);
    }


    public function testIfSameExistsItLookUpIdByOrderNumberId()
    {
        //GIVEN
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(400, ['hydra:description' => 'orderNumber: This value is already used.']),
            $this->createJsonResponse(200, ['hydra:member' => [['id' => 'tm-order-id']]]),
            $this->createJsonResponse(201, ['id' => 'tm-order-id']),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order item in WC
        $wcOrder = wc_create_order();
        $wcId = $wcOrder->get_id();

        //WHEN
        self::$synchronizer->syncOrder($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/orders'],
            ['GET', '/workspaces/1001/orders', ['orderNumber' => $wcOrder->get_order_number()]],
            ['PUT', '/orders/tm-order-id'],
        ]);

        self::assertSame('tm-order-id', get_post_meta( $wcId, '_trackmage_order_id', true ));
    }

    public function testIfSameExistsItLookUpIdByExternalSyncId()
    {
        //GIVEN
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(400, ['hydra:description' => 'externalSyncId: This value is already used.']),
            $this->createJsonResponse(200, ['hydra:member' => [['id' => 'tm-order-id']]]),
            $this->createJsonResponse(201, ['id' => 'tm-order-id']),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order item in WC
        $wcOrder = wc_create_order();
        $wcId = $wcOrder->get_id();

        //WHEN
        self::$synchronizer->syncOrder($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/orders'],
            ['GET', '/workspaces/1001/orders', ['externalSyncId' => (string) $wcId]],
            ['PUT', '/orders/tm-order-id'],
        ]);

        self::assertSame('tm-order-id', get_post_meta( $wcId, '_trackmage_order_id', true ));
    }

    public function testAlreadySyncedButDeletedOrderGetsPostedOnceAgain()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(404),
            $this->createJsonResponse(201, ['id' => 'tm-order-id']),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order item in WC linked to not existing TM id
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', 'tm-old-order-id', true );

        //WHEN
        self::$synchronizer->syncOrder($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/orders/tm-old-order-id'],
            ['POST', '/orders'],
        ]);

        self::assertSame('tm-order-id', get_post_meta( $wcId, '_trackmage_order_id', true ));
    }

    public function testPendingOrderIsNotPostedToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        $wcOrder = wc_create_order(); //by default status is pending
        $wcId = $wcOrder->get_id();

        //WHEN
        self::$synchronizer->syncOrder($wcId);

        //THEN
        self::assertCount(0, $requests);
    }

    public function xtestOrderWithItemsGetsPostedToTrackMage()
    {
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => 'o-id']),
            $this->createJsonResponse(201, ['id' => 'oi-id']),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(); //ignored since has no items
        $wcId = $wcOrder->get_id();
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcOrder->set_status('completed');
        //WHEN
        $wcOrder->save();

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/orders'],
            ['POST', '/order_items'],
        ]);
        //check this order item is sent to TM
        $this->assertSubmittedJsonIncludes([
            'order' => '/orders/o-id',
            'productName' => 'Test Product',
            'qty' => 1,
            'externalSyncId' => (string) $wcItemId,
            'rowTotal' => '100',
        ], $requests[1]['request']);

        //make sure that TM ID is saved to WC order meta
        self::assertSame('oi-id', wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    private function initPlugin(ClientInterface $guzzleClient = null)
    {
        $client = Plugin::get_client();
        if ($guzzleClient !== null) {
            $client->setGuzzleClient($guzzleClient);
        }
    }
}
