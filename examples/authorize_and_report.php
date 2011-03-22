<?php

require(dirname(__FILE__) . '/../lib/ThreeScaleClient.php');

// Put your provider key here:
$provider_key = "aaa";

// Put some app_ids here (you can test it with the one from test contract):
$app_id_one = "bbb";
$app_id_two = "ccc";

// Put a app key corresponding to the first app_id here (you can leave it empty, if
// the app has no keys defined.)
$app_key_one = "";

$client = new ThreeScaleClient($provider_key);

// Auth the application
$response = $client->authorize($app_id_one, $app_key_one);

// Check the response type
if ($response->isSuccess()) {
	// All fine, proceeed & pull the usage reports
	$usageReports = $response->getUsageReports();

        echo "Success:";
	echo "  Plan: " .          $response->getPlan();
	echo "  Usage reports: " . var_export($usageReports, true);
} else {
	// Something's wrong with this app.
	echo "Error: " . $response->getErrorMessage();
}

// Report some usages
$response = $client->report(
	array(
	  array('app_id' => $app_id_one, 'usage' => array('hits' => 1)),
	  array('app_id' => $app_id_two, 'usage' => array('hits' => 1))
	)
);
		  
?>
