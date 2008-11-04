<?php

require_once(dirname(__FILE__) . '/curl/curl.php');

/**
 * Interface for communication with 3scale backend server.
 *
 */
class ThreeScaleInterface {

  private $host;
  private $providerPrivateKey;
  private $http;

  const KEY_PREFIX = '3scale-';

  /**
   * Create a 3scale interface instance.
   *
   * @param $host Hostname of 3scale backend server.
   * @param $providerPrivateKey Unique key that identifies this provider.
   * @param $http Object for handling HTTP requests. Leaving it NULL will use
   *              CURL by default, which should be fine for most cases.
   */
  public function __construct($host = NULL, $providerPrivateKey = NULL, $http = NULL) {
    $this->host = $host;
    $this->providerPrivateKey = $providerPrivateKey;
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
  public function getProviderPrivateKey() {
    return $this->providerPrivateKey;
  }

  /**
   * Set provider's private key.
   * @param string $value
   */
  public function setProviderPrivateKey($value) {
    $this->providerPrivateKey = $value;
  }

  /**
   * Start a transaction.
   *
   * @param string $userKey
   * @param array $usage
   */
  public function start($userKey, $usage = array()) {
    $url = $this->getHost() . "/transactions.xml";
    $params = array(
        'user_key' => self::prepareKey($userKey),
        'provider_key' => $this->getProviderPrivateKey(),
        'usage' => $usage);

    $response = $this->http->post($url, $params);

    if ($response->headers['Status-Code'] == 200) {
      return $this->parseTransactionData($response->body);
    } else {
      $this->handleError($response->body);
    }
  }

  /**
   * Confirm transaction.
   */
  public function confirm($transactionId, $usage = array()) {
    $url = $this->getHost() . "/transactions/" . urlencode($transactionId) . "/confirm.xml";
    $params = array(
      'provider_key' => $this->getProviderPrivateKey(),
      'usage' => $usage);

    $response = $this->http->post($url, $params);

    if ($response->headers['Status-Code'] == 200) {
      return true;
    } else {
      $this->handleError($response->body);
    }
  }

  /**
   * Cancel transaction.
   */
  public function cancel($transactionId) {
    $url = $this->getHost() . "/transactions/" . urlencode($transactionId) . ".xml";
    $params = array('provider_key' => $this->getProviderPrivateKey());

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

# Exceptions caused by user of ther service.
class ThreeScaleException extends RuntimeException {}
class ThreeScaleUserException extends ThreeScaleException {}
class ThreeScaleUserKeyInvalid extends ThreeScaleUserException {}
class ThreeScaleContractNotActive extends ThreeScaleUserException {}
class ThreeScaleLimitsExceeded extends ThreeScaleUserException {}

# Exceptions caused by provider of the service.
class ThreeScaleProviderException extends ThreeScaleException {}
class ThreeScaleProviderKeyInvalid extends ThreeScaleProviderException {}
class ThreeScaleTransactionNotFound extends ThreeScaleProviderException {}
class ThreeScaleMetricInvalid extends ThreeScaleProviderException {}

# Exception caused by errors on 3scale backend system.
class ThreeScaleSystemException extends ThreeScaleException {}
class ThreeScaleUnknownException extends ThreeScaleSystemException {}

?>