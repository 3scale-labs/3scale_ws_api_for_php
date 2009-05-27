<?php

require(dirname(__FILE__) . '/../lib/ThreeScaleInterface.php');

$host = "http://server.3scale.net";

// Put your provider authentication key here:
$provider_key = "3scale-xxx";

// Put some user key here (you can test it with the one from test contract):
$user_key = "3scale-yyy";


$interface = new ThreeScaleInterface($host, $provider_key);
$transaction = $interface->start($user_key, array('hits' => 1));

var_export($transaction);

?>
