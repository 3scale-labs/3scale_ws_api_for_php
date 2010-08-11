<?php

/**
 * Defines ThreeScaleResponse and ThreeScaleResponseError classes.
 *
 * @copyright 2010 3scale networks S.L.
 */

/**
 * Object that wraps responses from 3scale server.
 */
class ThreeScaleResponse {
  private $errorCode = null;
  private $errorMessage = null;

  /**
   * Was the resposne successful?
   *
   * @return true if yes, false if not
   *
   * If the response is not successful, the getErrorCode() and getErrorMessage() methods 
   * return more more detailed information about the error.
   */
  public function isSuccess() {
    return is_null($this->errorCode) && is_null($this->errorMessage);
  }

  /**
   * System error code.
   *
   * @return string system error code
   */
  public function getErrorCode() {
    return $this->errorCode;
  }
  
  /**
   * Human readable error message.
   *
   * @return string error message
   */
  public function getErrorMessage() {
    return $this->errorMessage;
  }

  /**
   * @internal Switch response to success state.
   */
  public function setSuccess() {
    $this->errorCode = null;
    $this->errorMessage = null;
  }

  /**
   * @internal Switch response to error state and set error code and message.
   */
  public function setError($message, $code = null) {
    $this->errorCode = $code;
    $this->errorMessage = $message;
  }
}

?>
