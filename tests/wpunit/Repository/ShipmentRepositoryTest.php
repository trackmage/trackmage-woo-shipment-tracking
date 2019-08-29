<?php

namespace TrackMage\WordPress\Tests\wpunit\Repository;

use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Repository\ShipmentRepository;
use WpunitTester;

class ShipmentRepositoryTest extends WPTestCase
{
    /** @var WpunitTester */
    protected $tester;

    /** @var ShipmentRepository */
    private $repo;

    protected function _before()
    {
        global $wpdb;
        $this->repo = new ShipmentRepository($wpdb, true);
        $this->repo->delete([]);
    }

    public function testCriticalPath()
    {
        $row = $this->repo->insert( $submitted = [
            'order_id' => 10,
            'tracking_number' => 'ABC',
            'carrier' => 'ups',
            'status' => 'pending',
            'trackmage_id' => 99,
        ]);
        self::assertNotNull($row);
        $id = $row['id'];
        self::assertIsNumeric($id);
        //find
        self::assertArraySubset($submitted, $this->repo->find($id));

        //findOneBy
        $data = $this->repo->insert( $submitted = [
            'order_id' => 20,
            'tracking_number' => 'ABC',
            'carrier' => 'ups',
            'status' => 'pending',
            'trackmage_id' => 99,
        ]);
        $id = $data['id'];
        self::assertArraySubset($submitted, $this->repo->findOneBy(['order_id' => 20]));

        //update
        $this->repo->update( $submitted = [
            'tracking_number' => 'CDE',
        ], ['id' => $id]);
        self::assertArraySubset($submitted, $this->repo->findOneBy(['id' => $id]));

        //findBy
        self::assertCount(2, $this->repo->findBy([]));
        self::assertCount(1, $this->repo->findBy(['order_id' => 20]));

        //delete
        self::assertEquals(1, $this->repo->delete(['order_id' => 20]));
        self::assertCount(1, $this->repo->findBy([]));
        self::assertEquals(1, $this->repo->delete([]));
        self::assertCount(0, $this->repo->findBy([]));
    }
}
