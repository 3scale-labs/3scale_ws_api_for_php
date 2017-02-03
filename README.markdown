# Client for 3scale Web Service Management API [![Build Status](https://secure.travis-ci.org/3scale/3scale_ws_api_for_php.png?branch=master)](http://travis-ci.org/3scale/3scale_ws_api_for_php)
3scale integration plugin for PHP applications. 3scale is an API Infrastructure service which handles API Keys, Rate Limiting, Analytics, Billing Payments and Developer Management. Includes a configurable API dashboard and developer portal CMS. More product stuff at http://www.3scale.net/, support information at http://support.3scale.net/.

### Tutorials
* Plugin Setup: https://support.3scale.net/docs/deployment-options/plugin-setup
* Rate Limiting: https://support.3scale.net/docs/access-control/rate-limits
* Analytics Setup: https://support.3scale.net/quickstarts/3scale-api-analytics

## Installation

Download the source code from github: http://github.com/3scale/3scale_ws_api_for_php and place it somewhere accessible from your project.

## Usage

Require the ThreeScaleClient.php file (assuming you placed the library somewhere within the
include path):

```php
require_once('lib/ThreeScaleClient.php')
```

Then create an instance of the client
```php
$client = new ThreeScaleClient("your_service_token", true);
```

> NOTE: unless you specify ```(your_service_token", true)``` you will be expected to specify
a `provider_key` parameter, which is deprecated in favor of Service Tokens:
```php
$client = new ThreeScaleClient("your provider key");
```

Because the object is stateless, you can create just one and store it globally.

**NOTE: From November 2016 `service_id` is mandatory.**

### Authorize

To authorize a particular application, call the `authorize` method passing it the 
`application id` and `service id` and optionally the application key:

```php
$response = $client->authorize("the app id", "the app key", "service id");
```

Then call the `isSuccess()` method on the returned object to see if the authorization was
successful:

```php
if ($response->isSuccess()) {
  // All fine, proceeed.
} else {
  // Something's wrong with this app.
}
```

If both provider and app id are valid, the response object contains additional information about the status of the application:

```php
//Returns the name of the plan the application is signed up to.
$response->getPlan()
```

If the plan has defined usage limits, the response contains details about the usage broken down by the metrics and usage limit periods.

```php
// The usageReports array contains one element per each usage limit defined on the plan.
$usageReports = $response->getUsageReports();
$usageReport  = $usageReports[0];

// The metric
$usageReport->getMetric() // "hits"

// The period the limit applies to
$usageReport->getPeriod()       // "day"
$usageReport->getPeriodStart()  // 1272405600 (Unix timestamp for April 28, 2010, 00:00:00)
$usageReport->getPeriodEnd()    // 1272492000 (Unix timestamp for April 29, 2010, 00:00:00)

// The current value the application already consumed in the period
$usageReport->getCurrentValue() // 8032

// The maximal value allowed by the limit in the period
$usageReport->getMaxValue()     // 10000

// If the limit is exceeded, this will be true, otherwise false:
$usageReport->isExceeded()      // false
```

If the authorization failed, the `getErrorCode()` returns system error code and `getErrorMessage()` human readable error description:
 
```php    
$response->getErrorCode()       // "usage_limits_exceeded"
$response->getErrorMessage()    // "Usage limits are exceeded"
```

### Report

To report usage, use the `report` method. You can report multiple transaction at the same time:

```php    
$response = $client->report(array(
      array('app_id' => "first app's id",'usage' => array('hits' => 1)),
      array('app_id' => "second app's id", 'usage' => array('hits' => 1))), "service id");
```

The `"app_id"`,  `"usage"` parameters are required alongiwth `service id`. Additionaly, you can specify a timestamp
of the transaction:

```php
$response = $client->report(array(
  array('app_id'    => "app's id",
        'usage'     => array('hits' => 1),
        'timestamp' => mktime(12, 36, 0, 4, 28, 2010, "service id"))));
```

The timestamp can be either an unix timestamp (as integer) or a string. The string has to be in a
format parseable by the [strtotime](http://php.net/manual/en/function.strtotime.php) function.
For example:

```php    
"2010-04-28 12:38:33 +0200"
```

If the timestamp is not in UTC, you have to specify a time offset. That's the "+0200" 
(two hours ahead of the Universal Coordinate Time) in the example above.

Then call the `isSuccess()` method on the returned response object to see if the report was
successful:

```php    
if ($response->isSuccess()) {
  // All OK.
} else {
  // There was an error.
}
```

In case of error, the `getErrorCode()` returns system error code and `getErrorMessage()`
human readable error description:

```php    
$response->getErrorCode()    // "provider_key_invalid"
$response->getErrorMessage() // "provider key \"foo\" is invalid"
```

## Plugin integration

If you are interested in integrating the plugin with:

* [Composer](http://getcomposer.org/) check [the packagist](https://packagist.org/packages/tdaws/3scale_ws_api_for_php). This is kindly maintained by [daws.ca](http://daws.ca) tech team.

* [Symphony2](http://symfony.com/) check [tonivdv's 3scaleBundle](https://github.com/tonivdv/3scaleBundle). This is kindly maintained by [Adlogix](http://www.adlogix.eu) tech team.

## To Test

To run tests: `php test/all.php`. Please note that you will first need to set the following environment variables using your own 3scale keys:
    
 - TEST_3SCALE_PROVIDER_KEY
 - TEST_3SCALE_APP_IDS
 - TEST_3SCALE_APP_KEYS
 - TEST_3SCALE_SERVICE_ID

## Legal

Copyright (c) 2010 3scale networks S.L., released under the MIT license.

