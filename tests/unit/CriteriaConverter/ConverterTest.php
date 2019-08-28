<?php

namespace TrackMage\WordPress\Tests\unit\CriteriaConverter;

use Codeception\Test\Unit;
use TrackMage\WordPress\CriteriaConverter\Converter;

class ConverterTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /** @var Converter */
    private $converter;

    protected function _before()
    {
        $this->converter = new Converter();
    }

    public function test_getSqlForCriteria_AND()
    {
        $SQL = $this->converter->getSqlForCriteria(
            ['$and'=> ['x'  => 2,
                '$or'=> ['y'=> 5, 'z'=> 1]]
            ]);
        $this->assertEquals($SQL, '(`x` = 2 AND (`y` = 5 OR `z` = 1))');
    }

    public function test_getSqlForCriteria_OR()
    {
        $SQL = $this->converter->getSqlForCriteria(
            ['x'=> 2, '$or'=> ['y'=> 5, 'z'=> 1]]);
        $this->assertEquals($SQL, '`x` = 2 AND (`y` = 5 OR `z` = 1)');
    }

    public function test_getSqlForCriteria_multipleArgs()
    {
        $SQl = $this->converter->getSqlForCriteria(
            ['a'  => 1,
                'b'  => 2,
                '$or'=> ['c'   => 3,
                    '$and'=> ['d'=> 4, 'e'=> 5]],
                'f'  => 6
            ]);
        $this->assertEquals($SQl, '`a` = 1 AND `b` = 2 AND (`c` = 3 OR (`d` = 4 AND `e` = 5)) AND `f` = 6');
    }
}
