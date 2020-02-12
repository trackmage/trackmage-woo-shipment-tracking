<?php

namespace TrackMage\WordPress\Tests\wpunit\Syncrhonization;

use Codeception\TestCase\WPTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleMockTrait;
use TrackMage\WordPress\Exception\SynchronizationException;
use TrackMage\WordPress\Plugin;
use TrackMage\WordPress\Synchronization\OrderItemSync;
use WC_Product_Simple;
use WpunitTester;

class OrderItemSyncTest extends WPTestCase
{
    use GuzzleMockTrait;

    const QTY = 1;
    const TM_WS_ID = '1001';
    const TM_WEBHOOK_ID = '0110';
    const TM_ORDER_ID = 'tm-order-id';
    const TM_ORDER_ITEM_ID = 'tm-order-item-id';
    const PRODUCT_NAME = 'Test Product';
    const PRICE = '100';
    const INTEGRATION = 'tm-integration-id';

    /** @var WpunitTester */
    protected $tester;

    /** @var WC_Product_Simple */
    private static $product;

    /** @var OrderItemSync */
    private $orderItemSync;

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
        $this->orderItemSync = new OrderItemSync(self::INTEGRATION);
    }

    public function testOrderItemIsNotPostedBecauseOrderMustBeSyncedFirst()
    {
        $this->expectException(SynchronizationException::class);
        $this->expectExceptionMessage('Unable to sync order item because order is not yet synced');

        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderItemSync->sync($wcItemId);
    }

    public function testNewOrderItemGetsPosted()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        //programmatically create an order in WC
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        //check this order is sent to TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/order_items', ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'order' => '/orders/' . self::TM_ORDER_ID,
            'productName' => self::PRODUCT_NAME,
            'qty' => self::QTY,
//            'price' => self::PRICE, TODO: price is empty
            'rowTotal' => self::PRICE,
            'externalSyncId' => $wcItemId,
            'integration' => '/workflows/'.self::INTEGRATION,
        ], $requests[0]['request']);
        //make sure that TM ID is saved to WC order item meta
        self::assertSame(self::TM_ORDER_ITEM_ID, wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    public function testAlreadySyncedOrderItemSendsUpdateToTrackMage()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta($wcItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/order_items/'.self::TM_ORDER_ITEM_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        $this->assertSubmittedJsonIncludes([
            'productName' => self::PRODUCT_NAME,
            'qty' => self::QTY,
//            'price' => self::PRICE, TODO: price is empty
            'rowTotal' => self::PRICE,
        ], $requests[0]['request']);
    }

    public function testAlreadySyncedOrderItemIsNotSentTwice()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(200, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta($wcItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        //WHEN
        $this->orderItemSync->sync($wcItemId);
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/order_items/'.self::TM_ORDER_ITEM_ID],
        ]);
        self::assertCount(1, $requests);
    }

    public function testIfSameExistsItLookUpIdByExternalSyncId()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(400, ['hydra:description' => 'externalSyncId: This value is already used.']),
            $this->createJsonResponse(200, ['hydra:member' => [['id' => self::TM_ORDER_ITEM_ID]]]),
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['POST', '/order_items'],
            ['GET', '/orders/'.self::TM_ORDER_ID.'/items', ['externalSyncId' => $wcItemId, 'integration' => '/workflows/'.self::INTEGRATION]],
            ['PUT', '/order_items/'.self::TM_ORDER_ITEM_ID],
        ]);
        //make sure that TM ID is saved to WC order item meta
        self::assertSame(self::TM_ORDER_ITEM_ID, wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }


    public function testAlreadySyncedButDeletedOrderItemGetsPostedOnceAgain()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);

        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(404),
            $this->createJsonResponse(201, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order item in WC linked to not existing TM id
        $wcOrder = wc_create_order(['status' => 'completed']);
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta( $wcItemId, '_trackmage_order_item_id', 'tm-old-order-item-id', true );

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        // make sure it updates the linked order item in TM
        $this->assertMethodsWereCalled($requests, [
            ['PUT', '/order_items/tm-old-order-item-id'],
            ['POST', '/order_items'],
        ]);
        //make sure that TM ID is saved to WC order item meta
        self::assertSame(self::TM_ORDER_ITEM_ID, wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    public function testOrderItemIsNotPostedIfOrderCannotBeSynced()
    {
        //GIVEN
        update_option('trackmage_sync_statuses', ['wc-completed']);
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        $wcOrder = wc_create_order(); //status is pending
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderItemSync->sync($wcItemId);

        //THEN
        self::assertCount(0, $requests);
    }

    public function testAlreadySyncedOrderItemSendsDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([
            $this->createJsonResponse(204, ['id' => self::TM_ORDER_ITEM_ID]),
        ], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order and order item in TM
        $wcOrder = wc_create_order();
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        add_post_meta( $wcId, '_trackmage_order_id', self::TM_ORDER_ID, true );
        wc_add_order_item_meta($wcItemId, '_trackmage_order_item_id', self::TM_ORDER_ITEM_ID, true);

        //WHEN
        $this->orderItemSync->delete($wcItemId);

        //THEN
        $this->assertMethodsWereCalled($requests, [
            ['DELETE', '/order_items/'.self::TM_ORDER_ITEM_ID, ['ignoreWebhookId' => self::TM_WEBHOOK_ID]],
        ]);
        self::assertSame('', wc_get_order_item_meta($wcItemId, '_trackmage_order_item_id', true));
    }

    public function testNotSyncedOrderItemIgnoresDelete()
    {
        //GIVEN
        $requests = [];
        $guzzleClient = $this->createClient([], $requests);
        $this->initPlugin($guzzleClient);

        // pre-create order in TM
        $wcOrder = wc_create_order();
        $wcItemId = $wcOrder->add_product(self::$product);
        $wcId = $wcOrder->get_id();

        //WHEN
        $this->orderItemSync->delete($wcItemId);

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
