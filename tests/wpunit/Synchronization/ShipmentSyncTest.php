<?php

namespace TrackMage\WordPress\Tests\wpunit\Syncrhonization;

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleMockTrait;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Synchronization\ShipmentSync;
use WC_Product_Simple;
use WC_Product_Variation;

class ShipmentSyncTest extends WPTestCase
{
    use GuzzleMockTrait;
    const INTEGRATION = 'tm-integration-id';
    const TM_SHIPMENT_ID = '1010';
    const TM_ORDER_ID = '1110';
    const TM_ORDER_ITEM_ID = '11100111';
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

    /** @var WC_Product_Simple */
    private static $product;

    /** @var WC_Product_Variation */
    private static $productVariation;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $synchronizer = Plugin::instance()->getSynchronizer();
        $synchronizer->setDisableEvents(true);

        WC()->init();

        $product = new WC_Product_Simple();
        $product->set_name('TEST PRODUCT');
        $product->set_sku('TEST_SKU');
        $product->set_price(100);
        $product->save();
        self::$product = $product;

        //programmatically create a shipment in WC
        $variation_data =  array(
            'attributes' => array(
                'size'  => 'M',
                'color' => 'Green',
            ),
            'sku'           => 'VARIANT_SKU',
            'regular_price' => '100',
            'sale_price'    => '',
            'stock_qty'     => 10,
        );
        self::$productVariation = self::createProductVariation(self::$product->get_id(), $variation_data);

        add_option('trackmage_webhook', self::TM_WEBHOOK_ID);
    }

    protected function _before()
    {
        $this->shipmentSync = new ShipmentSync(self::INTEGRATION);
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

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$productVariation, 5);

        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrder->set_billing_email('email@email.test');
        $wcOrder->set_billing_phone('+123456789');
        $wcOrder->save();
        $wcOrderId = $wcOrder->get_id();
        add_post_meta( $wcOrderId, '_trackmage_order_id', self::TM_ORDER_ID );
        wc_add_order_item_meta($wcItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID);

        $wcShipment = [
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'items' => [
                ['order_item_id' => $wcItemId, 'qty' => 2],
            ]
        ];

        //WHEN
        $this->shipmentSync->sync($wcShipment);

        //THEN
        //check this shipment is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/shipments'],
        ]);

        $data = \GuzzleHttp\json_encode([
            'workspace' => '/workspaces/'.self::TM_WS_ID,
            'trackingNumber' => self::TEST_TRACKING_NUMBER,
            'originCarrier' => self::TEST_CARRIER,
            'externalSourceIntegration' => '/workflows/'.self::INTEGRATION,
            'email' => $wcOrder->get_billing_email(),
            'phoneNumber' => $wcOrder->get_billing_phone(),
            'orders' => ['/orders/'.self::TM_ORDER_ID],
            'shipmentItems' => [
                ['qty'=>2, 'orderItem'=>"/order_items/".self::TM_ORDER_ITEM_ID]
            ]
        ]);
        $requestData = (string)$requests[0]['request']->getBody()->getContents();
        $this->assertEquals($data, $requestData);
    }

    public function testAlreadyExistsShipmentSendsUpdateToTrackMage()
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
        $wcShipment = [
            'id' => self::TM_SHIPMENT_ID,
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
        ];

        //WHEN
        $this->shipmentSync->sync($wcShipment);

        //THEN
        // make sure it updates the linked shipment in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/shipments/'.self::TM_SHIPMENT_ID],
        ]);

        $data = \GuzzleHttp\json_encode([
            'trackingNumber' => self::TEST_TRACKING_NUMBER,
            'email' => $wcOrder->get_billing_email(),
            'phoneNumber' => $wcOrder->get_billing_phone(),
            'originCarrier' => self::TEST_CARRIER
        ]);
        $requestData = (string)$requests[0]['request']->getBody()->getContents();
        $this->assertEquals($data, $requestData);
    }


    public function testShipmentSendsDelete()
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

        //WHEN
        $this->shipmentSync->delete(self::TM_SHIPMENT_ID);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/shipments/'.self::TM_SHIPMENT_ID],
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
