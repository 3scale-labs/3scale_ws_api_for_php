<?php

/**
 * Defines ThreeScaleInterface class and hierarchy of exception classes
 * used by it.
 *
 * @copyright 2008 3scale networks S.L.
 */

require_once(dirname(__FILE__) . '/curl/curl.php');

/**
 * Interface for communication with 3scale monitoring system.
 *
 * Objects of this class are stateless and can be shared through multiple
 * transactions and by multiple clients.
 */
class ThreeScaleInterface {

  private $host;
  private $providerAuthenticationKey;
  private $http;

  /**
   * Create a 3scale interface instance.
   *
   * @param $host Hostname of 3scale backend server.
   * @param $providerAuthenticationKey Unique key that identifies this provider.
   * @param $http Object for handling HTTP requests. Leaving it NULL will use
   *              CURL by default, which is fine for most cases.
   */
  public function __construct($host = NULL, $providerAuthenticationKey = NULL, $http = NULL) {
    $this->host = $host;
    $this->providerAuthenticationKey = $providerAuthenticationKey;

    if (is_null($http)) {
      $http = new Curl;
      $http->options['CURLOPT_FOLLOWLOCATION'] = false;
    }

    $this->http = $http;
  }

  /**
   * Get hostname of 3scale backend server.
   * @return string
   */
  public function getHost() {
    return $this->host;
  }

  /**
   * Set hostname of 3scale backend server.
   * @param String $value
   */
  public function setHost($value) {
    $this->host = $value;
  }

  /**
   * Get provider's private key.
   * @return string
   */
  public function getProviderAuthenticationKey() {
    return $this->providerAuthenticationKey;
  }

  /**
   * Set provider's private key.
   * @param string $value
   */
  public function setProviderAuthenticationKey($value) {
    $this->providerAuthenticationKey = $value;
  }

  /**
   * Starts a transaction. This can be used also to report estimated resource
   * usage of the request.
   *
   * @param $userKey Key that uniquely identifies an user of the service.
   * @param $usage A array of metric names/values pairs that contains predicted
   *               resource usage of this request.
   *
   * For example, if this request is going to take 10MB of storage space, then
   * this parameter could contain <code>array("storage" => 10)</code>. The
   * values may be only approximate or they can be missing altogether. In these
   * cases, the actual values must be reported using {@link confirm}.
   * 
   * @return array containing these keys:
   * 
   *  - <var>id</var>: Transaction id. This is required for
   *    confirmation/cancellation of the transaction later.
   *  - <var>provider_verification_key</var>: This key should be sent back to
   *    the user so he/she can use it to verify the authenticity of the
   *    provider.
   *  - <var>contract_name</var>: This is name of the contract the user is
   *    singed for. This information can be used to serve different responses
   *    according to contract types, if that is desirable.
   *
   * @throws ThreeScaleUserKeyInvalid $userKey is not valid
   * @throws ThreeScaleProviderKeyInvalid $providerAuthenticationKey is not valid
   * @throws ThreeScaleMetricInvalid $usage contains invalid metrics
   * @throws ThreeScaleContractNotActive contract is not active
   * @throws ThreeScaleLimitsExceeded usage limits are exceeded
   * @throws ThreeScaleUnknownError some other unexpected error
   */
  public function start($userKey, $usage = array()) {
    $url = $this->getHost() . "/transactions.xml";
    $params = array(
        'user_key' => $userKey,
        'provider_key' => $this->getProviderAuthenticationKey(),
        'usage' => $usage);

    $response = $this->http->post($url, $params);

    if ($response->headers['Status-Code'] == 200) {
      return $this->parseTransactionData($response->body);
    } else {
      $this->handleError($response->body);
    }
  }

  /**
   * Confirms a transaction.
   *
   * @param $transactionId A transaction id obtained from previous call to
   *                       {@link start}.
   * @param $usage An array of metric names/values pairs containing actual
   *               resource usage of this request. This parameter is required
   *               only if no usage information was passed to method start for
   *               this transaction, or if it was only approximate.
   *
   * @return true
   *
   * @throws ThreeScaleTransactionNotFound transactions does not exits
   * @throws ThreeScaleProviderKeyInvalid $providerAuthenticationKey is not valid
   * @throws ThreeScaleMetricInvalid $usage contains invalid metrics
   * @throws ThreeScaleUnknownError some other unexpected error
   */
  public function confirm($transactionId, $usage = array()) {
    $url = $this->getHost() . "/transactions/" . urlencode($transactionId) . "/confirm.xml";
    $params = array(
      'provider_key' => $this->getproviderAuthenticationKey(),
      'usage' => $usage);

    $response = $this->http->post($url, $params);

    if ($response->headers['Status-Code'] == 200) {
      return true;
    } else {
      $this->handleError($response->body);
    }
  }

  /**
   * Cancels a transaction.
   *
   * Use this if request processing failed. Any estimated resource usage
   * reported by preceding call to {@link start} will be deleted. You don't
   * have to call this if call to {@link start} itself failed.
   *
   * @param $transactionId A transaction id obtained from previous call to
   *                       {@link start}.
   *
   * @return true
   *
   * @throws ThreeScaleTransactionNotFound transactions does not exits
   * @throws ThreeScaleProviderKeyInvalid $providerAuthenticationKey is not valid
   * @throws ThreeScaleUnknownError some other unexpected error
   */
  public function cancel($transactionId) {
    $url = $this->getHost() . "/transactions/" . urlencode($transactionId) . ".xml";
    $params = array('provider_key' => $this->getProviderAuthenticationKey());

    $response = $this->http->delete($url, $params);

    if ($response->headers['Status-Code'] == 200) {
      return true;
    } else {
      $this->handleError($response->body);
    }
  }

  /**
   * Authorize an user with given key.
   *
   * This returns true, if the user key exists, is valid, the contract is active and the
   * usage limits or credit are not exceeded. In other case, it throws an exception.
   *
   * @param $userKey Key that uniquely identifies an user of the service.
   *
   * @return true
   *
   * @throws ThreeScaleUserKeyInvalid $userKey is not valid
   * @throws ThreeScaleProviderKeyInvalid $providerAuthenticationKey is not valid
   * @throws ThreeScaleContractNotActive contract is not active
   * @throws ThreeScaleLimitsExceeded usage limits are exceeded
   * @throws ThreeScaleUnknownError some other unexpected error
   */
  public function authorize($userKey) {
    $url = $this->getHost() . "/transactions/authorize.xml";
    $params = array(
      'provider_key' => $this->getProviderAuthenticationKey(), 
      'user_key' => $userKey);

    $response = $this->http->get($url, $params);

    if ($response->headers['Status-Code'] == 200) {
      return true;
    } else {
      $this->handleError($response->body);
    }
  }

  /**
   * Report multiple transactions in single batch.
   *
   * @param $transaction an array containing one item per each transaction that is to be 
   * reported. Each item should be associative array with these items:
   *
   *  - <var>user_key</var>: (required) user key for the transaction.
   *  - <var>usage</var>: (required) associative array of usage data (see below).
   *  - <var>timestamp</var>: (optional) date and time when the transaction was registered. Can 
   *  be left out, in which case current time will be used. This has to be an integer 
   *  representing unix timestamp (number of seconds since 1.1.1970). The unix timestamp can be
   *  obtained for example with PHP's buliding function <code>time()</code>.
   *
   *  Warning: make sure you have correctly set your timezone. This can be done in PHP with
   *  the function <code>date_default_timezone_set()</code>.
   *
   *  The <var>usage</var> parameter should be an array in the form
   *  array('metric_name' => value, ...), where <var>metric_name</var> is a name of the metric
   *  and <var>value</var> is integer value you want to report for that metric.
   *
   *  @return true only if <strong>all</strong> transactions were processed successfuly. In
   *  other case, exception is thrown.
   *
   *  @throws ThreeScaleBatchException if there was error in processing at least one transaction.
   *  This exception contains member function getErrors(), which returns array of all errors
   *  that occured in the processing. The array contains one item per each error that occured.
   *  Each item is indexed with the same index as the transaction that caused it. Each item
   *  is array itself, with two elements: <var>code</var> and <var>message</var>. Code contains
   *  identifier of the error and message is human readable description.
   *   
   *  Example:
   *
   *  This will report three transactions in single batch. First transaction is for one user,
   *  the other two are for other user (note the user_keys). The timestamps are specified and
   *  generated with PHP's builtin function <code>mktime()</code>.
   *  The usage contains data for two metrics: hits and transfer.
   *
   *  $interface->batchReport(array(
   *    0 => array(
   *      'user_key' => '3scale-f762ce8f234b6605c760b47d0bd55a18',
   *      'usage' => array('hits' => 1, 'transfer' => 2048),
   *      'timestamp' => mktime(18, 33, 10, 2009, 8, 4)),
   *    1 => array(
   *      'user_key' => '3scale-ff762ce8f234b6605c760b47d0bd55a1',
   *      'usage' => array('hits' => 1, 'transfer' => 14021),
   *      'timestamp' => mktime(18, 38, 12, 2009, 8, 4)),
   *    2 => array(
   *      'user_key' => '3scale-ff762ce8f234b6605c760b47d0bd55a1',
   *      'usage' => array('hits' => 1, 'transfer' => 8167),
   *      'timestamp' => mktime(18, 52, 55, 2009, 8, 4))));
   *
   */
  public function batchReport($transactions) {
    $url = $this->getHost() . "/transactions.xml";
    $params = array(
      'provider_key' => $this->getProviderAuthenticationKey(),
      'transactions' => array());

    foreach($transactions as $index => $transaction) {
      if (isset($transaction['timestamp'])) {
        $transaction['timestamp'] = date('Y-m-d H:i:s P', $transaction['timestamp']);
      }

      $params['transactions'][(string) $index] = $transaction;
    }

    $response = $this->http->post($url, $params);

    if ($response->headers['Status-Code'] == 200) {
      return true;
    } else {
      throw new ThreeScaleBatchException($this->parseBatchError($response->body));
    }
  }

  private function parseBatchError($body) {
    $xml = new SimpleXMLElement($body);
    $result = array();

    foreach ($xml as $error) {
      $result[(string) $error['index']] = array(
        'code' => (string) $error['id'], 'message' => (string) $error);
    }

    return $result;
  }

  private function parseTransactionData($body) {
    $xml = new SimpleXMLElement($body);
    $result = array();

    foreach ($xml as $name => $value) {
      $result[$name] = (string) $value;
    }

    return $result;
  }

  private function handleError($body) {
    static $codes_to_exceptions = array(
      'user.exceeded_limits' => 'LimitsExceeded',
      'user.invalid_key' => 'UserKeyInvalid',
      'user.inactive_contract' => 'ContractNotActive',
      'provider.invalid_key' => 'ProviderKeyInvalid',
      'provider.invalid_metric' => 'MetricInvalid',
      'provider.invalid_transaction_id' => 'TransactionNotFound');

    try {
      $xml = new SimpleXMLElement($body);
    } catch (Exception $e) {
      throw new ThreeScaleUnknownException;
    }

    $exception_class = $codes_to_exceptions[(string) $xml['id']];

    if ($exception_class) {
      $exception_class = "ThreeScale{$exception_class}";
      throw new $exception_class((string) $xml);
    } else {
      throw new ThreeScaleUnknownException;
    }
  }
}


class ThreeScaleException extends RuntimeException {}



/**
 * Base class for exceptions caused by user of ther service.
 */
class ThreeScaleUserException extends ThreeScaleException {}

/**
 * Exception thrown when $user_key is not valid. This can mean that contract
 * between provider and user does not exists, or the passed $user_key does
 * not correspond to the key associated with this contract.
 */
 class ThreeScaleUserKeyInvalid extends ThreeScaleUserException {}

/**
 * Exception thrown when contract between user and provider is not active.
 * Contract can be inactive when it is pending (requires confirmation from
 * provider), suspended or canceled.
 */
class ThreeScaleContractNotActive extends ThreeScaleUserException {}

/**
 * Exception thrown when usage limits configured for contract are already
 * exceeded.
 */
class ThreeScaleLimitsExceeded extends ThreeScaleUserException {}



/**
 * Base class for exceptions caused by provider of the service.
 */
class ThreeScaleProviderException extends ThreeScaleException {}

/**
 * Exception thrown when provider authentication key is not valid. The provider
 * needs to make sure that the key used is the same as the one that was
 * generated for him/her when he/she published a service on 3scale.
 */
class ThreeScaleProviderKeyInvalid extends ThreeScaleProviderException {}

/**
 * Exception thrown when transaction corresponding to given $transaction_id
 * does not exists. Methods confirm and cancel need valid transaction id
 * that is obtained by preceding call to start.
 */
class ThreeScaleTransactionNotFound extends ThreeScaleProviderException {}

/**
 * Exception thrown when some metric name in provider $usage hash does not
 * correspond to metric configured for the service.
 */
class ThreeScaleMetricInvalid extends ThreeScaleProviderException {}



/**
 * Base class for exceptions caused by errors on 3scale backend system.
 */
class ThreeScaleSystemException extends ThreeScaleException {}

/**
 * Other error.
 */
class ThreeScaleUnknownException extends ThreeScaleSystemException {}

/**
 * Exception thrown batch batch reporting. Can contain multiple errors.
 */
class ThreeScaleBatchException extends ThreeScaleException {
  private $errors;

  public function __construct($errors) {
    $this->errors = $errors;
  }

  public function getErrors() {
    return $this->errors;
  }
}

?>
