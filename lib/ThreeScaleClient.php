<?php

/**
 * Defines ThreeScaleClient class.
 *
 * @copyright 2010 3scale networks S.L.
 */

require_once(dirname(__FILE__) . '/curl/curl.php');

require_once(dirname(__FILE__) . '/ThreeScaleResponse.php');
require_once(dirname(__FILE__) . '/ThreeScaleAuthorizeResponse.php');


/**
 * Wrapper for 3scale web service management system API.
 *
 * Objects of this class are stateless and can be shared through multiple
 * transactions and by multiple clients.
 */
class ThreeScaleClient {
  const DEFAULT_HOST = 'server.3scale.net';

  private $providerKey;
  private $host;
  private $httpClient;

  /**
   * Create a ThreeScaleClient instance.
   *
   * @param $providerKey Unique API key that identifies the provider.
   * @param $host Hostname of 3scale backend server. Usually there is no reason to use anything
   *              else than the default value.
   * @param $httpClient Object for handling HTTP requests. Default is CURL. Don't change it
   *                    unless you know what you are doing.
   */
  public function __construct($providerKey, $host = self::DEFAULT_HOST, $httpClient = null) {
    if (!$providerKey) {
      throw new InvalidArgumentException('missing $providerKey');
    }

    $this->providerKey = $providerKey;
    $this->host = $host;

    $this->setHttpClient($httpClient);
  }

  /**
   * Get provider's API key.
   * @return string
   */
  public function getProviderKey() {
    return $this->providerKey;
  }

  /**
   * Get hostname of 3scale backend server.
   * @return string
   */
  public function getHost() {
    return $this->host;
  }

  /**
   * Authorize an user with given key.
   *
   * @param $userKey user's API key.
   *
   * @return ThreeScaleResponse object containing additional authorization information.
   *
   * If the authorization is successful, the isSuccess() method of the returned object
   * returns true. In this case, the returned object is actually 
   * ThreeScaleAuthorizeResponse (which is subclass of ThreeScaleResponse) and contains
   * additional information about status of the user. See @see ThreeScaleAuthorizeResponse
   * for more details.
   * 
   * In case of error, the isSuccess() method returns false. See @see ThreeScaleResponse for
   * more information about how to get more details about the errors.
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
   *   $response = $client->authorize('foo');
   *
   *   if ($response->isSuccess()) {
   *     // ok.
   *   } else {
   *     // something is wrong.
   *   }
   *   ?>
   * </code>
   */
  public function authorize($userKey) {  
    $url = "http://" . $this->getHost() . "/transactions/authorize.xml";
    $params = array('provider_key' => $this->getProviderKey(), 'user_key' => $userKey);

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
   * "user_key"  - API key of the user to report the transaction for.
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
   *   // Report two transactions of two users.
   *   $client->report(array(array('user_key' => 'foo', 'usage' => array('hits' => 1)),
   *                         array('user_key' => 'bar', 'usage' => array('hits' => 1))));
   *
   *   // Report one transaction with timestamp.
   *   $client->report(array(array('user_key'  => 'foo',
   *                               'timestamp' => mktime(15, 14, 00, 2, 27, 2010),
   *                               'usage'     => array('hits' => 1))));
   *   ?>
   * </code>                            
   */
  public function report($transactions) {
    if (empty($transactions)) {
      throw new InvalidArgumentException('no transactions to report');
    }
    
    $url = "http://" . $this->getHost() . "/transactions.xml";

    $params = array();
    $params['provider_key'] = urlencode($this->getProviderKey());
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

    $response->setPlan((string) $doc->plan);

    foreach ($doc->usage as $node) {
      $response->addUsage()
        ->setMetric(trim($node['metric']))
        ->setPeriod(trim($node['period']))
        ->setPeriodInterval((string) $node->period_start, (string) $node->period_end)
        ->setCurrentValue((int) (string) $node->current_value)
        ->setMaxValue((int) (string) $node->max_value);
    }

    return $response;
  }

  private function buildErrorResponse($body) {
    $response = new ThreeScaleResponse(false);
    $doc = new SimpleXMLElement($body);

    foreach ($doc->xpath('//error') as $node) {
      $errorCode = $node['code'] ? $node['code'] : $node['id'];
      $response->addError($node['index'], $errorCode, trim((string) $node));
    }

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
        $new_value = urlencode($value);
      }

      $result[urlencode($key)] = $new_value;
    }

    return $result;
  }

  private static function isHttpSuccess($httpResponse) {
    return self::isHttpStatusCodeIn($httpResponse, 100, 299);
  }

  private static function isHttpClientError($httpResponse) {
    return self::isHttpStatusCodeIn($httpResponse, 400, 499);
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
      $httpClient->options['CURLOPT_FOLLOWLOCATION'] = false;
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

?>
