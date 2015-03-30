<?php
  session_start();
  $ini_array = parse_ini_file("/etc/stoker.ini", true);
  if ( $_SESSION["authenticated"] != 'true' ) {
    $mode = $ini_array["options"]["guest_access"];
  } else {
    $mode = "full";
  }
  if (( $mode != "read-only" ) && ( $mode != "full" )) {
    print "Sorry, system policy requires you to be logged in.";
    exit(1);
  }

  $daemon = $ini_array["paths"]["daemon"];
  $cook_path = $ini_array["paths"]["cooks"];

function get_active_cooks() {
  unset($active_cooks);
  global $daemon;
  global $cook_to_show;
  global $active_cook;
  global $curr_stoker_name;
  global $active_cooks;
  exec('pgrep -a $(basename '.$daemon.')', $daemon_list);
  foreach ($daemon_list as $daemon) {
    $cook = explode(" ",$daemon);
    $pid = $cook[0];
    $ip = $cook[3];
    $log = $cook[4];
    $cook_name = basename($log,'.log');
    $active_cooks[$ip]["pid"] = $pid;
    $active_cooks[$ip]["log"] = $log;
    $active_cooks[$ip]["cook_name"] = $cook_name;
    $cook_to_show = $cook_name;
    $active_cook = true;
  }
}


  get_active_cooks();

  foreach ($ini_array["devices"] as $id => $stoker) {
    $ip = explode(',',$stoker)[0];
    $name = explode(',',$stoker)[1];
    $stokers[$id]["ip"] = $ip;
    $stokers[$id]["name"] = $name;
    if (isset($active_cooks[$ip])) {
      $stokers[$id]["active"] = true;
    } else {
      $stokers[$id]["active"] = false;
    }
  } 

  if (isset($_POST["start_cook"]) && ($mode == 'full')) {
    $id = $_POST["start_cook"];
    $name = $stokers[$id]["name"];
    $ip = $stokers[$id]["ip"];
    $cook_name = date('Y-m-d_H-i-s');
    $log = $cook_path.'/'.$cook_name.".log";
    if (isset($active_cooks[$ip]["pid"])) {
      $curr_cook_name = $active_cooks[$ip]["cook_name"];
      print "<br>Error, $name already monitoring $curr_cook_name.<br>";
    } else {
      print "<br>Starting cook $cook_name for $name.<br>";
      exec("start-stop-daemon --background --oknodo --start --pidfile /tmp/$cook_name.pid --startas $daemon -- $ip $log", $output);
      $stokers[$id]["active"] = true;
      get_active_cooks();
      $cook_to_show = $cook_name;
      $active_cook = true;
      sleep(5);
    }
  }

  if (isset($_POST["stop_cook"]) && ($mode == 'full')) {
    print_r($_POST);
    $pid = $_POST["stop_cook"];
    exec("kill $pid");
    sleep(5);
    $stokers[$id]["active"] = false;
// TO-DO: If this was the cook_to_show we should set active_cook = false;
    get_active_cooks();
  }

  $log_list = scandir($cook_path);
  foreach ( $log_list as $logfile ) {
    if ( preg_match('/.*\.log$/', $logfile) ) {
      $cook_name = basename($logfile,'.log');
      $old_cooks[$cook_name]["logfile"] = $logfile;
    }
  }
?>
