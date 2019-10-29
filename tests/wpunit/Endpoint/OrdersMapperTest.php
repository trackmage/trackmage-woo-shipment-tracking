<?php
namespace TrackMage\WordPress\Tests\wpunit\Endpoint;


use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Exception\InvalidArgumentException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Webhook\Mappers\OrdersMapper;
use WC_Order;

class OrdersMapperTest extends WPTestCase
{
    const SOURCE = 'wp-5d9da5faf010c';
    const STATUS = 'completed';

    const TM_WS_ID = 'tm-workspace-id';
    const TM_ORDER_ID = 'tm-order-id';
    const TM_ORDER_ITEM_ID = 'tm-order-item-id';

    const ADDRESS = [
        'addressLine1' => 'addr1',
        'addressLine2' => 'addr2',
        'city' => 'City',
        'company' => 'Company LTD',
        'countryIso2' => 'US',
        'firstName' => 'FN',
        'lastName' => 'LN',
        'postcode' => '12345',
        'state' => 'FL',
    ];

    const TEST_STATUS = 'pending';
    const TEST_ADDRESS = [
        'addressLine1' => 'test_addr1',
        'addressLine2' => 'test_addr2',
        'city' => 'TestCity',
        'company' => 'Test Company LTD',
        'countryIso2' => 'CN',
        'firstName' => 'TEST FN',
        'lastName' => 'TEST LN',
        'postcode' => '54321',
        'state' => 'CN2',
    ];

    /** @var \WpunitTester */
    protected $tester;

    /** @var OrdersMapper */
    private $ordersMapper;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $endpoint = Plugin::instance()->getEndpoint();
        $endpoint->setDisableEvents(true);

        WC()->init();
    }

    protected function _before()
    {
        add_option('trackmage_workspace', self::TM_WS_ID);
        $this->ordersMapper = new OrdersMapper(self::SOURCE);
    }


    public function testOrderIsFullyUpdated() {
        update_option('trackmage_workspace', self::TM_WS_ID);
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed', 'wc-pending' => 'pending']);
        //GIVEN

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcOrder->set_shipping_address_1(self::ADDRESS['addressLine1']);
        $wcOrder->set_shipping_address_2(self::ADDRESS['addressLine2']);
        $wcOrder->set_shipping_city(self::ADDRESS['city']);
        $wcOrder->set_shipping_company(self::ADDRESS['company']);
        $wcOrder->set_shipping_country(self::ADDRESS['countryIso2']);
        $wcOrder->set_shipping_first_name(self::ADDRESS['firstName']);
        $wcOrder->set_shipping_last_name(self::ADDRESS['lastName']);
        $wcOrder->set_shipping_postcode(self::ADDRESS['postcode']);
        $wcOrder->set_shipping_state(self::ADDRESS['state']);
        $wcOrder->set_billing_address_1(self::ADDRESS['addressLine1']);
        $wcOrder->set_billing_address_2(self::ADDRESS['addressLine2']);
        $wcOrder->set_billing_city(self::ADDRESS['city']);
        $wcOrder->set_billing_company(self::ADDRESS['company']);
        $wcOrder->set_billing_country(self::ADDRESS['countryIso2']);
        $wcOrder->set_billing_first_name(self::ADDRESS['firstName']);
        $wcOrder->set_billing_last_name(self::ADDRESS['lastName']);
        $wcOrder->set_billing_postcode(self::ADDRESS['postcode']);
        $wcOrder->set_billing_state(self::ADDRESS['state']);
        $wcOrder->save();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        $statusBefore = $wcOrder->get_status();
        $shippingAddressBefore = $this->getShippingAddress($wcOrder);
        $billingAddressBefore = $this->getBillingAddress($wcOrder);

        $item = [
            "entity" => "orders",
            "data" => [
                "workspace" => "/workspaces/".self::TM_WS_ID,
                "orderNumber" => $wcId,
                "externalSource" => self::SOURCE,
                "externalSyncId" => $wcId,
                "orderStatus" => [
                    "code" => self::TEST_STATUS,
                    "title" => "Pending"
                ],
                "shippingAddress" => [
                    "city" => self::TEST_ADDRESS['city'],
                    "state" => self::TEST_ADDRESS['state'],
                    "company" => self::TEST_ADDRESS['company'],
                    "country" => self::TEST_ADDRESS['countryIso2'],
                    "lastName" => self::TEST_ADDRESS['lastName'],
                    "postcode" => self::TEST_ADDRESS['postcode'],
                    "firstName" => self::TEST_ADDRESS['firstName'],
                    "countryIso2" => self::TEST_ADDRESS['countryIso2'],
                    "addressLine1" => self::TEST_ADDRESS['addressLine1'],
                    "addressLine2" => self::TEST_ADDRESS['addressLine2']
                ],
                "billingAddress" => [
                    "city" => self::TEST_ADDRESS['city'],
                    "state" => self::TEST_ADDRESS['state'],
                    "company" => self::TEST_ADDRESS['company'],
                    "country" => self::TEST_ADDRESS['countryIso2'],
                    "lastName" => self::TEST_ADDRESS['lastName'],
                    "postcode" => self::TEST_ADDRESS['postcode'],
                    "firstName" => self::TEST_ADDRESS['firstName'],
                    "countryIso2" => self::TEST_ADDRESS['countryIso2'],
                    "addressLine1" => self::TEST_ADDRESS['addressLine1'],
                    "addressLine2" => self::TEST_ADDRESS['addressLine2']
                ],
                "id" => self::TM_ORDER_ID
            ],
            "event" => "update",
            "updatedFields" => [
                "shippingAddress.addressLine1",
                "shippingAddress.addressLine2",
                "shippingAddress.city",
                "shippingAddress.company",
                "shippingAddress.country",
                "shippingAddress.countryIso2",
                "shippingAddress.firstName",
                "shippingAddress.lastName",
                "shippingAddress.postcode",
                "shippingAddress.state",
                "billingAddress.addressLine1",
                "billingAddress.addressLine2",
                "billingAddress.city",
                "billingAddress.company",
                "billingAddress.country",
                "billingAddress.countryIso2",
                "billingAddress.firstName",
                "billingAddress.lastName",
                "billingAddress.postcode",
                "billingAddress.state",
                "orderStatus"
            ]
        ];

        //WHEN everything is OK
        $this->ordersMapper->handle($item);

        //THEN

        $wcOrderAfterChanges = wc_get_order($wcId);
        $statusAfter = $wcOrderAfterChanges->get_status();

        $shippingAddressAfter = $this->getShippingAddress($wcOrderAfterChanges);
        $billingAddressAfter = $this->getBillingAddress($wcOrderAfterChanges);

        self::assertNotEquals($statusBefore, $statusAfter, 'Status was not changed');

        $shippingDifferences = array_intersect_assoc($shippingAddressBefore, $shippingAddressAfter);
        self::assertEquals(count($shippingDifferences),0, 'There are not changed fields in shipping address');

        $billingDifferences = array_intersect_assoc($billingAddressBefore, $billingAddressAfter);
        self::assertEquals(count($billingDifferences),0, 'There are not changed fields in billing address');
    }

    public function testOrderCanNotBeHandledBecauseWorkspaceIsWrong() {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to handle because workspace is not correct');

        //add_option('trackmage_workspace', self::TM_WS_ID);
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed', 'wc-pending' => 'pending']);
        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcOrder->save();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        $item = [
            "entity" => "orders",
            "data" => [
                "workspace" => "/workspaces/".self::TM_WS_ID,
                "orderNumber" => $wcId,
                "externalSource" => self::SOURCE,
                "externalSyncId" => $wcId,
                "orderStatus" => [
                    "code" => self::TEST_STATUS,
                    "title" => "Pending"
                ],
                "shippingAddress" => [],
                "billingAddress" => [],
                "id" => self::TM_ORDER_ID
            ],
            "event" => "update",
            "updatedFields" => ["orderStatus"]
        ];

        //WHEN workspace is wrong
        $wrongItem = $item;
        $wrongItem['data']['workspace'] = '/workspaces/999999';
        $this->ordersMapper->handle($wrongItem);

    }

    public function testOrderCanNotBeHandledBecauseExternalSourceIsWrong() {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to handle because external source does not match');

        //GIVEN
        //add_option('trackmage_workspace', self::TM_WS_ID);
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed', 'wc-pending' => 'pending']);
        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcOrder->save();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        $item = [
            "entity" => "orders",
            "data" => [
                "workspace" => "/workspaces/".self::TM_WS_ID,
                "orderNumber" => $wcId,
                "externalSource" => self::SOURCE,
                "externalSyncId" => $wcId,
                "orderStatus" => [
                    "code" => self::TEST_STATUS,
                    "title" => "Pending"
                ],
                "shippingAddress" => [],
                "billingAddress" => [],
                "id" => self::TM_ORDER_ID
            ],
            "event" => "update",
            "updatedFields" => [ "orderStatus" ]
        ];

        //WHEN external source is wrong
        $wrongItem = $item;
        $wrongItem['data']['externalSource'] = 'wp-test0001';
        $this->ordersMapper->handle($wrongItem);

    }

    public function testOrderCanNotBeHandledBecauseUnknownOrder() {

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to handle because entity was not found');

        //GIVEN
        //add_option('trackmage_workspace', self::TM_WS_ID);
        update_option('trackmage_order_status_aliases', ['wc-completed' => 'completed', 'wc-pending' => 'pending']);
        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => self::STATUS]);
        $wcOrder->save();
        $wcId = $wcOrder->get_id();
        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        $item = [
            "entity" => "orders",
            "data" => [
                "workspace" => "/workspaces/".self::TM_WS_ID,
                "orderNumber" => $wcId,
                "externalSource" => self::SOURCE,
                "externalSyncId" => $wcId,
                "orderStatus" => [
                    "code" => self::TEST_STATUS,
                    "title" => "Pending"
                ],
                "shippingAddress" => [],
                "billingAddress" => [],
                "id" => self::TM_ORDER_ID
            ],
            "event" => "update",
            "updatedFields" => [ "orderStatus" ]
        ];

        //WHEN unknown shipment
        $wrongItem = $item;
        $wrongItem['data']['externalSyncId'] = '99999';
        $wrongItem['data']['id'] = rand(1000,9999);
        $this->ordersMapper->handle($wrongItem);

    }

    /**
     * @return array
     */
    private function getShippingAddress(WC_Order $order)
    {
        $countryIso2 = $order->get_shipping_country();
        $stateCode = $order->get_billing_state();

        return [
            'addressLine1' => $order->get_shipping_address_1(),
            'addressLine2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'company' => $order->get_shipping_company(),
            'countryIso2' => $countryIso2,
            'firstName' => $order->get_shipping_first_name(),
            'lastName' => $order->get_shipping_last_name(),
            'postcode' => $order->get_shipping_postcode(),
            'state' => $stateCode,
        ];
    }

    /**
     * @return array
     */
    private function getBillingAddress(WC_Order $order)
    {
        $countryIso2 = $order->get_billing_country();
        $stateCode = $order->get_billing_state();

        return [
            'addressLine1' => $order->get_billing_address_1(),
            'addressLine2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'company' => $order->get_billing_company(),
            'countryIso2' => $countryIso2,
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'postcode' => $order->get_billing_postcode(),
            'state' => $stateCode,
        ];
    }
}
