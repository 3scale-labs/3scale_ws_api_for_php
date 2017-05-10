<?php

/**
 * Defines ThreeScaleClient class.
 *
 * @copyright 2010 3scale networks S.L.
 */

require_once(dirname(__FILE__) . '/curl/curl.php');

require_once(dirname(__FILE__) . '/ThreeScaleResponse.php');
require_once(dirname(__FILE__) . '/ThreeScaleAuthorizeResponse.php');
require_once(dirname(__FILE__) . '/version.php');


/**
 * Wrapper for 3scale web service management system API.
 *
 * Objects of this class are stateless and can be shared through multiple
 * transactions and by multiple clients.
 * DEFAULT_ROOT_ENDPOINT  su1.3scale.net communicates with 3scale SAAS platform
 */
class ThreeScaleClient {
  const DEFAULT_ROOT_ENDPOINT = 'http://su1.3scale.net';

  private $providerKey = null;
  private $httpClient;
  private $host;

  /**
   * Create a ThreeScaleClient instance.
   *
   * @param $providerKey If (!Provider Key) then service token workflow is triggered.
   * @param $host String scheme + hostname + port of 3scale backend server or On-premise 3scale SAAS platform
   * @param $httpClient Object Object for handling HTTP requests. Default is CURL. Don't change it
   *                    unless you know what you are doing.
   */
  public function __construct($providerKey = null, $host = self::DEFAULT_ROOT_ENDPOINT, $httpClient = null) {
    if ($providerKey) {
      $this->providerKey = $providerKey;
    } 

    $this->setHttpClient($httpClient);
    $this->host   = $host;
  }

  /**
   * Get provider's API key.
   * @return string
   */
  public function getProviderKey() {
    return $this->providerKey;
  }

  /**
   * Get hostname of backend server.
   * @return string
   */
  public function getHost() {
    return $this->host;
  }

  /**
   * Authorize an application for app id auth mode
   *
   * @param $appId  application id.
   * @param $appKey secret application key.
   * @param ThreeScaleClientCredentials $credentials_or_service_id accepts service id and service token as tuple
   * @param string $credentials_or_service_id accepts only service id
   *
   * @return ThreeScaleResponse object containing additional authorization information.
   * If both provider key and application id are valid, the returned object is actually
   * @see ThreeScaleAuthorizeResponse (which is derived from ThreeScaleResponse) and
   * contains additional information about the usage status.
   *
   * @see ThreeScaleResponse
   * @see ThreeScaleAuthorizeResponse
   *
   * @throws ThreeScaleServerError in case of unexpected internal server error
   *
   * Example:
   *
   * <code>
   *   <?php
   *   $response = $client->authorize('app-id', 'app-key');
   *   // or $response = $client->authorize('app-id', 'app-key', 'service_id');
   *
   *   if ($response->isSuccess()) {
   *     // ok.
   *   } else {
   *     // something is wrong.
   *   }
   *   ?>
   * </code>
   */
  
     public function authorize($appId, $appKey = null, $credentials_or_service_id, $usage = null) {
    $url =  $this->getHost() . "/transactions/authorize.xml";
    $params = array('app_id' => $appId);

    if ($credentials_or_service_id instanceof ThreeScaleClientCredentials ) {
      $params['service_token'] = $credentials_or_service_id->service_token;
      $params['service_id'] = $credentials_or_service_id->service_id;
    } else {
      $params['provider_key'] = $this->getProviderKey();
      $params['service_id'] = $credentials_or_service_id;
    }

    if ($appKey) {
      $params['app_key'] = $appKey;
    }
    
    if ($usage) {
      $params['usage'] = $usage;
    }
    
    $httpResponse = $this->httpClient->get($url, $params);
    if (self::isHttpSuccess($httpResponse)) {
      return $this->buildAuthorizeResponse($httpResponse->body);
    } else {
      return $this->processError($httpResponse);
    }
  }


  /**
   * Authorize an application for OAuth auth mode.
   *
   * @param $appId  application id or client id (they are equivalent)
   * @param $usage usage
   * @param ThreeScaleClientCredentials $credentials_or_service_id accepts service id and service token as tuple
   * @param string $credentials_or_service_id accepts only service id
   *
   * @return ThreeScaleResponse object containing additional authorization information.
   * If both provider key and application id are valid, the returned object is actually
   * @see ThreeScaleAuthorizeResponse (which is derived from ThreeScaleResponse) and
   * contains additional information about the usage status.
   *
   * @see ThreeScaleResponse
   * @see ThreeScaleAuthorizeResponse
   *
   * @throws ThreeScaleServerError in case of unexpected internal server error
   *
   * Example:
   *
   * <code>
   *   <?php
   *   $response = $client->oauth_authorize('app_id');
   *   // or $response = $client->oauth_authorize('app_id', 'service_id');
   *
   *   if ($response->isSuccess()) {
   *     // ok.
   *   } else {
   *     // something is wrong.
   *   }
   *   ?>
   * </code>
   */
  public function oauth_authorize($appId, $credentials_or_service_id, $usage = null) {
    $url = $this->getHost() . "/transactions/oauth_authorize.xml";
    $params = array('app_id' => $appId);

   if ($credentials_or_service_id instanceof ThreeScaleClientCredentials ) {
      $params['service_token'] = $credentials_or_service_id->service_token;
      $params['service_id'] = $credentials_or_service_id->service_id;
    } else {
      $params['provider_key'] = $this->getProviderKey();
      $params['service_id'] = $credentials_or_service_id;
    }

    if ($usage) {
      $params['usage'] = $usage;
    }
    
    $httpResponse = $this->httpClient->get($url, $params);

    if (self::isHttpSuccess($httpResponse)) {
      return $this->buildAuthorizeResponse($httpResponse->body);
    } else {
      return $this->processError($httpResponse);
    }
  }
  

  /**
   * Authorize an application for user key auth mode
   *
   * @param $userKey  user key.
   * @param ThreeScaleClientCredentials $credentials_or_service_id accepts service id and service token as tuple
   * @param string $credentials_or_service_id accepts only service id
   *
   * @return ThreeScaleResponse object containing additional authorization information.
   * If both provider key and application id are valid, the returned object is actually
   * @see ThreeScaleAuthorizeResponse (which is derived from ThreeScaleResponse) and
   * contains additional information about the usage status.
   *
   * @see ThreeScaleResponse
   * @see ThreeScaleAuthorizeResponse
   *
   * @throws ThreeScaleServerError in case of unexpected internal server error
   *
   * Example:
   *
   * <code>
   *   <?php
   *   $response = $client->authorize_with_user_key('user-key');
   *   // or $response = $client->authorize_with_user_key('user-key','service_id');
   *
   *   if ($response->isSuccess()) {
   *     // ok.
   *   } else {
   *     // something is wrong.
   *   }
   *   ?>
   * </code>
   */

  public function authorize_with_user_key($userKey, $credentials_or_service_id, $usage = null) {
    $url = $this->getHost() . "/transactions/authorize.xml";
    $params = array('user_key' => $userKey);
    
    if ($credentials_or_service_id instanceof ThreeScaleClientCredentials ) {
      $params['service_token'] = $credentials_or_service_id->service_token;
      $params['service_id'] = $credentials_or_service_id->service_id;
    } else {
      $params['provider_key'] = $this->getProviderKey();
      $params['service_id'] = $credentials_or_service_id;
    }

    if ($usage) {
      $params['usage'] = $usage;
    }

    $httpResponse = $this->httpClient->get($url, $params);

    if (self::isHttpSuccess($httpResponse)) {
      return $this->buildAuthorizeResponse($httpResponse->body);
    } else {
      return $this->processError($httpResponse);
    }
  }


/**
   * Authorize and report in a single shot for app id auth mode
   *
   * @param $appId  application id.
   * @param $appKey secret application key.
   * @param ThreeScaleClientCredentials $credentials_or_service_id accepts service id and service token as tuple
   * @param string $credentials_or_service_id accepts only service id
   *
   * @return ThreeScaleResponse object containing additional authorization information.
   * If both provider key and application id are valid, the returned object is actually
   * @see ThreeScaleAuthorizeResponse (which is derived from ThreeScaleResponse) and
   * contains additional information about the usage status.
   *
   * @see ThreeScaleResponse
   * @see ThreeScaleAuthorizeResponse
   *
   * @throws ThreeScaleServerError in case of unexpected internal server error
   *
   * Example:
   *
   * <code>
   *   <?php
   *   $response = $client->authorize('app-id', 'app-key');
   *   // or $response = $client->authorize('app-id', 'app-key','service_id');
   *
   *   if ($response->isSuccess()) {
   *     // ok.
   *   } else {
   *     // something is wrong.
   *   }
   *   ?>
   * </code>
   */

   public function authrep($appId, $appKey = null, $credentials_or_service_id, $usage = null, $userId = null, $object = null, $no_body = null) {  
    $url = $this->getHost() . "/transactions/authrep.xml";

    $params = array('app_id' => $appId);

    if ($credentials_or_service_id instanceof ThreeScaleClientCredentials ) {
      $params['service_token'] = $credentials_or_service_id->service_token;
      $params['service_id'] = $credentials_or_service_id->service_id;
    } else {
      $params['provider_key'] = $this->getProviderKey();
      $params['service_id'] = $credentials_or_service_id;
    }
   
    if ($appKey) $params['app_key'] = $appKey;
    if ($userId) $params['user_id'] = $userId;
    if ($object) $params['object'] = $object;
    if ($usage) $params['usage'] = $usage;
    if ($no_body) $params['no_body'] = $no_body;
     
    $httpResponse = $this->httpClient->get($url, $params);

    if (self::isHttpSuccess($httpResponse)) {
      return $this->buildAuthorizeResponse($httpResponse->body);
    } else {
      return $this->processError($httpResponse);
    }
    
  }

  /**
   * Authorize and report in a single shot for user key auth mode
   *
   * @param $userKey  User key.
   * @param ThreeScaleClientCredentials $credentials_or_service_id accepts service id and service token as tuple
   * @param string $credentials_or_service_id accepts only service id
   *
   * @return ThreeScaleResponse object containing additional authorization information.
   * If both provider key and application id are valid, the returned object is actually
   * @see ThreeScaleAuthorizeResponse (which is derived from ThreeScaleResponse) and
   * contains additional information about the usage status.
   *
   * @see ThreeScaleResponse
   * @see ThreeScaleAuthorizeResponse
   *
   * @throws ThreeScaleServerError in case of unexpected internal server error
   *
   * Example:
   *
   * <code>
   *   <?php
   *   $response = $client->authorize('user_key');
   *   // or $response = $client->authorize('user_key','service_id');
   *
   *   if ($response->isSuccess()) {
   *     // ok.
   *   } else {
   *     // something is wrong.
   *   }
   *   ?>
   * </code>
   */

  public function authrep_with_user_key($userKey, $credentials_or_service_id, $usage = null, $userId = null, $object = null, $no_body = null) {  
    $url = $this->getHost() . "/transactions/authrep.xml";

    $params = array('user_key' => $userKey);

    if ($credentials_or_service_id instanceof ThreeScaleClientCredentials ) {

      $params['service_token'] = $credentials_or_service_id->service_token;
      $params['service_id'] = $credentials_or_service_id->service_id;
    } else {

      $params['provider_key'] = $this->getProviderKey();
      $params['service_id'] = $credentials_or_service_id;
    }
     
    if ($userId) $params['user_id'] = $userId;
    if ($object) $params['object'] = $object;
    if ($usage) $params['usage'] = $usage;
    if ($no_body) $params['no_body'] = $no_body;
    
     
    $httpResponse = $this->httpClient->get($url, $params);

    if (self::isHttpSuccess($httpResponse)) {
      return $this->buildAuthorizeResponse($httpResponse->body);
    } else {
      return $this->processError($httpResponse);
    }
    
  }

  /**
   * Report transaction(s).
   *
   * @param $transactions array of transactions to report.
   *
   * Each transaction is an array with these elements:
   *
   * "app_id"    - ID of the application to report the transaction for.
   *               This parameter is required.
   *
   * "usage"     - Array of usage values. The keys are metric names and values
   *               are correspoding numeric values.
   *               Example: array('hits' => 1, 'transfer' => 1024).
   *               This parameter is required.
   *
   * "timestamp" - Timestamp of the transaction. This can be either an integer
   *               (the unix timestamp) or a string in the "YYYY-MM-DD HH:MM:SS"
   *               format (if the time is in the UTC), or a string in the
   *               "YYYY-MM-DD HH:MM:SS ZZZZZZ" format, where the ZZZZZZ is the time
   *               offset from the UTC. For example, "US Pacific Time" has offset -08:00,
   *               "Tokyo" has offset +09:00. This parameter is optional, and if not
   *               provided, equals to the current time.
   *
   * @return ThreeScaleResponse
   *
   * The response object's isSuccess() method returns true if the report was successful,
   * or false if there was an error. See @see ThreeScaleResponse class for more information.
   *
   * @throws ThreeScaleServerError in case of unexpected internal server error.
   *
   * Example:
   *
   * <code>
   *   <?php
   *   // Report two transactions of two applications with app_id
   *   $client->report(array(array('app_id' => 'foo', 'usage' => array('hits' => 1)),
   *                         array('app_id' => 'bar', 'usage' => array('hits' => 1)), 'service id');
   *
   *   // Report one transaction with timestamp with app_id
   *   $client->report(array(array('app_id'    => 'foo',
   *                               'timestamp' => mktime(15, 14, 00, 2, 27, 2010),
   *                               'usage'     => array('hits' => 1), 
   *                               'service id')));
   *
   *   // Report two transactions of two applications with user_key
   *   $client->report(array(array('user_key' => 'foo', 'usage' => array('hits' => 1)),
   *                         array('user_key' => 'bar', 'usage' => array('hits' => 1)), 'service id'));
   *
   *    // Report one transaction with timestamp and with user_key
   *   $client->report(array(array('user_key'    => 'foo',
   *                               'timestamp' => mktime(15, 14, 00, 2, 27, 2010),
   *                               'usage'     => array('hits' => 1),
   *                               'service id')));
   *
   *   ?>
   * </code>                            
   */
  public function report($transactions, $credentials_or_service_id) {
    if (empty($transactions)) {
      throw new InvalidArgumentException('no transactions to report');
    }
    
    $url = $this->getHost() . "/transactions.xml";

    $params = array();

    if ($credentials_or_service_id instanceof ThreeScaleClientCredentials ) {

      $params['service_token'] = $credentials_or_service_id->service_token;
      $params['service_id'] = $credentials_or_service_id->service_id;
    } else {
      $params['provider_key'] = $this->getProviderKey();
      $params['service_id'] = $credentials_or_service_id;
    }

    $params['transactions'] = $this->encodeTransactions($transactions);
    
    $httpResponse = $this->httpClient->post($url, $params);
    
    if (self::isHttpSuccess($httpResponse)) {
      return new ThreeScaleResponse(true);
    } else {
      return $this->processError($httpResponse);
    }
  }

  private function processError($httpResponse) {
    if (self::isHttpClientError($httpResponse)) {
      return $this->buildErrorResponse($httpResponse->body);
    } else {
      throw new ThreeScaleServerError($httpResponse);
    }
  }

  private function buildAuthorizeResponse($body) {
    $response = new ThreeScaleAuthorizeResponse;

    $doc = new SimpleXMLElement($body);

    if ((string) $doc->authorized == 'true') {
      $response->setSuccess();
    } else {
      $response->setError((string) $doc->reason);
    }

    $response->setPlan((string) $doc->plan);

    if ($doc->usage_reports) {
      foreach ($doc->usage_reports->usage_report as $node) {
        $response->addUsageReport()
          ->setMetric(trim($node['metric']))
          ->setPeriod(trim($node['period']))
          ->setPeriodInterval((string) $node->period_start, (string) $node->period_end)
          ->setCurrentValue((int) (string) $node->current_value)
          ->setMaxValue((int) (string) $node->max_value);
      }
    }

    return $response;
  }

  private function buildErrorResponse($body) {
    $response = new ThreeScaleResponse(false);
    $doc = new SimpleXMLElement($body);

    $response->setError(trim((string) $doc), $doc['code']);
    return $response;
  }

  private function encodeTransactions($transactions) {
    $encoded_transactions = array();

    foreach($transactions as $index => $transaction) {
      if (array_key_exists('timestamp', $transaction)) {
        $transaction['timestamp'] = self::encodeTimestamp($transaction['timestamp']);
      }

      $encoded_transactions[(string) $index] = self::urlencodeRecursive($transaction);
    }

    return $encoded_transactions;
  }

  private static function encodeTimestamp($timestamp) {
    if (is_numeric($timestamp)) {
      return date('Y-m-d H:i:s P', $timestamp);
    } else {
      return $timestamp;
    }
  }

  private static function urlencodeRecursive($array) {
    $result = array();

    foreach ($array as $key => $value) {
      if (is_array($value)) {
        $new_value = self::urlencodeRecursive($value);
      } else {
        if($key == 'timestamp') {
          $new_value = $value;
        } 
        else {
          $new_value = urlencode($value);
        }
      }

      $result[$key] = $new_value;
    }

    return $result;
  }

  private static function isHttpSuccess($httpResponse) {
    return (self::isHttpStatusCodeIn($httpResponse, 100, 299)) || ($httpResponse->headers['Status-Code'] == 409);
  }

  private static function isHttpClientError($httpResponse) {
    return self::isHttpStatusCodeIn($httpResponse, 400, 404);
  }

  private static function isHttpStatusCodeIn($httpResponse, $min, $max) {
    $code = $httpResponse->headers['Status-Code'];
    return $min <= $code && $code <= $max;
  }

  // Set the HTTP Client object used for the HTTP requests. By default, it uses the bundled
  // Curl library, which is just a thin wrapper around php's curl functions.
  public function setHttpClient($httpClient) {
    if (is_null($httpClient)) {
      $httpClient = new Curl;
      $threeScaleVersion = new ThreeScaleVersion();

      $version = $threeScaleVersion->getVersion();

      $httpClient->options['CURLOPT_FOLLOWLOCATION'] = false;
      $httpClient->headers['X-3scale-User-Agent'] = 'plugin-php-v'. $version;
    }

    $this->httpClient = $httpClient;
  }
}

// Base class for all exceptions.
class ThreeScaleException extends RuntimeException {}

// This exceptions is thrown when there is an unexpected internal server error
// on the 3scale server.
class ThreeScaleServerError extends ThreeScaleException {
  public function __construct($response = null) {
    parent::__construct('server error');
    $this->response = $response;
  }

  public function getResponse() {
    return $this->response;
  }
}

 /* Objects of this class are stateless and can be shared through multiple
 * transactions and by multiple clients.
 */
class ThreeScaleClientCredentials {
  public $service_id;
  public $service_token;

  public function __construct($service_id, $service_token) {
   
   if (!$service_token){
    throw new InvalidArgumentException('missing $service_token');
   }

   $this->service_token=$service_token;
   $this->service_id=$service_id;
  }
}

?>

