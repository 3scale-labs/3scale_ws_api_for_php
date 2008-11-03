<?php

/**
 * Interface for communication with 3scale backend server.
 *
 */
class ThreeScaleInterface {

  private $host;
  private $providerPrivateKey;

  const KEY_PREFIX = '3scale-';

  /**
   * Create a 3scale interface instance.
   *
   * @param $host Hostname of 3scale backend server.
   * @param $providerPrivateKey Unique key that identifies this provider.
   */
  public function __construct($host = NULL, $providerPrivateKey = NULL) {
    $this->host = $host;
    $this->providerPrivateKey = $providerPrivateKey;
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

  }

  /**
   * Confirm transaction.
   */
  public function confirm($transactionId, $usage = array()) {

  }

  /**
   * Cancel transaction.
   */
  public function cancel($transactionId) {

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
  public function isSystemKey($key) {
    return strpos($key, self::KEY_PREFIX) === 0;
  }
}

?>