<?php

if (getenv('TEST_3SCALE_PROVIDER_KEY') && 
    getenv('TEST_3SCALE_APP_IDS')      &&
    getenv('TEST_3SCALE_APP_KEYS')  &&
    getenv('TEST_3SCALE_SERVICE_ID')) {
  error_reporting(E_ALL & ~E_DEPRECATED);
  require_once(dirname(__FILE__) . '/../lib/simpletest/unit_tester.php');
  require_once(dirname(__FILE__) . '/../lib/simpletest/autorun.php');
  error_reporting(E_ALL | E_NOTICE);

  date_default_timezone_set('Europe/Madrid');

  require_once(dirname(__FILE__) . '/../lib/ThreeScaleClient.php');

  class RemoteTest extends UnitTestCase {
    function setUp() {
      $this->providerKey = getenv('TEST_3SCALE_PROVIDER_KEY');

      $this->appIds = explode(',', getenv('TEST_3SCALE_APP_IDS'));
      $this->appIds = array_map('trim', $this->appIds);
      
      $this->appKeys = explode(',', getenv('TEST_3SCALE_APP_KEYS'));
      $this->appKeys = array_map('trim', $this->appKeys);

      $this->serviceId = explode(',', getenv('TEST_3SCALE_SERVICE_ID')); 
      $this->serviceId = array_map('trim', $this->serviceId);

      $this->client = new ThreeScaleClient($this->providerKey);
    }

    function testSuccessfulAuthorize() {
      foreach($this->appKeys as $appKey) {
        $response = $this->client->authorize($this->appIds[0], $appKey, $this->serviceId[0]);
        $this->assertTrue($response->isSuccess());
      }
    }

    function testFailedAuthorize() {
      $response = $this->client->authorize('boo', $this->serviceId[0]);
      $this->assertFalse($response->isSuccess());

      $this->assertEqual('application_not_found', $response->getErrorCode());
      $this->assertEqual('application with id="boo" was not found', 
                         $response->getErrorMessage());
    }

    function testSuccessfulReport() {
      $transactions = array();

      foreach ($this->appIds as $appId) {
        array_push($transactions, array('app_id' => $appId, 'usage' => array('hits' => 1)));
      }

      $response = $this->client->report($transactions, $this->serviceId[0]);
      $this->assertTrue($response->isSuccess());
    }

    function testFailedReport() {
      $transactions = array();
      foreach ($this->appIds as $appId) {
        array_push($transactions, array('app_id' => $appId, 'usage' => array('hits' => 1)));
      }

      $client = new ThreeScaleClient('boo');
      $response = $client->report($transactions, $this->serviceId[0]);
      
      $this->assertFalse($response->isSuccess());
      $this->assertEqual('provider_key_invalid', $response->getErrorCode());
      $this->assertEqual('provider key "boo" is invalid', 
                         $response->getErrorMessage());
    }
  }

} else {
  echo "This test executes real requests against 3scale backend server. It needs to know provider key, service id, application ids and application keys to use in the requests. You have to set these environment variables:\n";
  echo " * TEST_3SCALE_PROVIDER_KEY - provider key.\n";
  echo " * TEST_3SCALE_SERVICE_ID   - service id.\n";
  echo " * TEST_3SCALE_APP_IDS      - list of application ids, separated by commas.\n";
  echo " * TEST_3SCALE_APP_KEYS     - list of application keys corresponding to the FIRST id in the TEST_3SCALE_APP_IDS list. Also separated by commas.\n";
}
