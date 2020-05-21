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
        $this->shipmentItemSync = new ShipmentItemSync();
        $this->shipmentItemRepo = new ShipmentItemRepository($this->shipmentItemSync);
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
        $wcOrderItemId = $wcOrder->add_product(self::$product);

        $wcShipmentItem = [
            'order_item_id' => $wcOrderItemId,
            'shipment' => ['id'=>self::TM_SHIPMENT_ID],
            'qty' => self::TEST_QTY,
        ];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItem);
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
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        $wcShipmentItem = [
            'order_item_id' => $wcOrderItemId,
            'shipment' => ['id' => self::TM_SHIPMENT_ID],
            'qty' => self::TEST_QTY,
        ];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItem);

        //THEN
        //check this shipment item is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/shipment_items'],
        ]);
        $data = [
            'shipment' => '/shipments/'.self::TM_SHIPMENT_ID,
            'orderItem' => '/order_items/'.self::TM_ORDER_ITEM_ID,
            'qty' => self::TEST_QTY,
        ];
        $requestData = (string)$requests[0]['request']->getBody()->getContents();
        //make sure that TM ID is saved to WC shipment meta
        self::assertEquals(\GuzzleHttp\json_encode($data), $requestData);
    }

    public function testShipmentItemSendsUpdateToTrackMage()
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
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        $wcShipmentItem = [
            'order_item_id' => $wcOrderItemId,
            'shipment' => ['id' => self::TM_SHIPMENT_ID],
            'qty' => self::TEST_QTY,
            'id' => self::TM_SHIPMENT_ITEM_ID
        ];

        //WHEN
        $this->shipmentItemSync->sync($wcShipmentItem);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipment_items/'.self::TM_SHIPMENT_ITEM_ID],
        ]);
        $this->assertSubmittedJsonIncludes([
            'qty' => self::TEST_QTY,
        ], $requests[0]['request']);
    }

    public function testAlreadySyncedShipmentItemSendsDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(204),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //WHEN
        $this->shipmentItemSync->delete(self::TM_SHIPMENT_ITEM_ID);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/shipment_items/'.self::TM_SHIPMENT_ITEM_ID],
        ]);
    }

    private function initPlugin(ClientInterface $guzzleClient = null)
    {
        $client = Plugin::get_client();
        if ($guzzleClient !== null) {
            $client->setGuzzleClient($guzzleClient);
        }
    }
}
