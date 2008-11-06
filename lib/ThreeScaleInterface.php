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

  const KEY_PREFIX = '3scale-';

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
    $this->http = is_null($http) ? new Curl : $http;
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
        'user_key' => self::prepareKey($userKey),
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
   * Check if the key is 3scale system key.
   *
   * This can be used to quickly differentiate between 3scale keys and any
   * other authentication keys the provider might use.
   *
   * @param string $key
   * @return boolean
   */
  public static function isSystemKey($key) {
    return strpos($key, self::KEY_PREFIX) === 0;
  }


  private static function prepareKey($key) {
    return self::isSystemKey($key) ? substr($key, strlen(self::KEY_PREFIX)) : $key;
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

?>