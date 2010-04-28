<?php
require_once(dirname(__FILE__) . '/curl/curl.php');

require_once(dirname(__FILE__) . '/ThreeScaleResponse.php');
require_once(dirname(__FILE__) . '/ThreeScaleAuthorizeResponse.php');

class ThreeScaleClient {
  const DEFAULT_HOST = 'server.3scale.net';

  private $providerKey;
  private $host;
  private $httpClient;

  public function __construct($providerKey, $host = self::DEFAULT_HOST, $httpClient = null) {
    if (!$providerKey) {
      throw new InvalidArgumentException('missing $providerKey');
    }

    $this->providerKey = $providerKey;
    $this->host = $host;

    $this->setHttpClient($httpClient);
  }

  public function getProviderKey() {
    return $this->providerKey;
  }

  public function getHost() {
    return $this->host;
  }


// TODO: phpdoc-ize this comment.  
  //     # Authorize a user.
//     #
//     # == Parameters
//     # 
//     # Hash with options:
//     #
//     #   user_key:: API key of the user to authorize. This is required.
//     #
//     # == Return
//     #
//     # An ThreeScale::AuthorizeResponse object. It's +success?+ method returns true if
//     # the authorization is successful. In that case, it contains additional information
//     # about the status of the use. See the ThreeScale::AuthorizeResponse for more information.
//     # In case of error, the +success?+ method returns false and the +errors+ contains list
//     # of errors with more details.
//     #
//     # In case of unexpected internal server error, this method raises a ThreeScale::ServerError
//     # exception.
//     #
//     # == Examples
//     #
//     #   response = client.authorize(:user_key => 'foo')
//     #
//     #   if response.success?
//     #     # All good. Proceed...
//     #   end
//     #
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

// TODO: phpdoc-ize alto this comment
//     # Report transaction(s).
//     #
//     # == Parameters
//     #
//     # The parameters the transactions to report. Each transaction is a hash with
//     # these elements:
//     #
//     #   user_key::  API key of the user to report the transaction for. This parameter is
//     #               required.
//     #   usage::     Hash of usage values. The keys are metric names and values are
//     #               correspoding numeric values. Example: {'hits' => 1, 'transfer' => 1024}. 
//     #               This parameter is required.
//     #   timestamp:: Timestamp of the transaction. This can be either a object of the
//     #               ruby's Time class, or a string in the "YYYY-MM-DD HH:MM:SS" format
//     #               (if the time is in the UTC), or a string in 
//     #               the "YYYY-MM-DD HH:MM:SS ZZZZZ" format, where the ZZZZZ is the time offset
//     #               from the UTC. For example, "US Pacific Time" has offset -0800, "Tokyo"
//     #               has offset +0900. This parameter is optional, and if not provided, equals
//     #               to the current time.
//     #
//     # == Return
//     #
//     # A Response object with method +success?+ that returns true if the report was successful,
//     # or false if there was an error. See ThreeScale::Response class for more information.
//     #
//     # In case of unexpected internal server error, this method raises a ThreeScale::ServerError
//     # exception.
//     #
//     # == Examples
//     #
//     #   # Report two transactions of two users.
//     #   client.report({:user_key => 'foo', :usage => {'hits' => 1}},
//     #                 {:user_key => 'bar', :usage => {'hits' => 1}})
//     #
//     #   # Report one transaction with timestamp.
//     #   client.report({:user_key  => 'foo',
//     #                  :timestamp => Time.local(2010, 4, 27, 15, 14),
//     #                  :usage     => {'hits' => 1})
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


// /**
//  * Defines ThreeScaleInterface class and hierarchy of exception classes
//  * used by it.
//  *
//  * @copyright 2008 3scale networks S.L.
//  */
// 
// 
// /**
//  * Interface for communication with 3scale monitoring system.
//  *
//  * Objects of this class are stateless and can be shared through multiple
//  * transactions and by multiple clients.
//  */
// class ThreeScaleInterface {
// 
//   private $host;
//   private $providerAuthenticationKey;
//   private $http;
// 
//   /**
//    * Create a 3scale interface instance.
//    *
//    * @param $host Hostname of 3scale backend server.
//    * @param $providerAuthenticationKey Unique key that identifies this provider.
//    * @param $http Object for handling HTTP requests. Leaving it NULL will use
//    *              CURL by default, which is fine for most cases.
//    */
//   public function __construct($host = NULL, $providerAuthenticationKey = NULL, $http = NULL) {
//   }
// 
//   /**
//    * Get hostname of 3scale backend server.
//    * @return string
//    */
//   public function getHost() {
//   }
// 
//   /**
//    * Set hostname of 3scale backend server.
//    * @param String $value
//    */
//   public function setHost($value) {
//   }
// 
//   /**
//    * Get provider's private key.
//    * @return string
//    */
//   public function getProviderAuthenticationKey() {
//   }
// 
//   /**
//    * Set provider's private key.
//    * @param string $value
//    */
//   public function setProviderAuthenticationKey($value) {
//   }
// 
//   /**
//    * Authorize an user with given key.
//    *
//    * This returns true, if the user key exists, is valid, the contract is active and the
//    * usage limits or credit are not exceeded. In other case, it throws an exception.
//    *
//    * @param $userKey Key that uniquely identifies an user of the service.
//    *
//    * @return true
//    *
//    * @throws ThreeScaleUserKeyInvalid $userKey is not valid
//    * @throws ThreeScaleProviderKeyInvalid $providerAuthenticationKey is not valid
//    * @throws ThreeScaleContractNotActive contract is not active
//    * @throws ThreeScaleLimitsExceeded usage limits are exceeded
//    * @throws ThreeScaleUnknownError some other unexpected error
//    */
//   public function authorize($userKey) {
//   }
// 
//   /**
//    * Report multiple transactions in single batch.
//    *
//    * @param $transaction an array containing one item per each transaction that is to be 
//    * reported. Each item should be associative array with these items:
//    *
//    *  - <var>user_key</var>: (required) user key for the transaction.
//    *  - <var>usage</var>: (required) associative array of usage data (see below).
//    *  - <var>timestamp</var>: (optional) date and time when the transaction was registered. Can 
//    *  be left out, in which case current time will be used. This has to be an integer 
//    *  representing unix timestamp (number of seconds since 1.1.1970). The unix timestamp can be
//    *  obtained for example with PHP's buliding function <code>time()</code>.
//    *
//    *  Warning: make sure you have correctly set your timezone. This can be done in PHP with
//    *  the function <code>date_default_timezone_set()</code>.
//    *
//    *  The <var>usage</var> parameter should be an array in the form
//    *  array('metric_name' => value, ...), where <var>metric_name</var> is a name of the metric
//    *  and <var>value</var> is integer value you want to report for that metric.
//    *
//    *  @return true only if <strong>all</strong> transactions were processed successfuly. In
//    *  other case, exception is thrown.
//    *
//    *  @throws ThreeScaleBatchException if there was error in processing at least one transaction.
//    *  This exception contains member function getErrors(), which returns array of all errors
//    *  that occured in the processing. The array contains one item per each error that occured.
//    *  Each item is indexed with the same index as the transaction that caused it. Each item
//    *  is array itself, with two elements: <var>code</var> and <var>message</var>. Code contains
//    *  identifier of the error and message is human readable description.
//    *   
//    *  Example:
//    *
//    *  This will report three transactions in single batch. First transaction is for one user,
//    *  the other two are for other user (note the user_keys). The timestamps are specified and
//    *  generated with PHP's builtin function <code>mktime()</code>.
//    *  The usage contains data for two metrics: hits and transfer.
//    *
//    *  $interface->batchReport(array(
//    *    0 => array(
//    *      'user_key' => '3scale-f762ce8f234b6605c760b47d0bd55a18',
//    *      'usage' => array('hits' => 1, 'transfer' => 2048),
//    *      'timestamp' => mktime(18, 33, 10, 2009, 8, 4)),
//    *    1 => array(
//    *      'user_key' => '3scale-ff762ce8f234b6605c760b47d0bd55a1',
//    *      'usage' => array('hits' => 1, 'transfer' => 14021),
//    *      'timestamp' => mktime(18, 38, 12, 2009, 8, 4)),
//    *    2 => array(
//    *      'user_key' => '3scale-ff762ce8f234b6605c760b47d0bd55a1',
//    *      'usage' => array('hits' => 1, 'transfer' => 8167),
//    *      'timestamp' => mktime(18, 52, 55, 2009, 8, 4))));
//    *
//    */
//   public function batchReport($transactions) {
//   }

?>
