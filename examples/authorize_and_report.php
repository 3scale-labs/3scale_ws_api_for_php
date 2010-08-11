<?php

require(dirname(__FILE__) . '/../lib/ThreeScaleClient.php');

// Put your provider authentication key here:
$provider_key = "xxx";

// Put some user keys here (you can test it with the one from test contract):
$user_key_one = "yyy";
$user_key_two = "zzz";

$client = new ThreeScaleClient($provider_key);

// Auth the users key
$response = $client->authorize($user_key_one);

// Check the response type
if ($response->isSuccess()) {
	// All fine, proceeed & pull the usage reports
	$usageReports = $response->getUsageReports();

  echo "Success:"
	echo "  Plan: " .          $response->getPlan();
	echo "  Usage reports: " . var_export($usageReports, true);
} else {
	// Something's wrong with this user.
	echo "Error: " . $response->getErrorMessage();
}
	
// Report some usages
$response = $client->report(
	array(
	  array('user_key' => $user_key_one, 'usage' => array('hits' => 1)),
	  array('user_key' => $user_key_two, 'usage' => array('hits' => 1))
	)
);
		  
?>
