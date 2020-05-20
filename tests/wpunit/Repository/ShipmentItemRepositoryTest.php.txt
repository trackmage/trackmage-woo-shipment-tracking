<?php

namespace TrackMage\WordPress\Tests\wpunit\Repository;

use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use WpunitTester;

class ShipmentItemRepositoryTest extends WPTestCase
{
    /** @var WpunitTester */
    protected $tester;

    /** @var ShipmentItemRepository */
    private $repo;

    protected function _before()
    {
        global $wpdb;
        $this->repo = new ShipmentItemRepository($wpdb, true);
        $this->repo->delete([]);
    }

    public function testCriticalPath()
    {
        $row = $this->repo->insert( $submitted = [
            'order_item_id' => 10,
            'shipment_id' => 5,
            'qty' => 2,
            'trackmage_id' => 99,
        ]);
        self::assertNotNull($row);
        $id = $row['id'];
        self::assertTrue(is_numeric($id), "{$id} is not numeric");
        //find
        self::assertArraySubset($submitted, $this->repo->find($id));

        //findOneBy
        $data = $this->repo->insert( $submitted = [
            'order_item_id' => 20,
            'shipment_id' => 5,
            'qty' => 2,
            'trackmage_id' => 99,
        ]);
        $id = $data['id'];
        self::assertArraySubset($submitted, $this->repo->findOneBy(['order_item_id' => 20]));

        //update
        $this->repo->update( $submitted = [
            'qty' => 5,
        ], ['id' => $id]);
        self::assertArraySubset($submitted, $this->repo->findOneBy(['id' => $id]));

        //findBy
        self::assertCount(2, $this->repo->findBy([]));
        self::assertCount(1, $this->repo->findBy(['order_item_id' => 20]));

        //delete
        self::assertEquals(1, $this->repo->delete(['order_item_id' => 20]));
        self::assertCount(1, $this->repo->findBy([]));
        self::assertEquals(1, $this->repo->delete([]));
        self::assertCount(0, $this->repo->findBy([]));
    }
}
