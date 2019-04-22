<?php

namespace TrackMage;

include_once __DIR__ . '/../../../autoload/class-utility.php';

use PHPUnit\Framework\TestCase;

define('ABSPATH', true);

class sumTest extends TestCase {

  public function equals($a, $b, $expected){

    $this->assertEquals($expected, Utility::sum($a, $b));
  }

  public function inputData(){

    return [
      [1, 2, 3],
      [5, 5, 10],
    ];
  }

  public function testCount(){
    foreach ($this->inputData() as $data) {
      $this->equals($data[0], $data[1], $data[2]);
    }
  }
}
