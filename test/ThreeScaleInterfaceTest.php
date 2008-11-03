<?php

require_once(dirname(__FILE__) . '/simpletest/unit_tester.php');
require_once(dirname(__FILE__) . '/simpletest/autorun.php');

require_once(dirname(__FILE__) . '/../lib/ThreeScaleInterface.php');

class ThreeScaleInterfaceTest extends UnitTestCase {
  private $interface;

  function setUp() {
    $this->interface = new ThreeScaleInterface('http://3scale.net', 'some_key');
  }

  function testShouldIdentify3scaleKeys() {
    $this->assertTrue($this->interface->isSystemKey('3scale-foo'));
    $this->assertFalse($this->interface->isSystemKey('foo'));
  }

}

?>
