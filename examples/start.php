<?php

require(dirname(__FILE__) . '/../lib/ThreeScaleInterface.php');

$host = "http://server.3scale.net";

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
	// All fine, proceeed & pull the usages
	$usages = $response->getUsages();
	
	echo "Success: " . $response->getPlan();
	echo "Success: " . var_export($usages, true);

	// Handle the current issues with the test keys
	if (count($usages) > 0) {
		$usage = $usages[0];
		echo "Success: " . var_export($usage, true);		  
	}
} else {
	// Something's wrong with this user.
	$errors = $response->getErrors();
	$error  = $errors[0];
	echo "Error: " . var_export($error->getMessage(), true);
}
	
// Report some usages
$response = $client->report(
	array(
	  array('user_key' => $user_key_one, 'usage' => array('hits' => 1)),
	  array('user_key' => $user_key_two, 'usage' => array('hits' => 1))
	)
);
		  
?>
