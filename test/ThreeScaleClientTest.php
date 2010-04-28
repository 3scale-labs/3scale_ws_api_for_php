<?php

// Simpletest uses some deprecated stuff. I don't want my test output to be cluttered by it.
error_reporting(E_ALL & ~E_DEPRECATED);
require_once('simpletest/unit_tester.php');
require_once('simpletest/autorun.php');

// Now enabled full error reporting.
error_reporting(E_ALL | E_NOTICE);

date_default_timezone_set('Europe/Madrid');

require_once(dirname(__FILE__) . '/../lib/ThreeScaleClient.php');

Mock::generate('Curl');
 
class StubResponse {
  public $body = '';
  public $headers = array();

  public function __construct($code, $body = null) {
    $this->headers['Status-Code'] = $code;
    $this->body = $body;
  }
}

class ThreeScaleClientTest extends UnitTestCase {
  function setUp() {
    $this->httpClient = new MockCurl($this);

    $this->client = new ThreeScaleClient('1234abcd');
    $this->client->setHttpClient($this->httpClient);
  }

  function testThrowsExceptionIfProviderKeyIsMissing() {
    $this->expectException(new InvalidArgumentException('missing $providerKey'));
    
    new ThreeScaleClient("");
  }

  function testDefaultHost() {
    $client = new ThreeScaleClient('1234abcd');

    $this->assertEqual('server.3scale.net', $client->getHost());
  }
  
  function testSuccessfulAuthorize() {
    $body = '<status>
               <plan>Ultimate</plan>
              
               <usage metric="hits" period="day">
                 <period_start>2010-04-26 00:00:00</period_start>
                 <period_end>2010-04-26 23:59:59</period_end>
                 <current_value>10023</current_value>
                 <max_value>50000</max_value>
               </usage>

               <usage metric="hits" period="month">
                 <period_start>2010-04-01 00:00:00</period_start>
                 <period_end>2010-04-30 23:59:59</period_end>
                 <current_value>999872</current_value>
                 <max_value>150000</max_value>
               </usage>
               </status>';

    $this->httpClient->setReturnValue('get', new StubResponse(200, $body));
    $response = $this->client->authorize('foo');

    $this->assertTrue($response->isSuccess());
    $this->assertEqual('Ultimate', $response->getPlan());
    $this->assertEqual(2, count($response->getUsages()));

    $usages = $response->getUsages();

    $this->assertEqual('day',                           $usages[0]->getPeriod());
    $this->assertEqual(mktime(0, 0, 0, 4, 26, 2010),    $usages[0]->getPeriodStart());
    $this->assertEqual(mktime(23, 59, 59, 4, 26, 2010), $usages[0]->getPeriodEnd());
    $this->assertEqual(10023,                           $usages[0]->getCurrentValue());
    $this->assertEqual(50000,                           $usages[0]->getMaxValue());
    
    $this->assertEqual('month',                         $usages[1]->getPeriod());
    $this->assertEqual(mktime(0, 0, 0, 4, 1, 2010),     $usages[1]->getPeriodStart());
    $this->assertEqual(mktime(23, 59, 59, 4, 30, 2010), $usages[1]->getPeriodEnd());
    $this->assertEqual(999872,                          $usages[1]->getCurrentValue());
    $this->assertEqual(150000,                          $usages[1]->getMaxValue());
  }
  
  function testFailedAuthorize() {
    $errorBody ='<error code="user.exceeded_limits">
                   usage limits are exceeded
                 </error>';

    $this->httpClient->setReturnValue('get', new StubResponse(403, $errorBody));
    $response = $this->client->authorize('foo');

    $this->assertFalse($response->isSuccess());
    $this->assertEqual(1, count($response->getErrors()));

    $errors = $response->getErrors();

    $this->assertEqual('user.exceeded_limits', $errors[0]->getCode());
    $this->assertEqual('usage limits are exceeded', $errors[0]->getMessage());
  }
  
  function testAuthorizeWithServerError() {
    $this->httpClient->setReturnValue('get', new StubResponse(500, 'OMG! WTF!'));

    $this->expectException(new ThreeScaleServerError);
    $this->client->authorize('foo');
  }
  
  function testReportRaisesExceptionIfNoTransactionsGiven() {
    $this->expectException(new InvalidArgumentException('no transactions to report'));
    $this->client->report(array());
  }

  function testSuccessfulReport() {
    $this->httpClient->setReturnValue('post', new StubResponse(200));
    $response = $this->client->report(array(
      array('user_key'  => 'foo',
            'timestamp' => mktime(10, 10, 0, 4, 27, 2010),
            'usage'     => array('hits' => 1))));

    $this->assertTrue($response->isSuccess());
  }
  
  function testReportEncodesTransactions() {
    $this->httpClient->expectOnce('post',
      array('http://server.3scale.net/transactions.xml',
        array(
          'provider_key' => '1234abcd',
          'transactions' => array(
            '0' => array(
              'user_key'  => 'foo',
              'usage'     => array('hits' => '1'),
              'timestamp' => urlencode('2010-04-27 15:42:17 +02:00')),
            '1' => array(
              'user_key'  => 'bar',
              'usage'     => array('hits' => '1'),
              'timestamp' => urlencode('2010-04-27 15:55:12 +02:00'))))));
       
    $this->httpClient->setReturnValue('post', new StubResponse(200));

    $this->client->report(array(
      array('user_key'  => 'foo',
            'usage'     => array('hits' => 1),
            'timestamp' => mktime(15, 42, 17, 4, 27, 2010)),

      array('user_key'  => 'bar',
            'usage'     => array('hits' => 1),
            'timestamp' => mktime(15, 55, 12, 4, 27, 2010))));
  }
  
  function testFailedReport() {
    $errorBody = '<errors>
                    <error code="user.invalid_key" index="0">
                      user key is invalid
                    </error>
                    <error code="provider.invalid_metric" index="1">
                      metric does not exist
                    </error>
                  </errors>';

    $this->httpClient->setReturnValue('post', new StubResponse(403, $errorBody));
    $response = $this->client->report(array(
      array('user_key' => 'bogus', 'usage' => array('hits' => 1)),
      array('user_key' => 'bar',   'usage' => array('monkeys' => 1000000000))));

    $this->assertFalse($response->isSuccess());

    $errors = $response->getErrors();
    $this->assertEqual(2, count($errors));

    $this->assertEqual(0,                     $errors[0]->getIndex());
    $this->assertEqual('user.invalid_key',    $errors[0]->getCode());
    $this->assertEqual('user key is invalid', $errors[0]->getMessage());

    $this->assertEqual(1, $errors[1]->getIndex());
    $this->assertEqual('provider.invalid_metric', $errors[1]->getCode());
    $this->assertEqual('metric does not exist', $errors[1]->getMessage());
  }
  
  function testReportWithServerError() {
    $this->httpClient->setReturnValue('post', new StubResponse(500, 'OMG! WTF!'));

    $this->expectException(new ThreeScaleServerError);
    $this->client->report(array(array('user_key' => 'foo', 'usage' => array('hits' => 1))));
  }
}

?>
