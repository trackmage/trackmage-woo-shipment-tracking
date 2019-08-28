<?php

namespace TrackMage\WordPress\Tests\wpunit\Repository;

use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Repository\LogRepository;
use WpunitTester;

class LogRepositoryTest extends WPTestCase
{
    /** @var WpunitTester */
    protected $tester;

    /** @var LogRepository */
    private $repo;

    protected function _before()
    {
        global $wpdb;
        $this->repo = new LogRepository($wpdb, true);
    }

    public function testCriticalPath()
    {
        $row = $this->repo->insert( $submitted = [
            'message' => 'hello',
            'context' => '{}',
        ]);
        self::assertNotNull($row);
        $id = $row['id'];
        self::assertIsNumeric($id);
        //find
        self::assertArraySubset($submitted, $this->repo->find($id));

        //findOneBy
        $this->repo->insert( $submitted = [
            'message' => 'world',
            'context' => '{}',
        ]);

        self::assertArraySubset($submitted, $this->repo->findOneBy(['message' => 'world']));
        //findBy
        self::assertCount(2, $this->repo->findBy([]));
        self::assertCount(1, $this->repo->findBy(['message' => 'world']));
        //delete
        self::assertEquals(1, $this->repo->delete(['message' => 'world']));
        self::assertCount(1, $this->repo->findBy([]));
        self::assertEquals(1, $this->repo->delete([]));
        self::assertCount(0, $this->repo->findBy([]));
    }
}
