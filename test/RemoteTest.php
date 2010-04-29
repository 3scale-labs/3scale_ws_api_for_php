<?php

if (getenv('TEST_3SCALE_PROVIDER_KEY') && getenv('TEST_3SCALE_USER_KEYS')) {
  error_reporting(E_ALL & ~E_DEPRECATED);
  require_once('simpletest/unit_tester.php');
  require_once('simpletest/autorun.php');
  error_reporting(E_ALL | E_NOTICE);

  date_default_timezone_set('Europe/Madrid');

  require_once(dirname(__FILE__) . '/../lib/ThreeScaleClient.php');

  class RemoteTest extends UnitTestCase {
    function setUp() {
      $this->providerKey = getenv('TEST_3SCALE_PROVIDER_KEY');
      $this->userKeys = explode(',', getenv('TEST_3SCALE_USER_KEYS'));
      $this->userKeys = array_map('trim', $this->userKeys);

      $this->client = new ThreeScaleClient($this->providerKey);
    }

    function testSuccessfulAuthorize() {
      $response = $this->client->authorize($this->userKeys[0]);
      $this->assertTrue($response->isSuccess());
    }

    function testFailedAuthorize() {
      $response = $this->client->authorize('invalid-user-key');
      $this->assertFalse($response->isSuccess());

      $errors = $response->getErrors();
      $this->assertEqual('user.invalid_key', $errors[0]->getCode());
    }

    function testSuccessfulReport() {
      $transactions = array();
      foreach ($this->userKeys as $userKey) {
        array_push($transactions, array('user_key' => $userKey, 'usage' => array('hits' => 1)));
      }

      $response = $this->client->report($transactions);
      $this->assertTrue($response->isSuccess());
    }

    function testFailedReport() {
      $transactions = array();
      foreach ($this->userKeys as $userKey) {
        array_push($transactions, array('user_key' => $userKey, 'usage' => array('hits' => 1)));
      }

      $transactions[0]['user_key'] = 'invalid-user-key';

      $response = $this->client->report($transactions);
      $this->assertFalse($response->isSuccess());

      $errors = $response->getErrors();
      $this->assertEqual(1, count($errors));
      $this->assertEqual(0, $errors[0]->getIndex());
      $this->assertEqual('user.invalid_key', $errors[0]->getCode());
    }
  }

} else {
  echo "You need to set enviroment variables TEST_3SCALE_PROVIDER_KEY and TEST_3SCALE_USER_KEYS to run this remote test.\n";
}
