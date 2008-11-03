<?php

require_once('simpletest/unit_tester.php');
require_once('simpletest/autorun.php');

require_once(dirname(__FILE__) . '/../lib/ThreeScaleInterface.php');

Mock::generate('Curl');

class StubResponse {
  public $body = '';
  public $headers = array();
}

class ThreeScaleInterfaceTest extends UnitTestCase {
  private $interface;
  private $http;

  function setUp() {
    $this->http = new MockCurl($this);
    $this->interface = new ThreeScaleInterface('http://3scale.net', 'some_key',
      $this->http);
  }

  function testStartShouldThrowExceptionOnInvalidUserKey() {
    $this->http->expectOnce('post',
      array('http://3scale.net/transactions.xml', '*'));
    $this->http->setReturnValue('post',
      $this->stubError(403, 'user.invalid_key'));

    $this->expectException(new ThreeScaleUserKeyInvalid);
    $this->interface->start('invalid_key');
  }

  function testStartShouldThrowExceptionOnInvalidProviderKey() {
    $this->http->expectOnce('post',
      array('http://3scale.net/transactions.xml', '*'));
    $this->http->setReturnValue('post',
      $this->stubError(400, 'provider.invalid_key'));

    $this->expectException(new ThreeScaleProviderKeyInvalid);
    $this->interface->start('valid_key');
  }


  function testShouldIdentify3scaleKeys() {
    $this->assertTrue($this->interface->isSystemKey('3scale-foo'));
    $this->assertFalse($this->interface->isSystemKey('foo'));
  }

  // Stub error response.
  private function stubError($http_code, $error_code) {
    $response = new StubResponse;
    $response->headers['Status-Code'] = $http_code;
    $response->body = "<error id=\"{$error_code}\">blah blah</error>";

    return $response;
  }

}

?>
