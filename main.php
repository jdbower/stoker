<?php
/* 
TO-DO:
Do I still need main.php or can I move it back to index.php?
Check JSON error parsing in log file.
Move set_temp to ajax, perhaps have a loading/error icon.
I populate variables a lot, I should split ini.php to ini.php and check_login.php
Buttons should be disable-on-click.
5 second delay should be variable.
*/

  // Confirm session properties and initialize variables
  include "ini.php";

  print '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="stoker.css">
<title>stoker_mon Main Page</title>';

  // If you're not logged in, present the login button.
  if ( ! isset($_SESSION["authenticated"])) {
    include "login.php";
  }

  // Add the Old Cooks tab
  if ( $mode != 'none' ) {
    // The bottom tab is for all users, the top tab is for admins.
    print "<div id='bottom_tab' class='bottom-tab-div'></div>";
    print "<div id='cooks_tab' class='show-bottom-menu' onClick='toggle_cooks();'>Show Old Cooks</div>";
  }

  // If there's a cook_to_show, show it.
  if ( isset($cook_to_show)) {
    $display_name = $cook_to_show;
    if ( file_exists($cook_path.'/'.$cook_to_show.'.ini') ) {
      $cook_data = parse_ini_file($cook_path.'/'.$cook_to_show.'.ini');
      if ( isset($cook_data["display_name"]) ) {
        $display_name = $cook_data["display_name"];
      }
    }
    include "show_graph.php";
  }

  // Only if you've got full access should you show the Stokers and allow 
  // a cook to be started.
  if ( $mode == 'full' ) { 
    print "<hr><form id='start-stop-cook' method='post'>";
    foreach ($stokers as $id => $stoker) {
      // If a cook is started, we should be able to stop it.
      if ( $stoker["active"] == 'true' ) {
        $cook_name = $active_cooks[$stoker["ip"]]["cook_name"];
        $cook_pid = $active_cooks[$stoker["ip"]]["pid"];
        print "$cook_name <button name='stop_cook' value='$cook_pid'>Stop ".$stoker['name']." Cook</button>";
      } else {
        print "<button name='start_cook' value='$id' onclick='document.getElementById('start-stop-cook').submit(); this.disabled=true;'>Start ".$stoker['name']." Cook</button>";
      }
    }
    print "</form>";
  }
?>
