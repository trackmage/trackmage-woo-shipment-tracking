<?php

namespace TrackMage\WordPress\Tests\wpunit\Syncrhonization;

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleMockTrait;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Synchronization\ShipmentItemSync;
use WC_Product_Simple;

class ShipmentItemSyncTest extends WPTestCase
{
    use GuzzleMockTrait;
    const INTEGRATION = 'tm-integration-id';
    const TM_SHIPMENT_ID = '1010';
    const TM_SHIPMENT_ITEM_ID = '1111';
    const TM_WS_ID = '1001';
    const TM_WEBHOOK_ID = '0110';
    const TM_ORDER_ID = 'tm-order-id';
    const TM_ORDER_ITEM_ID = 'tm-order-item-id';
    const TEST_TRACKING_NUMBER = 'TN-ABC';
    const PRODUCT_NAME = 'Test Product';
    const PRICE = '100';

    const TEST_CARRIER = 'UPS';
    const TEST_QTY = 10;

    /** @var WC_Product_Simple */
    private static $product;

    /** @var \WpunitTester */
    protected $tester;

    /** @var ShipmentRepository */
    private $shipmentRepo;

    /** @var ShipmentItemRepository */
    private $shipmentItemRepo;

    /** @var ShipmentItemSync */
    private $shipmentItemSync;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::instance()->getSynchronizer();
        $synchronizer->setDisableEvents(true);

        WC()->init();

        $product = new WC_Product_Simple();
        $product->set_name(self::PRODUCT_NAME);
        $product->set_price(self::PRICE);
        $product->save();
        self::$product = $product;

        add_option('trackmage_workspace', self::TM_WS_ID);
        add_option('trackmage_webhook', self::TM_WEBHOOK_ID);
    }

    protected function _before()
    {
        $this->shipmentRepo = Plugin::instance()->getShipmentRepo();
        $this->shipmentItemRepo = Plugin::instance()->getShipmentItemsRepo();
        $this->shipmentItemSync = new ShipmentItemSync($this->shipmentItemRepo, $this->shipmentRepo, self::INTEGRATION);
    }

    public function testShipmentItemIsNotPostedBecauseShipmentMustBeSyncedFirst()
    {
        $this->expectException(SynchronizationException::class);
        $this->expectExceptionMessage('Unable to sync shipment item because shipment is not yet synced');

        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);
    }

    public function testShipmentItemIsNotPostedBecauseOrderItemMustBeSyncedFirst()
    {
        $this->expectException(SynchronizationException::class);
        $this->expectExceptionMessage('Unable to sync shipment item because order item is not yet synced');

        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);
    }

    public function testNewShipmentItemGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-pending']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_SHIPMENT_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
//        add_post_meta( $wcOrderId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);

        //THEN
        //check this shipment item is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/shipment_items', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'shipment' => '/shipments/'.self::TM_SHIPMENT_ID,
            'orderItem' => '/order_items/'.self::TM_ORDER_ITEM_ID,
            'qty' => self::TEST_QTY,
            'externalSyncId' => (string) $wcShipmentItemId,
            'integration' => '/workflows/'.self::INTEGRATION,
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC shipment meta
        self::assertSame(self::TM_SHIPMENT_ITEM_ID, $this->shipmentItemRepo->find($wcShipmentItemId)['trackmage_id']);
    }

    public function testAlreadySyncedShipmentItemSendsUpdateToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-pending']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_SHIPMENT_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create shipment in TM
        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
            'trackmage_id' => self::TM_SHIPMENT_ITEM_ID
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipment_items/'.self::TM_SHIPMENT_ITEM_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'orderItem' => '/order_items/'.self::TM_ORDER_ITEM_ID,
            'qty' => self::TEST_QTY,
        ], $requests[0]['request']);
    }


    public function testAlreadySyncedShipmentItemIsNotSentTwice()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-pending']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_SHIPMENT_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
            'trackmage_id' => self::TM_SHIPMENT_ITEM_ID
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);
        $this->shipmentItemSync->sync($wcShipmentItemId);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipment_items/'.self::TM_SHIPMENT_ITEM_ID],
        ]);
        self::assertCount(1, $requests);
    }

    public function testIfSameExistsItLookUpIdByExternalSyncId()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(400, ['hydra:description' => 'externalSyncId: This value is already used.']),
            $this->createJsonResponse(200, ['hydra:member' => [['id' => self::TM_SHIPMENT_ITEM_ID]]]),
            $this->createJsonResponse(201, ['id' => self::TM_SHIPMENT_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/shipment_items'],
            ['GET', '/shipment_items', ['workspace.id' => self::TM_WS_ID, 'externalSyncId' => (string) $wcShipmentItemId, 'integration' => '/workflows/'.self::INTEGRATION]],
            ['PUT', '/shipment_items/'.self::TM_SHIPMENT_ITEM_ID],
        ]);

        self::assertSame(self::TM_SHIPMENT_ITEM_ID, $this->shipmentItemRepo->find($wcShipmentItemId)['trackmage_id']);
    }

    public function testAlreadySyncedButDeletedShipmentGetsPostedOnceAgain()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(404),
            $this->createJsonResponse(201, ['id' => self::TM_SHIPMENT_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //pre-create shipment item in WC linked to not existing TM id
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
            'trackmage_id' => 'not-existing-id'
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipment_items/not-existing-id'],
            ['POST', '/shipment_items'],
        ]);

        self::assertSame(self::TM_SHIPMENT_ITEM_ID, $this->shipmentItemRepo->find($wcShipmentItemId)['trackmage_id']);
    }


    public function testShipmentItemIsNotPostedIfOrderCannotBeSynced()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

//        $wcOrder = wc_create_order();
//        $wcItemId = $wcOrder->add_product(self::$product);
//        $wcId = $wcOrder->get_id();

        //pre-create shipment item
        $wcOrder = wc_create_order();  //status is pending
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItemId);

        //THEN
        self::assertCount(0, $requests);
    }


    public function testAlreadySyncedShipmentItemSendsDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(204),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
            'trackmage_id' => self::TM_SHIPMENT_ITEM_ID
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->delete($wcShipmentItemId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/shipment_items/'.self::TM_SHIPMENT_ITEM_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        self::assertNull($this->shipmentItemRepo->find($wcShipmentItemId)['trackmage_id']);
    }

    public function testNotSyncedShipmentItemIgnoresDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::TEST_QTY,
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        //WHEN
        $this->shipmentItemSync->delete($wcShipmentItemId);

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
