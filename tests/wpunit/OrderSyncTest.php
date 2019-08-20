<?php

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use TrackMage\WordPress\Plugin;

class OrderSyncTest extends WPTestCase {
    use GuzzleMockTrait;

    /** @var WpunitTester */
    protected $tester;

    /** @var WC_Product_Simple */
    private static $product;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();
        WC()->init();

        $product = new WC_Product_Simple();
        $product->set_name('Test Product');
        $product->set_price(100.00);
        $product->save();
        self::$product = $product;
    }

    public function testSingleOrderIsPostedToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['hydra:member' => []]), //can't find order by externalSyncId
            $this->createJsonResponse(201, ['id' => 'o-id']),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //WHEN
        //programmatically create an order in WC
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(['status' => 'completed']); //handled because status is completed.
        $wcId = $wcOrder->get_id();
        //THEN
        //check this order is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['GET', '/workspaces/1001/orders', ['externalSyncId' => (string) $wcId]],
            ['POST', '/orders'],
        ]);
        $this->assertSubmittedJsonIncludes([
            'externalSyncId' => (string) $wcId,
            'orderNumber' => $wcOrder->get_order_number(),
            'status' => 'completed',
        ], $requests[1]['request']);
        //make sure that TM ID is saved to WC order meta
        self::assertSame('o-id', get_post_meta($wcId, '_trackmage_order_id', true));
    }

    public function testPendingOrderIsNotPostedToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        //WHEN
        wc_create_order(); //by default status is pending

        //THEN
        self::assertCount(0, $requests);
    }

    public function testOrderWithItemsIsPostedToTrackMage()
    {
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', '1001');

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['hydra:member' => []]), //can't find order by externalSyncId
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
        $wcOrder->save(); //handled because status is completed.

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['GET', '/workspaces/1001/orders', ['externalSyncId' => (string) $wcId]],
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
        ], $requests[2]['request']);

        //make sure that TM ID is saved to WC order meta
        self::assertSame('oi-id', wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    public function testSyncOrderDoesNotAddIfExists()
    {
        // pre-create order in TM
        // pre-create order item in TM

        //programmatically create an order in WC
        //check this order was looked up but NOT sent to TM
        //make sure that TM ID is saved to WC order meta

        //programmatically create an order itm in WC
        //check this order item was looked up but NOT sent to TM
        //make sure that TM ID is saved to WC order item meta
    }

    public function testAlreadySyncedOrderSendsUpdateToTrackMage()
    {
        // pre-create order in TM
        // pre-create order item in TM
        // pre-create order item in WC linked to TM
        // pre-create order item in WC linked to TM

        // make a change on order in WC
        // make sure it updates the linked order in TM
        // make a change on order item in WC
        // make sure it updates the linked order item in TM
    }

    public function testAlreadySyncedButDeletedOrderGetsUnlinked()
    {
        // pre-create order item in WC linked to not existing TM id
        // pre-create order item in WC linked to not existing TM id

        // make a change on order in WC
        // check if the order is unlinked in WC
        // make a change on order item in WC
        // check if the order item is unlinked in WC
    }

    private function initPlugin(ClientInterface $guzzleClient = null)
    {
        $client = Plugin::get_client();
        if ($guzzleClient !== null) {
            $client->setGuzzleClient($guzzleClient);
        }
    }
}
