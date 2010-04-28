<?php

class ThreeScaleResponse {
  private $success;
  private $errors;

  public function __construct($success) {
    $this->success = $success;
    $this->errors = array();
  }

  public function isSuccess() {
    return $this->success;
  }
  
  public function addError($index, $code, $message) {
    array_push($this->errors, new ThreeScaleResponseError($index, $code, $message));
  }

  public function getErrors() {
    return $this->errors;
  }
}

class ThreeScaleResponseError {
  private $index;
  private $code;
  private $message;

  public function __construct($index, $code, $message) {
    $this->index = $index;
    $this->code = $code;
    $this->message = $message;
  }

  public function getIndex() {
    return $this->index;
  }
  
  public function getCode() {
    return $this->code;
  }

  public function getMessage() {
    return $this->message;
  }
}

?>
