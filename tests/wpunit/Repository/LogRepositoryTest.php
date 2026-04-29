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
        $this->repo->delete([]);
    }

    public function testCriticalPath()
    {
        $row = $this->repo->insert( $submitted = [
            'message' => 'hello',
            'context' => '{}',
        ]);
        self::assertNotNull($row);
        $id = $row['id'];
        self::assertTrue(is_numeric($id), "{$id} is not numeric");
        //find
        self::assertArraySubset($submitted, $this->repo->find($id));

        //findOneBy
        $data = $this->repo->insert( $submitted = [
            'message' => 'world',
            'context' => '{}',
        ]);
        $id = $data['id'];
        self::assertArraySubset($submitted, $this->repo->findOneBy(['message' => 'world']));

        //update
        $this->repo->update( $submitted = [
            'context' => '[]',
        ], ['id' => $id]);
        self::assertArraySubset($submitted, $this->repo->findOneBy(['id' => $id]));

        //findBy
        self::assertCount(2, $this->repo->findBy([]));
        self::assertCount(1, $this->repo->findBy(['message' => 'world']));

        //delete
        self::assertEquals(1, $this->repo->delete(['message' => 'world']));
        self::assertCount(1, $this->repo->findBy([]));
        self::assertEquals(1, $this->repo->delete([]));
        self::assertCount(0, $this->repo->findBy([]));
    }

    public function testRotateNoOpOnEmptyTable()
    {
        self::assertEquals(0, $this->repo->rotate(1000));
        self::assertCount(0, $this->repo->findBy([]));
    }

    public function testRotateNoOpWhenAtOrBelowCap()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->repo->insert(['message' => "row {$i}", 'context' => '{}']);
        }
        self::assertEquals(0, $this->repo->rotate(5), 'exactly at cap should be a no-op');
        self::assertEquals(0, $this->repo->rotate(10), 'below cap should be a no-op');
        self::assertCount(5, $this->repo->findBy([]));
    }

    public function testRotateKeepsNewestRows()
    {
        $ids = [];
        for ($i = 1; $i <= 10; $i++) {
            $row = $this->repo->insert(['message' => "row {$i}", 'context' => '{}']);
            $ids[] = (int) $row['id'];
        }
        // Keep newest 4 → expect 6 deletions, ids 1..6 gone, ids 7..10 kept.
        self::assertEquals(6, $this->repo->rotate(4));
        $remaining = $this->repo->findBy([]);
        self::assertCount(4, $remaining);
        $remaining_ids = array_column($remaining, 'id');
        sort($remaining_ids);
        self::assertEquals(array_slice($ids, -4), array_map('intval', $remaining_ids));
    }

    public function testRotateZeroEmptiesTable()
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->insert(['message' => "row {$i}", 'context' => '{}']);
        }
        self::assertEquals(3, $this->repo->rotate(0));
        self::assertCount(0, $this->repo->findBy([]));
    }

    public function testRotateNegativeIsNoOp()
    {
        for ($i = 0; $i < 3; $i++) {
            $this->repo->insert(['message' => "row {$i}", 'context' => '{}']);
        }
        self::assertEquals(0, $this->repo->rotate(-1));
        self::assertCount(3, $this->repo->findBy([]));
    }

    public function testRotateOneOverCapDeletesOnlyOldest()
    {
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $row = $this->repo->insert(['message' => "row {$i}", 'context' => '{}']);
            $ids[] = (int) $row['id'];
        }
        // Cap at 5, table has 6 → exactly one delete (the oldest).
        self::assertEquals(1, $this->repo->rotate(5));
        $remaining = $this->repo->findBy([]);
        self::assertCount(5, $remaining);
        $remaining_ids = array_column($remaining, 'id');
        self::assertNotContains($ids[0], array_map('intval', $remaining_ids), 'oldest id should be gone');
    }
}
