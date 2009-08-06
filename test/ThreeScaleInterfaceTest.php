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
    $this->interface = new ThreeScaleInterface('http://server.3scale.net',
      '3scale-pak123', $this->http);
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
    $this->http->expectOnce('post', array('http://server.3scale.net/transactions.xml',
        array('user_key' => '3scale-uk456', 'provider_key' => '3scale-pak123',
          'usage' => array('hits' => 1))));
    $this->http->setReturnValue('post',
      $this->stubResponse('<transaction></transaction>', 200));

    $this->interface->start('3scale-uk456', array('hits' => 1));
  }

  function testStartShouldReturnTransactionDataOnSuccess() {
    $body = '<transaction>
               <id>42</id>
               <provider_verification_key>3scale-pvk789</provider_verification_key>
               <contract_name>ultimate</contract_name>
             </transaction>';

    $this->http->setReturnValue('post', $this->stubResponse($body, 200));

    $result = $this->interface->start('3scale-uk456', array('clicks' => 1));

    $this->assertIdentical('42', $result['id']);
    $this->assertIdentical('3scale-pvk789', $result['provider_verification_key']);
    $this->assertIdentical('ultimate', $result['contract_name']);
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
      'http://server.3scale.net/transactions/42/confirm.xml',
        array('provider_key' => '3scale-pak123', 'usage' => array('hits' => 1))));
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
      array('http://server.3scale.net/transactions/42.xml',
        array('provider_key' => '3scale-pak123')));

    $this->http->setReturnValue('delete', $this->stubResponse('', 200));

    $result = $this->interface->cancel(42);
    $this->assertTrue($result);
  }

  // "authorize" tests

  function testAuthorizeShouldThrowExceptionOnInvalidUserKey() {
    $this->http->setReturnValue('get',
      $this->stubError('user.invalid_key', 403),
      array(
        'http://server.3scale.net/transactions/authorize.xml',
        array('provider_key' => '3scale-pak123', 'user_key' => 'invalid_key')));


    $this->expectException(new ThreeScaleUserKeyInvalid);
    $this->interface->authorize('invalid_key');
  }

  function testAuthorizeShouldThrowExceptionOnInvalidProviderKey() {
    $this->http->setReturnValue('get',
      $this->stubError('provider.invalid_key', 403),
      array(
        'http://server.3scale.net/transactions/authorize.xml',
        array('provider_key' => '3scale-pak123', 'user_key' => 'uk456')));

    $this->expectException(new ThreeScaleProviderKeyInvalid);
    $this->interface->authorize('uk456');
  }

  function testAuthorizeShouldThrowExceptionOnInactiveContract() {
    $this->http->setReturnValue('get',
      $this->stubError('user.inactive_contract', 403),
      array(
        'http://server.3scale.net/transactions/authorize.xml',
        array('provider_key' => '3scale-pak123', 'user_key' => 'uk456')));

    $this->expectException(new ThreeScaleContractNotActive);
    $this->interface->authorize('uk456');
  }

  function testAuthorizeShouldThrowExceptionOnExceededLimits() {
    $this->http->setReturnValue('get',
      $this->stubError('user.exceeded_limits', 403),
      array(
        'http://server.3scale.net/transactions/authorize.xml',
        array('provider_key' => '3scale-pak123', 'user_key' => 'uk456')));

    $this->expectException(new ThreeScaleLimitsExceeded);
    $this->interface->authorize('uk456');
  }

  function testAuthorizeShouldThrowExceptionOnUnexpectedError() {
    $this->http->setReturnValue('get', $this->stubResponse('', 500),
      array(
        'http://server.3scale.net/transactions/authorize.xml',
        array('provider_key' => '3scale-pak123', 'user_key' => 'uk456')));

    $this->expectException(new ThreeScaleUnknownException);
    $this->interface->authorize('uk456');
  }

  function testAuthorizeShouldReturnTrueOnSuccess() {
    $this->http->setReturnValue('get', $this->stubResponse('', 200),
      array(
        'http://server.3scale.net/transactions/authorize.xml',
        array('provider_key' => '3scale-pak123', 'user_key' => 'uk456')));

    $result = $this->interface->authorize('uk456');
    $this->assertTrue($result);
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
