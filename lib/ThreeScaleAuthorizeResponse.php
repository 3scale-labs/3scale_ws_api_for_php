<?php
require_once(dirname(__FILE__) . '/ThreeScaleResponse.php');

class ThreeScaleAuthorizeResponse extends ThreeScaleResponse {
  private $usages;
  private $plan;

  public function __construct($success = true) {
    parent::__construct($success);
    $this->usages = array();

  }

  public function setPlan($plan) {
    $this->plan = $plan;
  }

  public function getPlan() {
    return $this->plan;
  }

  public function addUsage() {
    $usage = new ThreeScaleAuthorizeResponseUsage;
    array_push($this->usages, $usage);
    return $usage;
  }

  public function getUsages() {
    return $this->usages;
  }
}

class ThreeScaleAuthorizeResponseUsage {
  private $metric;
  private $period;
  private $periodStart;
  private $periodEnd;
  private $parsedPeriodStart;
  private $parsedPeriodEnd;

  public function getMetric() {
    return $this->metric;
  }

  public function setMetric($metric) {
    $this->metric = $metric;
    return $this;
  }

  public function getPeriod() {
    return $this->period;
  }

  public function setPeriod($period) {
    $this->period = $period;
    return $this;
  }

  public function getPeriodStart() {
    if (is_null($this->parsedPeriodStart)) {
      $this->parsedPeriodStart = strtotime($this->periodStart);
    }

    return $this->parsedPeriodStart;
  }
  
  public function getPeriodEnd() {
    if (is_null($this->parsedPeriodEnd)) {
      $this->parsedPeriodEnd = strtotime($this->periodEnd);
    }

    return $this->parsedPeriodEnd;
  }

  public function setPeriodInterval($start, $end) {
    $this->periodStart = $start;
    $this->periodEnd = $end;
    return $this;
  }

  public function getCurrentValue() {
    return $this->currentValue;
  }

  public function setCurrentValue($value) {
    $this->currentValue = $value;
    return $this;
  }
  
  public function getMaxValue() {
    return $this->maxValue;
  }

  public function setMaxValue($value) {
    $this->maxValue = $value;
    return $this;
  }
}

?>
