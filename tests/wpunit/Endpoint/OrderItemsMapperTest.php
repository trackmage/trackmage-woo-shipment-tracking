<?php
namespace TrackMage\WordPress\Tests\wpunit\Endpoint;


use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Exception\EndpointException;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Webhook\Mappers\OrderItemsMapper;
use TrackMage\WordPress\Webhook\Mappers\OrdersMapper;
use WC_Product_Simple;
use WC_Order;
use WC_Order_Item;

class OrderItemsMapperTest extends WPTestCase
{
    const INTEGRATION = 'tm-integration-id';
    const STATUS = 'completed';
    const PRICE = 50;

    const TM_WS_ID = 'tm-workspace-id';
    const TM_ORDER_ID = 'tm-order-id';
    const TM_ORDER_ITEM_ID = 'tm-order-item-id';

    const PRODUCT_NAME = 'Test Product';
    const TOTAL = '100';
    const QTY = 2;

    const TEST_TM_ORDER_ITEM_ID = 'test-tm-order-item-id';
    const TEST_QTY = 3;
    const TEST_TOTAL = '150';

    /** @var \WpunitTester */
    protected $tester;

    /** @var OrderItemsMapper */
    private $orderItemsMapper;

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

        $this->orderItemsMapper = new OrderItemsMapper(self::INTEGRATION);
    }

    public function testOrderItemIsFullyUpdated() {
        //GIVEN
         //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        $wcOrderItemId = $wcOrder->add_product(self::$product, self::QTY);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID);

        $orderItemBefore = $this->orderItemsMapper->getOrderItem($wcOrderItemId);
        $dataBefore = [
            'qty' => $orderItemBefore->get_quantity(),
            'rowTotal' => $orderItemBefore->get_total()
        ];

        $item = [
            "entity" => "order_items",
            "data" => [
                "order" => "/orders/".self::TM_ORDER_ID,
                "qty" => self::TEST_QTY,
                "price" => self::PRICE,
                "rowTotal" => self::TEST_QTY*self::PRICE,
                "externalSourceSyncId" => $wcOrderItemId,
                "externalSourceIntegration" => '/workflows/'.self::INTEGRATION,
                "id" => self::TM_ORDER_ITEM_ID,
            ],
            "event" => "update",
            "updatedFields" => ["qty", "price", "rowTotal"]
        ];

        //WHEN everything is OK
        $this->orderItemsMapper->handle($item);

        $orderItemAfter = $this->orderItemsMapper->getOrderItem($wcOrderItemId);

        $dataAfter = [
            'qty' => $orderItemAfter->get_quantity(),
            'rowTotal' => $orderItemAfter->get_total()
        ];
        self::assertEquals(count(array_intersect_assoc($dataBefore, $dataAfter)),0);
    }

    public function testOrderItemCanNotBeHandledBecauseWrongIntegration() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to handle because integration Id does not match');
        //GIVEN
        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        $wcOrderItemId = $wcOrder->add_product(self::$product, self::QTY);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID);

        $item = [
            "entity" => "order_items",
            "data" => [
                "order" => "/orders/".self::TM_ORDER_ID,
                "qty" => self::TEST_QTY,
                "price" => self::PRICE,
                "rowTotal" => self::TEST_QTY*self::PRICE,
                "externalSourceSyncId" => $wcOrderItemId,
                "externalSourceIntegration" => '/workflows/XXXXXXXX',
                "id" => self::TM_ORDER_ITEM_ID,
            ],
            "event" => "update",
            "updatedFields" => ["qty", "price", "rowTotal"]
        ];

        //WHEN external source is wrong
        $wrongItem = $item;
        $wrongItem['data']['integration'] = '/workflows/wp-test-0001';
        $this->orderItemsMapper->handle($wrongItem);

    }

    public function testOrderItemCanNotBeHandledBecauseUnknownOrderItem() {

        $this->expectException(EndpointException::class);
        $this->expectExceptionMessage('Unable to handle order item because TrackMage Order Item Id not found or does not match');
        //GIVEN
        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        $wcOrderItemId = $wcOrder->add_product(self::$product, self::QTY);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID);

        $item = [
            "entity" => "order_items",
            "data" => [
                "order" => "/orders/".self::TM_ORDER_ID,
                "qty" => self::TEST_QTY,
                "price" => self::PRICE,
                "rowTotal" => self::TEST_QTY*self::PRICE,
                "externalSourceSyncId" => $wcOrderItemId,
                "externalSourceIntegration" => '/workflows/'.self::INTEGRATION,
                "id" => self::TM_ORDER_ITEM_ID,
            ],
            "event" => "update",
            "updatedFields" => ["qty", "price", "rowTotal"]
        ];

        //WHEN unknown shipment item
        $wrongItem = $item;
        $wrongItem['data']['externalSourceSyncId'] = 99999;
        //$wrongItem['data']['id'] = rand(1000,9999);
        $this->orderItemsMapper->handle($wrongItem);
    }

    public function testOrderItemCanNotBeHandledBecauseUnknownOrder() {

        $this->expectException(EndpointException::class);
        $this->expectExceptionMessage('Unable to handle order item because TrackMage Order Id does not match');
        //GIVEN
        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        $wcOrderItemId = $wcOrder->add_product(self::$product, self::QTY);
        wc_add_order_item_meta($wcOrderItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID);

        $item = [
            "entity" => "order_items",
            "data" => [
                "order" => "/orders/".self::TM_ORDER_ID,
                "qty" => self::TEST_QTY,
                "price" => self::PRICE,
                "rowTotal" => self::TEST_QTY*self::PRICE,
                "externalSourceSyncId" => $wcOrderItemId,
                "externalSourceIntegration" => '/workflows/'.self::INTEGRATION,
                "id" => self::TM_ORDER_ITEM_ID,
            ],
            "event" => "update",
            "updatedFields" => ["qty", "price", "rowTotal"]
        ];
        //WHEN wrong order
        $wrongItem = $item;
        $wrongItem['data']['order'] = "/orders/wrong-test-order";
        $this->orderItemsMapper->handle($wrongItem);

    }
}
