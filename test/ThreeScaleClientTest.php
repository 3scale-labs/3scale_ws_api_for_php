<?php

error_reporting(E_ALL & ~E_DEPRECATED);
require_once(dirname(__FILE__) .  '/../lib/simpletest/unit_tester.php');
require_once(dirname(__FILE__) .  '/../lib/simpletest/autorun.php');
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

  function testThrowsExceptionIfServiceTokenIsMissing() {
    $this->expectException(new InvalidArgumentException('missing $service_token'));
    
    new ThreeScaleClient("foo","bar", new ThreeScaleClientCredentials("12345", ""));
  }

  function testDefaultHost() {
    $client = new ThreeScaleClient('1234abcd');

    $this->assertEqual('http://su1.3scale.net', $client->getHost());
  }
  
  function testSuccessfulAuthorize() {
    $body = '<status>
               <authorized>true</authorized>
               <plan>Ultimate</plan>
             
               <usage_reports>
                 <usage_report metric="hits" period="day">
                   <period_start>2010-04-26 00:00:00 +0000</period_start>
                   <period_end>2010-04-27 00:00:00 +0000</period_end>
                   <current_value>10023</current_value>
                   <max_value>50000</max_value>
                 </usage_report>

                 <usage_report metric="hits" period="month">
                   <period_start>2010-04-01 00:00:00 +0000</period_start>
                   <period_end>2010-05-01 00:00:00 +0000</period_end>
                   <current_value>999872</current_value>
                   <max_value>150000</max_value>
                 </usage_report>
               </usage_reports>
             </status>';

    $this->httpClient->setReturnValue('get', new StubResponse(200, $body));
    $response = $this->client->authorize('foo', "bar", new ThreeScaleClientCredentials("1234","1234"));

    $this->assertTrue($response->isSuccess());
    $this->assertEqual('Ultimate', $response->getPlan());
    $this->assertEqual(2, count($response->getUsageReports()));

    $usageReports = $response->getUsageReports();

    $this->assertEqual('day',                           $usageReports[0]->getPeriod());
    $this->assertEqualTime('2010-04-26 00:00:00 +0000', $usageReports[0]->getPeriodStart());
    $this->assertEqualTime('2010-04-27 00:00:00 +0000', $usageReports[0]->getPeriodEnd());
    $this->assertEqual(10023,                           $usageReports[0]->getCurrentValue());
    $this->assertEqual(50000,                           $usageReports[0]->getMaxValue());
    
    $this->assertEqual('month',                         $usageReports[1]->getPeriod());
    $this->assertEqualTime('2010-04-01 00:00:00 +0000', $usageReports[1]->getPeriodStart());
    $this->assertEqualTime('2010-05-01 00:00:00 +0000', $usageReports[1]->getPeriodEnd());
    $this->assertEqual(999872,                          $usageReports[1]->getCurrentValue());
    $this->assertEqual(150000,                          $usageReports[1]->getMaxValue());
  }
  
  function testAuthorizeWithExceededUsageLimits() {
    $body = '<status>
               <authorized>false</authorized>
               <reason>usage limits are exceeded</reason>

               <plan>Ultimate</plan>
             
               <usage_reports>
                 <usage_report metric="hits" period="day" exceeded="true">
                   <period_start>2010-04-26 00:00:00 +0000</period_start>
                   <period_end>2010-04-27 00:00:00 +0000</period_end>
                   <current_value>50002</current_value>
                   <max_value>50000</max_value>
                 </usage_report>

                 <usage_report metric="hits" period="month">
                   <period_start>2010-04-01 00:00:00 +0000</period_start>
                   <period_end>2010-05-01 00:00:00 +0000</period_end>
                   <current_value>999872</current_value>
                   <max_value>150000</max_value>
                 </usage_report>
               </usage_reports>
             </status>';
    
    $this->httpClient->setReturnValue('get', new StubResponse(200, $body));
    $response = $this->client->authorize('foo', "bar", new ThreeScaleClientCredentials("1234","12345"));

    $this->assertFalse($response->isSuccess());
    $this->assertEqual('usage limits are exceeded', $response->getErrorMessage());

    $usageReports = $response->getUsageReports();

    $this->assertTrue($usageReports[0]->isExceeded());
  }

  function testAuthorizeWithInvalidAppId() {
    $body = '<error code="application_not_found">
               application with id="foo" was not found
             </error>';
    
    $this->httpClient->setReturnValue('get', new StubResponse(403, $body));
    $response = $this->client->authorize('foo', "bar", new ThreeScaleClientCredentials("1234","12345"));

    $this->assertFalse($response->isSuccess());
    $this->assertEqual('application_not_found', $response->getErrorCode());
    $this->assertEqual('application with id="foo" was not found',
                       $response->getErrorMessage());
  }
  
  function testAuthorizeWithServerError() {
    $this->httpClient->setReturnValue('get', new StubResponse(500, 'OMG! WTF!'));

    $this->expectException(new ThreeScaleServerError);
    $this->client->authorize('foo', "bar", new ThreeScaleClientCredentials("1234","12345"));
  }
  
  function testReportRaisesExceptionIfNoTransactionsGiven() {
    $this->expectException(new InvalidArgumentException('no transactions to report'));
    $this->client->report(array(), new ThreeScaleClientCredentials("12345","12345"));
  }

  function testSuccessfulReport() {
    $this->httpClient->setReturnValue('post', new StubResponse(200));
    $response = $this->client->report(array(
      array('app_id'    => 'foo',
            'timestamp' => mktime(10, 10, 0, 4, 27, 2010),
            'usage'     => array('hits' => 1))),
            new ThreeScaleClientCredentials("12345","12345"));

    $this->assertTrue($response->isSuccess());
  }
  
  function testReportEncodesTransactions() {
    $this->httpClient->expectOnce('post',
      array(ThreeScaleClient::DEFAULT_ROOT_ENDPOINT . '/transactions.xml',
        array(
          'service_token' => '12345',
          'service_id' => '12345',
          'transactions' => array(
            '0' => array(
              'app_id'    => 'foo',
              'usage'     => array('hits' => '1'),
              'timestamp' => '2010-04-27 15:42:17 +02:00'),
            '1' => array(
              'app_id'    => 'bar',
              'usage'     => array('hits' => '1'),
              'timestamp' => '2010-04-27 15:55:12 +02:00')))));
       
    $this->httpClient->setReturnValue('post', new StubResponse(200));

    $this->client->report(array(
      array('app_id'    => 'foo',
            'usage'     => array('hits' => 1),
            'timestamp' => mktime(15, 42, 17, 4, 27, 2010)),

      array('app_id'    => 'bar',
            'usage'     => array('hits' => 1),
            'timestamp' => mktime(15, 55, 12, 4, 27, 2010))),
      new ThreeScaleClientCredentials("12345","12345"));
  }
  
  function testFailedReport() {
    $errorBody = '<error code="provider_key_invalid">provider key "foo" is invalid</error>';
    
    $this->httpClient->setReturnValue('post', new StubResponse(403, $errorBody));
    #$response = $this->client->report(array(array('app_id' => 'abc', 
                 //                                 'usage'  => array('hits' => 1),
                   //                               new ThreeScaleClientCredentials("2555902","2555902"))));
    $response = $this->client->report(array(array('app_id' => 'abc', 'usage' => array('hits' => 1))), new ThreeScaleClientCredentials("12345","12345"));

    $this->assertFalse($response->isSuccess());
    $this->assertEqual('provider_key_invalid',          $response->getErrorCode());
    $this->assertEqual('provider key "foo" is invalid', $response->getErrorMessage());
  }
  
  function testReportWithServerError() {
    $this->httpClient->setReturnValue('post', new StubResponse(500, 'OMG! WTF!'));

    $this->expectException(new ThreeScaleServerError);
    $this->client->report(array(array('app_id' => 'foo', 'usage' => array('hits' => 1))), new ThreeScaleClientCredentials("2555902","2555902"));
  }

  private function assertEqualTime($expected, $actual) {
    $time = new DateTime($expected);
    $this->assertEqual($time->getTimestamp(), $actual);
  }
}

?>
