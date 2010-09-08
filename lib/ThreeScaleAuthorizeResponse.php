<?php

/**
 * Defines ThreeScaleAuthorizeResponse and ThreeScaleAuthorizeResponseUsage classes.
 *
 * @copyright 2010 3scale networks S.L.
 */

require_once(dirname(__FILE__) . '/ThreeScaleResponse.php');

/**
 * Object that wraps responses from successful authorize calls.
 */
class ThreeScaleAuthorizeResponse extends ThreeScaleResponse {
  private $usageReports = array();
  private $plan;

  /**
   * @internal Set plan name.
   */
  public function setPlan($plan) {
    $this->plan = $plan;
  }

  /**
   * Get name of the plan the application is signed up to.
   *
   * @return string
   */
  public function getPlan() {
    return $this->plan;
  }

  /**
   * @internal Add usage report entry.
   */
  public function addUsageReport() {
    $usageReport = new ThreeScaleAuthorizeResponseUsageReport;
    array_push($this->usageReports, $usageReport);
    return $usageReport;
  }

  /**
   * Get list of usage report entries.
   *
   * There will be one entry per each usage limit defined on the plan the used
   * is signed up to.
   *
   * @see ThreeScaleAuthorizeResponseUsage
   */
  public function getUsageReports() {
    return $this->usageReports;
  }
}

/**
 * Object with information about application's usage and how close it is to meeting 
 * the limits.
 *
 * One object of this class always corresponds to one usage limit defined on the plan.
 */
class ThreeScaleAuthorizeResponseUsageReport {
  private $metric;
  private $period;
  private $periodStart;
  private $periodEnd;
  private $parsedPeriodStart;
  private $parsedPeriodEnd;

  /**
   * Name of the metric the usage limit is defined for.
   *
   * @returns string
   */
  public function getMetric() {
    return $this->metric;
  }

  /**
   * @internal set the metric.
   */
  public function setMetric($metric) {
    $this->metric = $metric;
    return $this;
  }

  /**
   * The period of the usage limit. 
   *
   * @returns string
   *
   * This returns symbolic value: "year", "month", "week", "day", "hour" or "minute".
   */
  public function getPeriod() {
    return $this->period;
  }

  /**
   * @internal set the period.
   */
  public function setPeriod($period) {
    $this->period = $period;
    return $this;
  }

  /**
   * Start of the current period of the usage limit, as unix timestamp.
   *
   * @return int (unit timestamp)
   */
  public function getPeriodStart() {
    if (is_null($this->parsedPeriodStart)) {
      $this->parsedPeriodStart = strtotime($this->periodStart);
    }

    return $this->parsedPeriodStart;
  }

  /**
   * End of the current period of the usage limit, as unix timestamp.
   *
   * @return int (unit timestamp)
   */
  public function getPeriodEnd() {
    if (is_null($this->parsedPeriodEnd)) {
      $this->parsedPeriodEnd = strtotime($this->periodEnd);
    }

    return $this->parsedPeriodEnd;
  }

  /**
   * @internal set start and end of the period.
   */
  public function setPeriodInterval($start, $end) {
    $this->periodStart = $start;
    $this->periodEnd = $end;
    return $this;
  }

  /**
   * The value the application alredy spent in the current period of the usage limit.
   *
   * @return int
   */
  public function getCurrentValue() {
    return $this->currentValue;
  }

  /**
   * @internal set current value.
   */
  public function setCurrentValue($value) {
    $this->currentValue = $value;
    return $this;
  }

  /**
   * Maximum value allowed by the usage limit for the current period.
   *
   * @return int
   */
  public function getMaxValue() {
    return $this->maxValue;
  }

  /**
   * @internal set max value.
   */
  public function setMaxValue($value) {
    $this->maxValue = $value;
    return $this;
  }

  /** 
   * Is the usage limit corresponding to this report exceeded?
   */
  public function isExceeded() {
    return $this->getCurrentValue() > $this->getMaxValue();
  }
}

?>
