<?php
namespace TrackMage\WordPress\Tests\wpunit\Endpoint;


use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleMockTrait;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Webhook\Mappers\ShipmentsMapper;
use TrackMage\WordPress\Synchronization\ChangesDetector;

class ShipmentsMapperTest extends WPTestCase
{

    use GuzzleMockTrait;

    const SOURCE = 'wp';
    const TM_SHIPMENT_ID = '1010';
    const TM_WS_ID = '1001';
    const TEST_TRACKING_NUMBER = 'TN-ABC';
    const TEST_CARRIER = 'UPS';

    /** @var \WpunitTester */
    protected $tester;

    /** @var ShipmentRepository */
    private $shipmentRepo;

    /** @var ShipmentsMapper */
    private $shipmentsMapper;

    public static function _setUpBeforeClass() {
        parent::_setUpBeforeClass();

        $endpoint = Plugin::instance()->getEndpoint();
        $endpoint->setDisableEvents(true);

        WC()->init();
    }

    protected function _before()
    {
        $this->shipmentRepo = Plugin::instance()->getShipmentRepo();
        $this->shipmentsMapper = new ShipmentsMapper($this->shipmentRepo, self::SOURCE);
    }


    public function testShipmentIsUpdated() {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);
        $trackMageId = rand(10000,99999);
        //programmatically create a shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => $trackMageId
        ]);
        $wcShipmentId = $wcShipment['id'];

        $item = [
            'entity'        => 'shipments',
            'data'          =>
                [
                    'id'                     => $trackMageId,
                    'trackingNumber'         => 'DHL0123456789',
                    'status'                 => 'returned',
                    'originCarrier'          => 'ups',
                    'workspace'              => '/workspaces/'.self::TM_WS_ID,
                    'externalSource'         => self::SOURCE,
                    'externalSyncId'         => $wcShipmentId
                ],
            'event'         => 'update',
            'updatedFields' =>
                [
                    0 => 'status',
                    1 => 'originCarrier',
                ],
        ];

        //WHEN everything is OK
        $result = $this->shipmentsMapper->handle($item);

        $shipmentAfter = $this->shipmentRepo->find($wcShipmentId);
        self::assertNotSame($shipmentAfter, $wcShipment);
    }


    public function testShipmentCanBeHandled() {
        //GIVEN
        add_option('trackmage_workspace', self::TM_WS_ID);
        $trackMageId = rand(10000,99999);
        //programmatically create a shipment in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcOrderId = $wcOrder->get_id();
        $wcShipment = $this->shipmentRepo->insert([
            'order_id' => $wcOrderId,
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'carrier' => self::TEST_CARRIER,
            'trackmage_id' => $trackMageId
        ]);
        $wcShipmentId = $wcShipment['id'];

        $item = [
            'entity'        => 'shipments',
            'data'          =>
                [
                    'id'                     => $trackMageId,
                    'trackingNumber'         => 'DHL0123456789',
                    'status'                 => 'delivered',
                    'daysInTransit'          => 6,
                    'originCarrier'          => 'dhl',
                    'workspace'              => '/workspaces/'.self::TM_WS_ID,
                    'externalSource'         => self::SOURCE,
                    'externalSyncId'         => $wcShipmentId
                ],
            'event'         => 'update',
            'updatedFields' =>
                [
                    0 => 'status',
                    1 => 'originCarrier'
                ],
        ];

        //WHEN workspace is wrong
        $wrongItem = $item;
        $wrongItem['data']['workspace'] = '/workspaces/999999';
        $result = $this->shipmentsMapper->handle($wrongItem);
        self::assertEquals($result, -12);

        //WHEN external source is wrong
        $wrongItem = $item;
        $wrongItem['data']['externalSource'] = 'wp-test0001';
        $result = $this->shipmentsMapper->handle($wrongItem);
        self::assertEquals($result, -10);

        //WHEN unknown shipment
        $wrongItem = $item;
        $wrongItem['data']['externalSyncId'] = '99999';
        $wrongItem['data']['id'] = rand(1000,9999);
        $result = $this->shipmentsMapper->handle($wrongItem);
        self::assertEquals($result, -11);

    }

    private function initPlugin(ClientInterface $guzzleClient = null)
    {
        $client = Plugin::get_client();
        if ($guzzleClient !== null) {
            $client->setGuzzleClient($guzzleClient);
        }
    }
}
