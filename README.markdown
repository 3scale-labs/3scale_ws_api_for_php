# Client for 3scale web service management system API #

This library provides client for the 3scale web service management API.

## Installation

Download the source code from github: http://github.com/3scale/3scale_ws_api_for_php and place
it somewhere accessible from your project.

## Usage

Require the ThreeScaleClient.php file (assuming you placed the library somewhere within the
include path):

    require_once('lib/ThreeScaleClient.php')

Then, create an instance of the client, giving it your provider API key:

    $client = new ThreeScaleClient("your provider key");

Because the object is stateless, you can create just one and store it globally.

### Authorize

To authorize a particular user, call the `authorize` method passing it the user key
identifiing the user:

    $response = $client->authorize("the user key");

Then call the `isSuccess()` method on the returned object to see if the authorization was
successful:

    if ($response->isSuccess()) {
      // All fine, proceeed.
    } else {
      // Something's wrong with this user.
    }

If the authorization succeeded, the response object contains additional information about 
the status of the user:

    // Returns the name of the plan the user is signed up to.
    $response->getPlan()

If the plan has defined usage limits, the response contains details about how close the user
is to meet these limits:

    // The usages array contains one element per each usage limit defined on the plan.
    $usages = $response->getUsages();
    $usage  = $usages[0];

    // The metric
    $usage->getMetric() // "hits"

    // The period the limit applies to
    $usage->getPeriod()      // "day"
    $usage->getPeriodStart() // 1272405600 (Unix timestamp for April 28, 2010, 00:00:00)
    $usage->getPeriodEnd()   // 1272491999 (Unix timestamp for April 28, 2010, 23:59:59)

    // The current value the user already consumed in the period
    $usage->getCurrentValue() // 8032

    // The maximal value allowed by the limit in the period
    $usage->getMaxValue()     // 10000

If the authorization failed, the `getErrors()` method returns one or more errors with more
detailed information:

    $errors = $response->getErrors();
    $error  = $errors[0];

    // Error code
    $error->getCode();    // "user.exceeded_limits"

    // Human readable error message
    $error->getMessage(); // "Usage limits are exceeded"

### Report

To report usage, use the `report` method. You can report multiple transaction at the same time:

    $response = $client->report(array(
      array('user_key' => "first user's key",  'usage' => array('hits' => 1)),
      array('user_key' => "second user's key", 'usage' => array('hits' => 1))));

The `"user_key"` and `"usage"` parameters are required. Additionaly, you can specify a timestamp
of the transaction:

    $response = $client->report(array(
      array('user_key'  => "user's key",
            'usage'     => array('hits' => 1),
            'timestamp' => mktime(12, 36, 0, 4, 28, 2010))));

The timestamp can be either an unix timestamp (as integer) or a string. The string has to be in a
format parseable by the [strtotime](http://php.net/manual/en/function.strtotime.php) function.
For example:

    "2010-04-28 12:38:33 +02:00"

If the timestamp is not in UTC, you have to specify a time offset. That's the "+02:00" 
(two hours ahead of the Universal Coordinate Time) in the example above.

Then call the `isSuccess()` method on the returned response object to see if the report was
successful:

    if ($response->isSuccess()) {
      # All OK.
    } else {
      # There were some errors
    }

If the report was successful, you are done. Otherwise, the `getErrors()` method will return array
of errors with more detailed information. Each of these errors has a `getIndex()` method that
returns numeric index of the transaction this error corresponds to. For example, if you report
three transactions, first two are ok and the last one has invalid user key, there will be
an error in the `getErrors()` array with the `getIndex()` equal to 2 (the indices start at 0):

    $errors = $response->getErrors();
    $error = $errors[0]
    $error->getIndex()   // 2
    $error->getCode()    // "user.invalid_key"
    $error->getMessage() // "User key is invalid"

## Legal

Copyright (c) 2010 3scale networks S.L., released under the MIT license.

