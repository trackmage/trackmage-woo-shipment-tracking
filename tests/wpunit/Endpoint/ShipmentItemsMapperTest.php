<?php
namespace TrackMage\WordPress\Tests\wpunit\Endpoint;


use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use TrackMage\WordPress\Webhook\Mappers\ShipmentsMapper;
use TrackMage\WordPress\Webhook\Mappers\ShipmentItemsMapper;
use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Exception\InvalidArgumentException;

use WC_Product_Simple;
use WC_Order;
use WC_Order_Item;

class ShipmentItemsMapperTest extends WPTestCase
{

    const INTEGRATION = 'tm-integration-id';
    const TRACKING_NUMBER = 'UPS-ABCDEF012345';
    const CARRIER = 'UPS';

    const TM_WS_ID = 'tm-workspace-id';
    const TM_ORDER_ID = 'tm-order-id';
    const TM_ORDER_ITEM_ID = 'tm-order-item-id';
    const TM_SHIPMENT_ID = 'tm-shipment-id';
    const TM_SHIPMENT_ITEM_ID = 'tm-shipment-item-id';

    const PRODUCT_NAME = 'Test Product';
    const PRICE = '100';
    const QTY = 2;

    const TEST_TM_ORDER_ITEM_ID = 'test-tm-order-item-id';
    const TEST_QTY = 3;

    /** @var \WpunitTester */
    protected $tester;

    /** @var ShipmentRepository */
    private $shipmentRepo;

    /** @var ShipmentItemRepository */
    private $shipmentItemRepo;

    /** @var ShipmentItemsMapper */
    private $shipmentItemsMapper;

    /** @var WC_Product_Simple */
    private static $product;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $endpoint = Plugin::instance()->getEndpoint();
        $endpoint->setDisableEvents(true);

        WC()->init();

        $product = new WC_Product_Simple();
        $product->set_name(self::PRODUCT_NAME);
        $product->set_price(self::PRICE);
        $product->save();
        self::$product = $product;
    }

    protected function _before()
    {
        add_option('trackmage_workspace', self::TM_WS_ID);

        $this->shipmentRepo = Plugin::instance()->getShipmentRepo();
        $this->shipmentItemRepo = Plugin::instance()->getShipmentItemsRepo();
        $this->shipmentItemsMapper = new ShipmentItemsMapper($this->shipmentItemRepo, self::INTEGRATION);
    }

    public function testShipmentItemIsFullyUpdated() {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);
        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TRACKING_NUMBER,
            'carrier' => self::CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::QTY,
            'trackmage_id' => self::TM_SHIPMENT_ITEM_ID
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        // add new order item
        $wcTestOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcTestOrderItemId, '_trackmage_order_item_id', self::TEST_TM_ORDER_ITEM_ID, true);

        $item = [
            "entity" => "shipment_items",
            "data" => [
                "shipment" => "/shipments/".self::TM_SHIPMENT_ID,
                "orderItem" => "/order_items/".self::TEST_TM_ORDER_ITEM_ID,
                "qty" => self::TEST_QTY,
                "externalSourceSyncId" => $wcShipmentItemId,
                "externalSourceIntegration" => '/workflows/'.self::INTEGRATION,
                "id" => self::TM_SHIPMENT_ITEM_ID,
                "workspace" => "/workspaces/".self::TM_WS_ID
            ],
            "event" => "update",
            "updatedFields" => [ "qty", "orderItem" ]
        ];

        $dataBefore = [
            'order_item_id' => $wcShipmentItem['order_item_id'],
            'qty' => $wcShipmentItem['qty']
        ];

        //WHEN everything is OK
        $this->shipmentItemsMapper->handle($item);

        //THEN
        $shipmentItemAfter = $this->shipmentItemRepo->find($wcShipmentItemId);
        $dataAfter = [
            'order_item_id' => $shipmentItemAfter['order_item_id'],
            'qty' => $shipmentItemAfter['qty']
        ];

        self::assertEquals(count(array_intersect_assoc($dataBefore, $dataAfter)),0);
    }

    public function testShipmentItemCanNotBeHandledBecauseWrongIntegration() {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to handle because integration Id does not match');
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TRACKING_NUMBER,
            'carrier' => self::CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::QTY,
            'trackmage_id' => self::TM_SHIPMENT_ITEM_ID
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        $item = [
            "entity" => "shipment_items",
            "data" => [
                "shipment" => "/shipments/".self::TM_SHIPMENT_ID,
                "orderItem" => "/order_items/".self::TM_ORDER_ITEM_ID,
                "qty" => self::TEST_QTY,
                "externalSourceSyncId" => $wcShipmentItemId,
                "externalSourceIntegration" => '/workflows/XXXXXXXX',
                "id" => self::TM_SHIPMENT_ITEM_ID,
                "workspace" => "/workspaces/".self::TM_WS_ID
            ],
            "event" => "update",
            "updatedFields" => [ "qty" ]
        ];

        //WHEN external source is wrong
        $wrongItem = $item;
        $wrongItem['data']['integration'] = '/workflows/wp-test-0001';
        $this->shipmentItemsMapper->handle($wrongItem);
    }

    public function testShipmentItemCanNotBeHandledBecauseUnknownShipmentItem() {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to handle because entity was not found');
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TRACKING_NUMBER,
            'carrier' => self::CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::QTY,
            'trackmage_id' => self::TM_SHIPMENT_ITEM_ID
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        $item = [
            "entity" => "shipment_items",
            "data" => [
                "shipment" => "/shipments/".self::TM_SHIPMENT_ID,
                "orderItem" => "/order_items/".self::TM_ORDER_ITEM_ID,
                "qty" => self::TEST_QTY,
                "externalSourceSyncId" => $wcShipmentItemId,
                "externalSourceIntegration" => '/workflows/'.self::INTEGRATION,
                "id" => self::TM_SHIPMENT_ITEM_ID,
                "workspace" => "/workspaces/".self::TM_WS_ID
            ],
            "event" => "update",
            "updatedFields" => [ "qty" ]
        ];

        //WHEN unknown shipment item
        $wrongItem = $item;
        $wrongItem['data']['externalSyncId'] = '99999';
        $wrongItem['data']['id'] = rand(1000,9999);
        $this->shipmentItemsMapper->handle($wrongItem);

    }

    public function testShipmentItemCanNotBeHandledBecauseUnknownOrderItem() {

        $this->expectException(EndpointException::class);
        $this->expectExceptionMessage('Order item was not found.');
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);

        //programmatically create a shipment item in WC
        $wcOrder = wc_create_order();
        $wcOrderId = $wcOrder->get_id();
        $wcOrderItemId = $wcOrder->add_product(self::$product);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TRACKING_NUMBER,
            'carrier' => self::CARRIER,
            'trackmage_id' => self::TM_SHIPMENT_ID
        ]);
        $wcShipmentId = $wcShipment['id'];
        $wcShipmentItem = $this->shipmentItemRepo->insert([
            'order_item_id' => $wcOrderItemId,
            'shipment_id' => $wcShipmentId,
            'qty' => self::QTY,
            'trackmage_id' => self::TM_SHIPMENT_ITEM_ID
        ]);
        $wcShipmentItemId = $wcShipmentItem['id'];

        $item = [
            "entity" => "shipment_items",
            "data" => [
                "shipment" => "/shipments/".self::TM_SHIPMENT_ID,
                "orderItem" => "/order_items/".self::TM_ORDER_ITEM_ID,
                "qty" => self::TEST_QTY,
                "externalSourceSyncId" => $wcShipmentItemId,
                "externalSourceIntegration" => '/workflows/'.self::INTEGRATION,
                "id" => self::TM_SHIPMENT_ITEM_ID
            ],
            "event" => "update",
            "updatedFields" => [ "qty", "orderItem" ]
        ];
        //WHEN wrong order item
        $wrongItem = $item;
        $wrongItem['data']['orderItem'] = "/order_items/wrong-test-order-item";
        $this->shipmentItemsMapper->handle($wrongItem);

    }
}
