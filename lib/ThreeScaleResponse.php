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
  private $success;
  private $errors;

  public function __construct($success) {
    $this->success = $success;
    $this->errors = array();
  }

  /**
   * Was the resposne successful?
   *
   * @return true if yes, false if not
   *
   * If the response is not successful, the getErrors() method returns list of errors with
   * more detailed information.
   */
  public function isSuccess() {
    return $this->success;
  }

  /**
   * List of errors, in case the response was not successful.
   *
   * @return array of ThreeScaleResponseError objects.
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * @internal Add an error to the response. 
   */
  public function addError($index, $code, $message) {
    array_push($this->errors, new ThreeScaleResponseError($index, $code, $message));
  }
}

/**
 * Object containing detailed information about an error.
 */
class ThreeScaleResponseError {
  private $index;
  private $code;
  private $message;

  public function __construct($index, $code, $message) {
    $this->index = $index;
    $this->code = $code;
    $this->message = $message;
  }

  /**
   * Index of the transaction that caused this error. This has meaning only if the error 
   * was result of the ThreeScaleClient::report() call. Otherwise it's null.
   *
   * @return int
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * System code of the error.
   *
   * @return string
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * Human readable description of the error.
   *
   * @return string
   */
  public function getMessage() {
    return $this->message;
  }
}

?>
