<?php
/* 
TO-DO:
Do I still need main.php or can I move it back to index.php?
On cook stop I should gzip the cook log.
Add description to new cook.
Check JSON error parsing in log file.
View old cooks.
Change cook title to Stoker name
Move set_temp to ajax, perhaps have a loading/error icon.
Delete old cooks?
I populate variables a lot, I should split ini.php to ini.php and check_login.php
Buttons should be disable-on-click.
5 second delay should be variable.
*/

  // Confirm session properties and initialize variables
  include "ini.php";
  // If you're not logged in, present the login button.
  if ( ! isset($_SESSION["authenticated"])) {
    include "login.php";
  }
  // If there's a cook_to_show, show it.
  if ( isset($cook_to_show)) {
    include "show_graph.php";
  }

  print "<title>stoker_mon Main Page</title>";
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
