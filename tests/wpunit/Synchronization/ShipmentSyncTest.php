<?php

namespace TrackMage\WordPress\Tests\wpunit\Syncrhonization;

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleMockTrait;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Synchronization\ShipmentSync;

class ShipmentSyncTest extends WPTestCase
{
    use GuzzleMockTrait;
    const SOURCE = 'wp';
    const TM_SHIPMENT_ID = '1010';
    const TM_ORDER_ID = '1110';
    const TM_WS_ID = '1001';
    const TM_WEBHOOK_ID = '0110';
    const TEST_TRACKING_NUMBER = 'TN-ABC';
    const TEST_CARRIER = 'UPS';

    /** @var \WpunitTester */
    protected $tester;

    /** @var ShipmentRepository */
    private $shipmentRepo;

    /** @var ShipmentSync */
    private $shipmentSync;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::instance()->getSynchronizer();
        $synchronizer->setDisableEvents(true);

        WC()->init();
        add_option('trackmage_webhook', self::TM_WEBHOOK_ID);
    }

    protected function _before()
    {
        $this->shipmentRepo = Plugin::instance()->getShipmentRepo();
        $this->shipmentSync = new ShipmentSync($this->shipmentRepo, self::SOURCE);
    }

    public function testNewShipmentGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_SHIPMENT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrder->set_billing_email('email@email.test');
        $wcOrder->set_billing_phone('+123456789');
        $wcOrder->save();
        $wcOrderId = $wcOrder->get_id();
        add_post_meta( $wcOrderId, '_trackmage_order_id', self::TM_ORDER_ID );

        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->sync($wcShipmentId);

        //THEN
        //check this shipment is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/shipments', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'workspace' => '/workspaces/'.self::TM_WS_ID,
            'externalSyncId' => (string) $wcShipmentId,
            'externalSource' => self::SOURCE,
            'trackingNumber' => self::TEST_TRACKING_NUMBER,
            'originCarrier' => self::TEST_CARRIER,
            'email' => $wcOrder->get_billing_email(),
            'phoneNumber' => $wcOrder->get_billing_phone(),
            'orders' => ['/orders/'.self::TM_ORDER_ID],
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC shipment meta
        self::assertSame(self::TM_SHIPMENT_ID, $this->shipmentRepo->find($wcShipmentId)['trackmage_id']);
    }

    public function testNewShipmentWithAutoCarrierSubmitsNull()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_SHIPMENT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        add_post_meta( $wcOrderId, '_trackmage_order_id', self::TM_ORDER_ID );

        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => 'auto',
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->sync($wcShipmentId);

        //THEN
        //check this shipment is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/shipments'],
        ]);
        $this->assertSubmittedJsonIncludes([
            'originCarrier' => null,
        ], $requests[0]['request']);
    }

    public function testAlreadySyncedShipmentSendsUpdateToTrackMage()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_SHIPMENT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create shipment in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrder->set_billing_email('email@email.test');
        $wcOrder->set_billing_phone('+123456789');
        $wcOrder->save();
        $wcOrderId = $wcOrder->get_id();
        add_post_meta( $wcOrderId, '_trackmage_order_id', self::TM_ORDER_ID );
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID,
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->sync($wcShipmentId);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipments/'.self::TM_SHIPMENT_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'trackingNumber' => self::TEST_TRACKING_NUMBER,
            'email' => $wcOrder->get_billing_email(),
            'phoneNumber' => $wcOrder->get_billing_phone(),
            'orders' => ['/orders/'.self::TM_ORDER_ID],
        ], $requests[0]['request']);
    }


    public function testAlreadySyncedShipmentIsNotSentTwice()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_SHIPMENT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create shipment in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID,
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->sync($wcShipmentId);
        $this->shipmentSync->sync($wcShipmentId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipments/'.self::TM_SHIPMENT_ID],
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
            $this->createJsonResponse(200, ['hydra:member' => [['id' => self::TM_SHIPMENT_ID]]]),
            $this->createJsonResponse(201, ['id' => self::TM_SHIPMENT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->sync($wcShipmentId);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/shipments'],
            ['GET', '/workspaces/'.self::TM_WS_ID.'/shipments', ['externalSyncId' => (string) $wcShipmentId, 'externalSource' => self::SOURCE]],
            ['PUT', '/shipments/'.self::TM_SHIPMENT_ID],
        ]);

        self::assertSame(self::TM_SHIPMENT_ID, $this->shipmentRepo->find($wcShipmentId)['trackmage_id']);
    }

    public function testAlreadySyncedButDeletedShipmentGetsPostedOnceAgain()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(404),
            $this->createJsonResponse(201, ['id' => self::TM_SHIPMENT_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create shipment in WC linked to not existing TM id
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => '1111',
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->sync($wcShipmentId);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipments/1111'],
            ['POST', '/shipments'],
        ]);

        self::assertSame(self::TM_SHIPMENT_ID, $this->shipmentRepo->find($wcShipmentId)['trackmage_id']);
    }


    public function testAlreadySyncedShipmentSendsDelete()
    {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(204),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID,
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->delete($wcShipmentId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/shipments/'.self::TM_SHIPMENT_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        self::assertNull($this->shipmentRepo->find($wcShipmentId)['trackmage_id']);
    }

    public function testNotSyncedShipmentIgnoresDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
        ]);
        $wcShipmentId = $wcShipment['id'];

        //WHEN
        $this->shipmentSync->delete($wcShipmentId);

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
