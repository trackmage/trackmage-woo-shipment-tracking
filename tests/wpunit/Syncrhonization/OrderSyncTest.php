<?php

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Synchronization\OrderSync;

class OrderSyncTest extends WPTestCase {
    use GuzzleMockTrait;
    const TM_ORDER_ID = 'tm-order-id';
    const TM_WS_ID = '1001';
    const SOURCE = 'wp';

    /** @var WpunitTester */
    protected $tester;

    /** @var OrderSync */
    private $orderSync;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::instance()->getSynchronizer();
        $synchronizer->setDisableEvents(true);

        WC()->init();
    }

    protected function _before()
    {
        $this->orderSync = new OrderSync(self::SOURCE);
    }

    public function testNewOrderGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrder->set_shipping_address_1('address1');
        $wcOrder->save();

        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        //check this order is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/orders'],
        ]);
        $this->assertSubmittedJsonIncludes([
            'workspace' => '/workspaces/'.self::TM_WS_ID,
            'externalSyncId' => (string) $wcId,
            'externalSource' => self::SOURCE,
            'orderNumber' => $wcOrder->get_order_number(),
            'address' => 'address1',
            'status' => ['name' => 'completed'],
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC order meta
        self::assertSame(self::TM_ORDER_ID, get_post_meta($wcId, '_trackmage_order_id', true));
    }

    public function testAlreadySyncedOrderSendsUpdateToTrackMage()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        $wcOrder = wc_create_order();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/orders/'.self::TM_ORDER_ID],
        ]);
        $this->assertSubmittedJsonIncludes([
            'status' => ['name' => 'pending'],
        ], $requests[0]['request']);
    }

    public function testAlreadySyncedOrderIsNotSentTwice()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderSync->sync($wcId);
        $this->orderSync->sync($wcId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/orders/'.self::TM_ORDER_ID],
        ]);
        self::assertCount(1, $requests);
    }

    public function testIfSameExistsItLookUpIdByExternalSyncId()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(400, ['hydra:description' => 'externalSyncId: This value is already used.']),
            $this->createJsonResponse(200, ['hydra:member' => [['id' => self::TM_ORDER_ID]]]),
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in WC
        $wcOrder = wc_create_order();
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/orders'],
            ['GET', '/workspaces/'.self::TM_WS_ID.'/orders', ['externalSyncId' => (string) $wcId, 'externalSource' => self::SOURCE]],
            ['PUT', '/orders/'.self::TM_ORDER_ID],
        ]);

        self::assertSame(self::TM_ORDER_ID, get_post_meta( $wcId, '_trackmage_order_id', true ));
    }

    public function testAlreadySyncedButDeletedOrderGetsPostedOnceAgain()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(404),
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in WC linked to not existing TM id
        $wcOrder = wc_create_order();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', 'tm-old-order-id', true );

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/orders/tm-old-order-id'],
            ['POST', '/orders'],
        ]);

        self::assertSame(self::TM_ORDER_ID, get_post_meta( $wcId, '_trackmage_order_id', true ));
    }

    public function testNewOrderWithNotSyncedStatusIsNotPostedToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        $wcOrder = wc_create_order(); //status is pending
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        self::assertCount(0, $requests);
    }

    public function testAlreadySyncedOrderIgnoresSyncStatuses()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(); //status is pending
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/orders/'.self::TM_ORDER_ID],
        ]);
    }

    public function testAlreadySyncedOrderSendsDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(204),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        $wcOrder = wc_create_order();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderSync->delete($wcId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/orders/'.self::TM_ORDER_ID],
        ]);
        self::assertSame('', get_post_meta( $wcId, '_trackmage_order_id', true ));
    }

    public function testNotSyncedOrderIgnoresDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        $wcOrder = wc_create_order();
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderSync->delete($wcId);

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
}
