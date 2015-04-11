<?php

// This is somewhat dangerous, it renames cook files.

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

// We need a cook name
if ( isset($_GET["name"]) ) {
  $name = urldecode($_GET["name"]);
  // Kill double quotes
  $name = str_replace('"',"'",$name);
  $name = 'display_name="'.$name.'"';
} else {
  print "Name not specified.";
  exit (3);
}

if ( ! file_exists($cook_path.'/'.$cook.'.log') ) {
  print "Cook $cook doesn't exist.";
  exit(4);
}

$file = fopen($cook_path.'/'.$cook.'.ini', 'w');
fwrite($file, $name);
fclose($file);

?>
