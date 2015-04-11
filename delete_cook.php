<?php

// This is somewhat dangerous, it deletes cook files.

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

// Search active cooks, we can't delete an active cook
foreach ($active_cooks as $ip => $curr_cook) {
  if ($curr_cook["cook_name"] == $cook) {
    $stoker_ip = $ip;
  }
}
if ( $stoker_ip != null ) {
  print "Cook $cook currently active.";
  exit(3);
}

if ( ! file_exists($cook_path.'/'.$cook.'.log') ) {
  print "Cook $cook doesn't exist.";
  exit(4);
}

foreach (glob($cook_path.'/'.$cook.'.*') as $file) {
  unlink($file);
}
?>
