<?php

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Synchronization\OrderSync;

class OrderSyncTest extends WPTestCase {
    use GuzzleMockTrait;
    const TM_ORDER_ID = 'tm-order-id';
    const TM_WS_ID = '1001';
    const TM_WEBHOOK_ID = '0110';
    const SOURCE = 'wp';
    const TEST_ADDRESS = [
        'addressLine1' => 'addr1',
        'addressLine2' => 'addr2',
        'city' => 'TestCity',
        'company' => 'Company LTD',
        'countryIso2' => 'CN',
        'firstName' => 'FN',
        'lastName' => 'LN',
        'postcode' => '123',
        'state' => 'Beijing / 北京',
    ];

    /** @var WpunitTester */
    protected $tester;

    /** @var OrderSync */
    private $orderSync;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::instance()->getSynchronizer();
        $synchronizer->setDisableEvents(true);

        WC()->init();
        add_option('trackmage_webhook', self::TM_WEBHOOK_ID);
    }

    protected function _before()
    {
        $this->orderSync = new OrderSync(self::SOURCE);
    }

    public function testNewOrderGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed']);
        add_option('trackmage_workspace', self::TM_WS_ID);
        set_transient('trackmage_order_statuses',[self::TM_WS_ID => ['new'=>'New','pending'=>'Pending', 'completed'=>'Completed']]);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrder->set_shipping_address_1(self::TEST_ADDRESS['addressLine1']);
        $wcOrder->set_shipping_address_2(self::TEST_ADDRESS['addressLine2']);
        $wcOrder->set_shipping_city(self::TEST_ADDRESS['city']);
        $wcOrder->set_shipping_company(self::TEST_ADDRESS['company']);
        $wcOrder->set_shipping_country(self::TEST_ADDRESS['countryIso2']);
        $wcOrder->set_shipping_first_name(self::TEST_ADDRESS['firstName']);
        $wcOrder->set_shipping_last_name(self::TEST_ADDRESS['lastName']);
        $wcOrder->set_shipping_postcode(self::TEST_ADDRESS['postcode']);
        $wcOrder->set_shipping_state('CN2'); //Beijing
        $wcOrder->set_billing_address_1(self::TEST_ADDRESS['addressLine1']);
        $wcOrder->set_billing_address_2(self::TEST_ADDRESS['addressLine2']);
        $wcOrder->set_billing_city(self::TEST_ADDRESS['city']);
        $wcOrder->set_billing_company(self::TEST_ADDRESS['company']);
        $wcOrder->set_billing_country(self::TEST_ADDRESS['countryIso2']);
        $wcOrder->set_billing_first_name(self::TEST_ADDRESS['firstName']);
        $wcOrder->set_billing_last_name(self::TEST_ADDRESS['lastName']);
        $wcOrder->set_billing_postcode(self::TEST_ADDRESS['postcode']);
        $wcOrder->set_billing_state('CN2'); //Beijing
        $wcOrder->save();

        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        //check this order is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/orders', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);

        $this->assertSubmittedJsonIncludes([
            'workspace' => '/workspaces/'.self::TM_WS_ID,
            'externalSyncId' => (string) $wcId,
            'externalSource' => self::SOURCE,
            'orderNumber' => $wcOrder->get_order_number(),
            'orderStatus' => ['code' => 'completed', 'title' => 'Completed'],
            'shippingAddress' => self::TEST_ADDRESS,
            'billingAddress' => self::TEST_ADDRESS,
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC order meta
        self::assertSame(self::TM_ORDER_ID, get_post_meta($wcId, '_trackmage_order_id', true));
    }

    public function testAlreadySyncedOrderSendsUpdateToTrackMage()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);
        update_option('trackmage_order_status_aliases', ['wc-pending' => 'pending']);
        set_transient('trackmage_order_statuses',[self::TM_WS_ID => ['new'=>'New','pending'=>'Pending','completed'=>'Completed']]);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        $wcOrder = wc_create_order();
        $wcOrder->set_shipping_address_1(self::TEST_ADDRESS['addressLine1']);
        $wcOrder->set_shipping_address_2(self::TEST_ADDRESS['addressLine2']);
        $wcOrder->set_shipping_city(self::TEST_ADDRESS['city']);
        $wcOrder->set_shipping_company(self::TEST_ADDRESS['company']);
        $wcOrder->set_shipping_country(self::TEST_ADDRESS['countryIso2']);
        $wcOrder->set_shipping_first_name(self::TEST_ADDRESS['firstName']);
        $wcOrder->set_shipping_last_name(self::TEST_ADDRESS['lastName']);
        $wcOrder->set_shipping_postcode(self::TEST_ADDRESS['postcode']);
        $wcOrder->set_shipping_state('CN2'); //Beijing
        $wcOrder->set_billing_address_1(self::TEST_ADDRESS['addressLine1']);
        $wcOrder->set_billing_address_2(self::TEST_ADDRESS['addressLine2']);
        $wcOrder->set_billing_city(self::TEST_ADDRESS['city']);
        $wcOrder->set_billing_company(self::TEST_ADDRESS['company']);
        $wcOrder->set_billing_country(self::TEST_ADDRESS['countryIso2']);
        $wcOrder->set_billing_first_name(self::TEST_ADDRESS['firstName']);
        $wcOrder->set_billing_last_name(self::TEST_ADDRESS['lastName']);
        $wcOrder->set_billing_postcode(self::TEST_ADDRESS['postcode']);
        $wcOrder->set_billing_state('CN2'); //Beijing
        $wcOrder->save();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderSync->sync($wcId);

        //THEN
        // make sure it updates the linked order in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/orders/'.self::TM_ORDER_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'orderStatus' => ['code' => 'pending', 'title' => 'Pending'],
            'shippingAddress' => self::TEST_ADDRESS,
            'billingAddress' => self::TEST_ADDRESS,
        ], $requests[0]['request']);
    }

    public function testAlreadySyncedOrderIsNotSentTwice()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed']);
        set_transient('trackmage_order_statuses',[self::TM_WS_ID => ['new'=>'New','pending'=>'Pending', 'completed'=>'Completed']]);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        /** @var WC_Order $wcOrder */
        $wcOrder = wc_create_order(['status' => 'completed', 'title' => 'Completed']);
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
        set_transient('trackmage_order_statuses',[self::TM_WS_ID => ['new'=>'New','pending'=>'Pending']]);

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
        set_transient('trackmage_order_statuses',[self::TM_WS_ID => ['new'=>'New','pending'=>'Pending']]);

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
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed']);
        set_transient('trackmage_order_statuses',[self::TM_WS_ID => ['new'=>'New','pending'=>'Pending', 'completed'=>'Completed']]);

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
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed']);
        add_option('trackmage_workspace', self::TM_WS_ID);
        set_transient('trackmage_order_statuses',[self::TM_WS_ID => ['new'=>'New','pending'=>'Pending', 'completed'=>'Completed']]);

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
        add_option('trackmage_workspace', self::TM_WS_ID);

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
            ['DELETE', '/orders/'.self::TM_ORDER_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
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
