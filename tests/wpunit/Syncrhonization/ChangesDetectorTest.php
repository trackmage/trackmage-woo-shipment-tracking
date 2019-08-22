<?php

use Codeception\TestCase\WPTestCase;
use TrackMage\WordPress\Syncrhonization\ArrayAccessDecorator;
use TrackMage\WordPress\Syncrhonization\ChangesDetector;
use TrackMage\WordPress\Exception\InvalidArgumentException;

class ChangesDetectorTest extends WPTestCase
{
    private $hash;

    public function testRequiresNotEmptyFields()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fields must be specified');

        new ChangesDetector([], null, null);
    }

    public function testIsChangedRequiresArrayAccess()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity must provide array access');

        $detector = new ChangesDetector(['a'], null, null);
        $detector->isChanged(new \DateTime());
    }

    public function testLockChangesRequiresArrayAccess()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity must provide array access');

        $detector = new ChangesDetector(['a'], null, null);
        $detector->lockChanges(new \DateTime());
    }

    public function testNotChanged()
    {
        $order = new ArrayObject([
            'a' => '1',
        ]);
        $detector = new ChangesDetector(['[a]'], function() { return md5('1'); }, null);
        self::assertFalse($detector->isChanged($order));
    }

    public function testChanged()
    {
        $order = new ArrayObject([
            'a' => '1',
        ]);
        $detector = new ChangesDetector(['[a]'], function() { return md5('1'); }, null);
        self::assertFalse($detector->isChanged($order));
        $order['a'] = '2';
        self::assertTrue($detector->isChanged($order));
    }

    public function testNotChangedWhenModifyNotRelatedFields()
    {
        $order = new ArrayObject([
            'a' => '1',
            'b' => '1',
        ]);
        $detector = new ChangesDetector(['[a]'], function() { return md5('1'); }, null);
        self::assertFalse($detector->isChanged($order));
        $order['b'] = '2';
        self::assertFalse($detector->isChanged($order));
    }

    public function testObjectNotChangedAfterLock()
    {
        $order = new ArrayObject([
            'a' => '1',
        ]);
        $detector = new ChangesDetector(['[a]'], function($order) {
            return isset($order['_hash']) ? $order['_hash'] : '';
        }, function($order, $hash) {
            $order['_hash'] = $hash;
            return $order;
        });
        self::assertTrue($detector->isChanged($order));
        $order = $detector->lockChanges($order);
        self::assertFalse($detector->isChanged($order));
    }

    public function testArrayNotChangedAfterLock()
    {
        $order = ['a' => '1'];
        $detector = new ChangesDetector(['[a]'], function($order) {
            return isset($order['_hash']) ? $order['_hash'] : '';
        }, function($order, $hash) {
            $order['_hash'] = $hash;
            return $order;
        });
        self::assertTrue($detector->isChanged($order));
        $order = $detector->lockChanges($order);
        self::assertFalse($detector->isChanged($order));
    }


    public function testOrderNotChangedAfterLock()
    {
        $this->hash = '';
        $order = new WC_Order();

        $order->set_id(100);
        $order->set_status('pending');

        $decorated = new ArrayAccessDecorator($order);
        $detector = new ChangesDetector(['[id]', '[status]'], function($order) {
            return $this->hash;
        }, function($order, $hash) {
            $this->hash = $hash;
            return $order;
        });
        self::assertTrue($detector->isChanged($decorated));
        $decorated = $detector->lockChanges($decorated);
        self::assertFalse($detector->isChanged($decorated));
    }
}
