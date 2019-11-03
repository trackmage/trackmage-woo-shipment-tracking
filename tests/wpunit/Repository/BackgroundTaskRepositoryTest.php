<?php

namespace TrackMage\WordPress\Tests\wpunit\Repository;

use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Repository\BackgroundTaskRepository;
use WpunitTester;

class BackgroundTaskRepositoryTest extends WPTestCase
{
    /** @var WpunitTester */
    protected $tester;

    /** @var BackgroundTaskRepository */
    private $repo;

    protected function _before()
    {
        global $wpdb;
        $this->repo = new BackgroundTaskRepository($wpdb, true);
        $this->repo->delete([]);
    }

    public function testCriticalPath()
    {
        $row = $this->repo->insert( $submitted = [
            'action' => 'test_action',
            'params' => '{}',
            'status' => 'status',
        ]);
        self::assertNotNull($row);
        $id = $row['id'];
        self::assertTrue(is_numeric($id), "{$id} is not numeric");
        //find
        self::assertArraySubset($submitted, $this->repo->find($id));

        //findOneBy
        $data = $this->repo->insert( $submitted = [
            'action' => 'new_action',
            'params' => '{orderIds:[]}',
            'status' => 'active',
        ]);
        $id = $data['id'];
        self::assertArraySubset($submitted, $this->repo->findOneBy(['action' => 'new_action','status' => 'active']));

        //update
        $this->repo->update( $submitted = [
            'status' => 'completed',
        ], ['id' => $id]);
        self::assertArraySubset($submitted, $this->repo->findOneBy(['id' => $id]));

        //findBy
        self::assertCount(2, $this->repo->findBy([]));
        self::assertCount(1, $this->repo->findBy(['status' => 'completed']));

        //delete
        self::assertEquals(1, $this->repo->delete(['action' => 'new_action']));
        self::assertCount(1, $this->repo->findBy([]));
        self::assertEquals(1, $this->repo->delete([]));
        self::assertCount(0, $this->repo->findBy([]));
    }
}
