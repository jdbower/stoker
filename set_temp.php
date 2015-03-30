<?php

// This is the dangerous page, it allows the user to set the temperature
// of a probe.

include 'ini.php';

// You must have full access to use this page, otherwise fail out gracelessly.
if (( $_SESSION["authenticated"] != 'true' ) || ( $mode != 'full' )) {
  print "Sorry, you must be authenticated!";
  exit(1);
}

// We need to set the cook (the date_time identifier), sensor ID, and the
// target temp. If any are missing, fail out.
if ( isset($_GET["cook"]) ) {
  $cook = $_GET["cook"];
} else {
  print "Cook not specified.";
  exit (2);
}
if ( isset($_GET["id"]) ) {
  $id = $_GET["id"];
} else {
  print "Probe ID not specified.";
  exit (2);
}
if ( isset($_GET["target"]) ) {
  $target = $_GET["target"];
} else {
  print "Target not specified.";
  exit (2);
}

// Search the active cooks to see if the cook exists, we can't change the
// target of a completed cook.
foreach ($active_cooks as $ip => $curr_cook) {
  if ($curr_cook["cook_name"] == $cook) {
    $stoker_ip = $ip;
  }
}
if ( $stoker_ip == null ) {
  print "Cook $cook not active.";
  exit(3);
}

// Construct the JSON string and pass it to the Stoker
$json_string = '{"stoker":{"sensors":[{"id":"'.$id.'","ta":'.$target.'}]}}';
$stoker_curl = curl_init();
$url = "http://$ip/stoker.Json_Handler";
curl_setopt($stoker_curl, CURLOPT_POST, true);
curl_setopt($stoker_curl, CURLOPT_POSTFIELDS, $json_string);
curl_setopt($stoker_curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
curl_setopt($stoker_curl, CURLOPT_URL, $url);
curl_setopt($stoker_curl, CURLOPT_HEADER, true);
curl_setopt($stoker_curl, CURLOPT_HEADER_OUT, true);
curl_setopt($stoker_curl, CURLOPT_VERBOSE, true);
curl_setopt($stoker_curl, CURLOPT_RETURNTRANSFER, true);
$stoker_resp = curl_exec($stoker_curl);
$resp_info = curl_getinfo($stoker_curl);
curl_close($stoker_curl);
// In theory the output should be 200 or 400.
print $resp_info['http_code'];
?>
