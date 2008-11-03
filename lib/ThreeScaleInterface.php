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
    $url = "{$this->host}/transactions.xml";
    $params = array(
        'user_key' => $userKey,
        'provider_key' => $this->getProviderPrivateKey(),
        'usage' => $usage);

    $response = $this->http->post($url, $params);

    if ($response->headers['Status-Code'] == 200) {

    } else {
      $this->handleError($response->body);
    }


    //      params = {
    //        'user_key' => prepare_key(user_key),
    //        'provider_key' => provider_private_key
    //      }
    //      params.merge!(encode_params(usage, 'usage'))
    //      response = Net::HTTP.post_form(uri, params)
    //
    //      if response.is_a?(Net::HTTPSuccess)
    //        element = Hpricot::XML(response.body).at('transaction')
    //        [:id, :provider_verification_key, :contract_name].inject({}) do |memo, key|
    //          memo[key] = element.at(key).inner_text if element.at(key)
    //          memo
    //        end
    //      else
    //        handle_error(response.body)
    //      end
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

  private function handleError($body) {
    static $codes_to_exceptions = array(
    'user.exceeded_limits' => 'LimitsExceeded',
    'user.invalid_key' => 'UserKeyInvalid',
    'user.inactive_contract' => 'ContractNotActive',
    'provider.invalid_key' => 'ProviderKeyInvalid',
    'provider.invalid_metric' => 'MetricInvalid',
    'provider.invalid_transaction_id' => 'TransactionNotFound');
    
    $xml = new SimpleXMLElement($body);
    $exception_class = $codes_to_exceptions[(string) $xml['id']];

    if ($exception_class) {
      $exception_class = "ThreeScale{$exception_class}";
      throw new $exception_class;
    } else {
      throw new ThreeScaleUnknownError;
    }
  }
}

class ThreeScaleException extends RuntimeException {}
class ThreeScaleUserException extends ThreeScaleException {}
class ThreeScaleUserKeyInvalid extends ThreeScaleUserException {}

class ThreeScaleProviderException extends ThreeScaleException {}
class ThreeScaleProviderKeyInvalid extends ThreeScaleProviderException {}

?>