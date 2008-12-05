<?php

require_once('simpletest/unit_tester.php');
require_once('simpletest/autorun.php');

require_once(dirname(__FILE__) . '/../lib/ThreeScaleInterface.php');

error_reporting(E_ALL);

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
    $this->interface = new ThreeScaleInterface('http://3scale.net',
      'pak123', $this->http);
  }

  // "start" tests

  function testStartShouldThrowExceptionOnInvalidUserKey() {
    $this->http->setReturnValue('post',
      $this->stubError('user.invalid_key', 403));

    $this->expectException(new ThreeScaleUserKeyInvalid);
    $this->interface->start('invalid_key');
  }

  function testStartShouldThrowExceptionOnInvalidProviderKey() {
    $this->http->setReturnValue('post',
      $this->stubError('provider.invalid_key', 403));

    $this->expectException(new ThreeScaleProviderKeyInvalid);
    $this->interface->start('uk456');
  }

  function testStartShouldThrowExceptionOnInactiveContract() {
    $this->http->setReturnValue('post',
      $this->stubError('user.inactive_contract', 403));

    $this->expectException(new ThreeScaleContractNotActive);
    $this->interface->start('uk456');
  }

  function testStartShouldThrowExceptionOnInvalidMetric() {
    $this->http->setReturnValue('post',
      $this->stubError('provider.invalid_metric', 400));

    $this->expectException(new ThreeScaleMetricInvalid);
    $this->interface->start('uk456');
  }

  function testStartShouldThrowExceptionOnExceededLimits() {
    $this->http->setReturnValue('post',
      $this->stubError('user.exceeded_limits', 403));

    $this->expectException(new ThreeScaleLimitsExceeded);
    $this->interface->start('uk456');
  }

  function testStartShouldThrowExceptionOnUnexpectedError() {
    $this->http->setReturnValue('post', $this->stubResponse('', 500));

    $this->expectException(new ThreeScaleUnknownException);
    $this->interface->start('uk456');
  }

  function testStartShouldSendUsageData() {
    $this->http->expectOnce('post', array('http://3scale.net/transactions.xml',
        array('user_key' => 'uk456', 'provider_key' => 'pak123',
          'usage' => array('hits' => 1))));
    $this->http->setReturnValue('post',
      $this->stubResponse('<transaction></transaction>', 200));

    $this->interface->start('uk456', array('hits' => 1));
  }

  function testStartShouldReturnTransactionDataOnSuccess() {
    $body = '<transaction>
               <id>42</id>
               <provider_verification_key>pvk789</provider_verification_key>
               <contract_name>ultimate</contract_name>
             </transaction>';

    $this->http->setReturnValue('post', $this->stubResponse($body, 200));

    $result = $this->interface->start('uk456', array('clicks' => 1));

    $this->assertIdentical('42', $result['id']);
    $this->assertIdentical('pvk789', $result['provider_verification_key']);
    $this->assertIdentical('ultimate', $result['contract_name']);
  }

  function testStartShouldStrip3scalePrefixFromUserKeyBeforeSending() {
    $this->http->expectOnce('post', array('*',
        array('user_key' => 'foo', 'provider_key' => 'pak123',
        'usage' => array())));
    $this->http->setReturnValue('post',
      $this->stubResponse('<transaction></transaction>', 200));

    $this->interface->start('3scale-foo');
  }

  function testStartShouldLeaveUserKeyUnchangedIfItDoesNotContain3scalePrefix() {
    $this->http->expectOnce('post', array('*',
        array('user_key' => 'foo', 'provider_key' => 'pak123',
        'usage' => array())));
    $this->http->setReturnValue('post',
      $this->stubResponse('<transaction></transaction>', 200));

    $this->interface->start('foo');
  }

  // "confirm" tests

  function testConfirmShouldThrowExceptionOnInvalidTransaction() {
    $this->http->setReturnValue('post',
      $this->stubError('provider.invalid_transaction_id', 404));

    $this->expectException(new ThreeScaleTransactionNotFound);
    $this->interface->confirm(42);
  }

  function testConfirmShouldThrowExceptionOnInvalidProviderKey() {
    $this->http->setReturnValue('post',
      $this->stubError('provider.invalid_key', 403));

    $this->expectException(new ThreeScaleProviderKeyInvalid);
    $this->interface->confirm(42);
  }

  function testConfirmShouldThrowExceptionOnInvalidMetric() {
    $this->http->setReturnValue('post',
      $this->stubError('provider.invalid_metric', 400));

    $this->expectException(new ThreeScaleMetricInvalid);
    $this->interface->confirm(42);
  }

  function testConfirmShouldReturnTrueOnSuccess() {
    $this->http->setReturnValue('post', $this->stubResponse('', 200));

    $result = $this->interface->confirm(42);
    $this->assertTrue($result);
  }

  function testConfirmShouldSendUsageData() {
    $this->http->expectOnce('post', array(
      'http://3scale.net/transactions/42/confirm.xml',
        array('provider_key' => 'pak123', 'usage' => array('hits' => 1))));
    $this->http->setReturnValue('post', $this->stubResponse('', 200));

    $this->interface->confirm(42, array('hits' => 1));
  }

  // "cancel" tests

  function testCancelShouldThrowExceptionOnInvalidTransaction() {
    $this->http->setReturnValue('delete',
      $this->stubError('provider.invalid_transaction_id', 404));

    $this->expectException(new ThreeScaleTransactionNotFound);
    $this->interface->cancel(42);
  }

  function testCancelShouldThrowExceptionOnInvalidProviderKey() {
    $this->http->setReturnValue('delete',
      $this->stubError('provider.invalid_key', 403));

    $this->expectException(new ThreeScaleProviderKeyInvalid);
    $this->interface->cancel(42);
  }

  function testCancelShouldThrowExceptionOnUnexpectedError() {
    $this->http->setReturnValue('delete', $this->stubResponse('', 500));

    $this->expectException(new ThreeScaleUnknownException);
    $this->interface->cancel(42);
  }

  function testCancelShouldReturnTrueOnSuccess() {
    $this->http->expectOnce('delete',
      array('http://3scale.net/transactions/42.xml',
        array('provider_key' => 'pak123')));

    $this->http->setReturnValue('delete', $this->stubResponse('', 200));

    $result = $this->interface->cancel(42);
    $this->assertTrue($result);
  }


  // other tests

  function testShouldIdentify3scaleKeys() {
    $this->assertTrue(ThreeScaleInterface::isSystemKey('3scale-foo'));
    $this->assertFalse(ThreeScaleInterface::isSystemKey('foo'));
  }

  // helpers

  private function stubError($error_code, $http_code, $body = '') {
    return $this->stubResponse("<error id=\"{$error_code}\">{$body}</error>",
      $http_code);
  }

  private function stubResponse($body, $http_code) {
    $response = new StubResponse;
    $response->headers['Status-Code'] = $http_code;
    $response->body = $body;

    return $response;
  }

}

?>
