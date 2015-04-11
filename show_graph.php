<!-- 
Highcharts is stored locally because I don't want to deal with change control,
but Highcharts needs jquery which I pull from Google with an explicit 
version.
-->

<!-- 
This should fix most font issues, but Chrome's default font in Win8 is pretty
bad and has few UTF-8 characters. Change it to Tahoma if you have problems.
-->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="js/highcharts.js"></script>

<!-- Spacer that holds the control tab -->
<div id="tab_div" class="tab-div"></div>

<!-- The graph container -->
<div id="title" class="title-bar"></div>

<!-- The graph container -->
<div id="container" style="width:100%; height:70%;"></div>

<div id="control_output" align="center" style="width:100%; height:100px; display: none">
<iframe id="control_output_frame"></iframe>
</div>

<!-- Cook control tab -->
<div id="control_tab" class="show-menu" onClick="toggle_menu();">Cook Controls</div>

<!-- Background div when the menu is up -->
<div id="menu_background" class="menu-background-div" onClick="clear_menu();">
  <!-- Container div to help center the menu -->
<!--
  <div id="menu_container" class="menu-container-div">
-->
    <!-- The actual menu -->
    <div id="menu" class="menu-div" onClick="event.stopPropagation();">
      <h3>Sensor List</h3>
      <!-- Shows a list of sensors and temperature -->
      <div id="sensor_menu"></div>
      <h3>Blower List</h3>
      <!-- Shows a list of blowers and associations -->
      <div id="blower_menu"></div>
    </div>
    <!-- The cook list --> 
    <div id="cook_list" class="cooks-div" onClick="event.stopPropagation();">
<form id="view-cook">
<?php
$cook_list = [];
$file_list = scandir($cook_path);
foreach ($file_list as $file) {
  $file_arr = explode('.', $file);
  $file_ext = $file_arr[count($file_arr) - 1];
  $file_base = basename($file, '.'.$file_ext);
  if ( $file_ext == 'log' ) {
    $curr_cook["name"] = $file_base;
    $curr_cook["display_name"] = $file_base;

    if ( file_exists($cook_path.'/'.$file_base.'.ini') ) {
      $cook_data = parse_ini_file($cook_path.'/'.$file_base.'.ini');
      if ( isset($cook_data["display_name"]) ) {
        $curr_cook["display_name"] = $cook_data["display_name"];
      } 
    }

    $last_line = '';
    $f = fopen($cook_path.'/'.$file, 'r');
    $first_line = fgets($f);
    $cursor = -1;
    fseek($f, $cursor, SEEK_END);
    $char = fgetc($f);

    // Trim trailing newline chars of the file
    while ($char === "\n" || $char === "\r") {
      fseek($f, $cursor--, SEEK_END);
      $char = fgetc($f);
    }

    // Read until the start of file or first newline char
    while ($char !== false && $char !== "\n" && $char !== "\r") {
      $last_line = $char . $last_line;
      fseek($f, $cursor--, SEEK_END);
      $char = fgetc($f);
    }
    fclose($f);

    $start_time = json_decode($first_line, true)["stoker"]["timestamp"];
    $end_time = json_decode($last_line, true)["stoker"]["timestamp"];
    $cook_duration = $end_time - $start_time;
    $cook_days = floor($cook_duration / (24 * 60 * 60));
    $cook_duration = $cook_duration - ($cook_days * 24 * 60 * 60);
    $cook_hours = floor($cook_duration / (60 * 60));
    $cook_duration = $cook_duration - ($cook_hours * 60 * 60);
    $cook_minutes = round($cook_duration / 60);
    if ( $cook_days > 0 ) {
      $duration = $cook_days.'d';
    } else {
      $duration = '';
    }
    if ( $cook_hours > 0 ) {
      $duration = $duration.$cook_hours.'h';
    }
    if (( $cook_minutes > 0 ) || ( $duration == '' )) {
      $duration = $duration.$cook_minutes.'m';
    }
    $curr_cook["duration"] = $duration;
    $curr_cook["active"] = false;
    foreach ( $active_cooks as $cook ) {
      if ( $cook["cook_name"] == $curr_cook["name"] ) {
        $curr_cook["active"] = true;
      }
    }
    $curr_cook["endtime"] = $end_time;
    $curr_cook["date"] = date('Y-M-d', $end_time);
    array_push($cook_list, $curr_cook);
// I should build this as an array of objects, sort the array, then create the divs.
  }  
}
usort($cook_list, function($a, $b) {
  return $b["endtime"] - $a["endtime"];
});
foreach ( $cook_list as $cook ) {
  if ( $cook["active"] ) {
    $cook_display = "<b>".$cook["display_name"]."*</b>";
    $delete_button = '<img name="cook-delete" id="cook-delete-'.$cook["name"].'" class="ok-button" style="display: none; height: 0px; width: 0px;">';
  } else {
    $cook_display = $cook["display_name"];
    $delete_button = '<img name="cook-delete" id="cook-delete-'.$cook["name"].'" class="ok-button" style="display: none" src="delete.png" onClick="delete_cook(\''.$cook["name"].'\');">';
  }
  print '<div class="cook" name="cook-div" id="cook-'.$cook["name"].'" onClick="show_cook_buttons(\''.$cook["name"].'\');">
  <table>
    <tr>
      <td id="cook-display-'.$cook["name"].'" colspan="2">'.$cook_display.'</td>
      <td rowspan="2"><img name="cook-ok" id="cook-ok-'.$cook["name"].'" class="ok-button" style="display: none" src="ok-button.png" onClick="show_cook(\''.$cook["name"].'\');"></td>
      <td rowspan="2">'.$delete_button.'</td>
    </tr>
    <tr>
      <td align="left" class="cook-desc">'.$cook["date"].'</td>
      <td align="right" class="cook-desc">'.$cook["duration"].'</td>
    </tr>
  </table>
</div>';
}
print '<div class="cook" style="float: right; font-size: 75%; position: absolute; bottom: 10; right: 10;"><i><b>bold*</b> cooks are active.</i></div>';
?>
</form>
    </div>
<!--
  </div>
-->
</div>
<script>
/*
Bug tracker:
I can't seem to get connectNulls=false to work - they do seem to work when I miss a poll, just not when a sensor is missing...
Better error checking.
Anyplace else I should be using CSS? Check for style=
Stop Cook should be moved to Cook Controls, should Start Cook?
Add indicator to blower as to which sensor is controlling it.
*/

<?php
// Pass some variables from php-land to JavaScript.
print "
var cook_to_show = \"".$cook_to_show."\";
var display_name = \"".$display_name."\";
var active_cook = \"".$active_cook."\";
var mode = \"".$mode."\";
";
?>

// If you've got the access, show the control div
if ( mode == 'full' ) {
  document.getElementById('control_tab').style.display = 'block';
}

// The actual graph object.
var chart;

// These are an attempt to track null values, however I can't seem to get the
// gaps working properly.
var id_list = []; 
var timestamp_list = []; 

// The last timestamp I processed so I don't duplicate data points.
var last_timestamp = 0;

// Updates the cook's title
function update_title() {
  if ( mode == 'full' ) {
    // The title has naughty characters, it's safest to grab the value
    title = document.getElementById('title-input').value;
    // Update the graph title
    graph_title(title);
    // URI encode the title and pass it to the php that will update the files.
    uri_title = encodeURIComponent(title);
    url = 'rename_cook.php?cook='+cook_to_show+'&name='+uri_title;
    document.getElementById('control_output_frame').src = url;
    // Now we need to update the cook list, it's either that or a reload but
    // that has timing issues.
    if ( active_cook == 'true' ) {
      title = "<b>"+title+"*</b>";
    }
    document.getElementById('cook-display-'+cook_to_show).innerHTML = title;
  }
}

// Replaces the title with an input box
function title_to_input() {
  if ( mode == 'full' ) {
    title = document.getElementById('title-text').innerHTML;
    document.getElementById('title').innerHTML = "<input class='title-input' id='title-input'> <img src='ok-button.png' class='ok-button' onClick='update_title()'> <img src='cancel.png' class='ok-button' onClick='graph_title(document.getElementById(\"title-input\").defaultValue)'>";
    title_input = document.getElementById('title-input');
    title_input.defaultValue = title;
  }
}

// The cancel button was clicked on a rename
function restore_name(id) {
  if ( mode == 'full' ) {
    name = document.getElementById("name_input_"+id).defaultValue;
    document.getElementById("name_"+id).innerHTML = "<div id='name_div_"+id+"' onClick='name_to_input(\""+id+"\")'>"+name+"</div>";
  }
}

function update_name(id) {
  if ( mode == 'full' ) {
    orig_name = document.getElementById("name_input_"+id).value;
    name = encodeURIComponent(orig_name);
    type = document.getElementById("name_"+id).getAttribute("name");
    url = "set_sensor_name.php?cook="+cook_to_show+"&id="+id+"&name="+name+"&type="+type;
    document.getElementById('control_output_frame').src=url;
    document.getElementById("name_"+id).innerHTML = "<div id='name_div_"+id+"' onClick='name_to_input(\""+id+"\")'>"+orig_name+"</div>";
  }
}

// Replaces a sensor/blower name with an input box
function name_to_input(id) {
  if ( mode == 'full' ) {
    input_td = document.getElementById("name_"+id);
    prev_value = document.getElementById("name_div_"+id).innerHTML;
    input_td.innerHTML = "<input class='title-input' id='name_input_"+id+"'> <img src='ok-button.png' class='ok-button' onClick='update_name(\""+id+"\")'> <img src='cancel.png' class='ok-button' onClick='restore_name(\""+id+"\")'>";
    document.getElementById("name_input_"+id).defaultValue = prev_value;
  }
}

// Set the display title on the graph
function graph_title(title) {
  title_div = document.getElementById('title');
  title_div.innerHTML = "<div id='title-text' onClick='title_to_input()'>"+title+"</div>";
  document.title = "stoker_mon "+title;
}

// Shows the cook view/delete buttons
function show_cook_buttons(cook_name) {
  cook_ok_buttons = document.getElementsByName('cook-ok');
  cook_delete_buttons = document.getElementsByName('cook-delete');
  cook_divs = document.getElementsByName('cook-div');
  for ( i = 0; i < cook_ok_buttons.length; i++) {
    cook_ok_buttons[i].style.display = 'none';
    cook_delete_buttons[i].style.display = 'none';
    cook_divs[i].className = 'cook';
  }
  if ( document.getElementById('cook-ok-'+cook_name) != null ) {
    document.getElementById('cook-ok-'+cook_name).style.display = 'block';
  }
  if (( document.getElementById('cook-ok-'+cook_name) != null ) && ( mode == 'full' )) {
    document.getElementById('cook-delete-'+cook_name).style.display = 'block';
  }
  if ( document.getElementById('cook-'+cook_name) != null ) {
    document.getElementById('cook-'+cook_name).className = 'cook-selected';
  }
}

// Reloads the page with a specific cook name
function show_cook(cook_name) {
  url = (window.location.href).split('?')[0];
  url += '?cook_to_show='+cook_name;
  window.location.href = url;
}

// Deletes a cook permanently
function delete_cook(cook_name) {
  if (window.confirm("Are you sure you want to delete this cook?")) {
    url = "delete_cook.php?cook="+cook_name;
    document.getElementById('control_output_frame').src=url;
    document.getElementById('cook-'+cook_name).remove();
  } 
}

// Sets a target temperature for a sensor
function set_target(id) {
  target = document.getElementById(id).value;
  id = id.replace('_target','');
  // This should probably be an AJAX call...
  url = "set_temp.php?cook="+cook_to_show+"&id="+id+"&target="+target;
  document.getElementById('control_output_frame').src=url;
}

// Sets a target temperature for a sensor
function set_blower(id) {
  controller = document.getElementById(id).value;
  // This should probably be an AJAX call...
  url = "set_blower.php?cook="+cook_to_show+"&id="+id+"&controller="+controller;
  document.getElementById('control_output_frame').src=url;
}

function clear_menu() {
  var c = document.getElementById("cook_list");
  var m = document.getElementById("menu");
  var b = document.getElementById("menu_background");
  c.style.display = 'none';
  m.style.display = 'none';
  b.style.display = 'none';
}

function toggle_cooks() {
  var c = document.getElementById("cook_list");
  var b = document.getElementById("menu_background");
  if(c.style.display == 'block') {
    c.style.display = 'none';
    b.style.display = 'none';
  } else {
    c.style.display = 'block';
    b.style.display = 'block';
  }
}

function toggle_menu() {
  var m = document.getElementById("menu");
  var b = document.getElementById("menu_background");
  if(m.style.display == 'block') {
    m.style.display = 'none';
    b.style.display = 'none';
  } else {
    // Take the latest stoker_reading and generate the list of current 
    // sensors and blowers, we don't want to try to set sensors that aren't
    // in the latest poll.
    sensor_list = stoker_reading["stoker"]["sensors"];
    blower_list = stoker_reading["stoker"]["blowers"];
    sensor_menu = document.getElementById("sensor_menu");
    // We use the sensor/blower_html variables to build a table.
    sensor_html = "<table>";
    blower_menu = document.getElementById("blower_menu");
    blower_html = "<table>";
    // Walk the sensor list.
    for (i = 0; i < sensor_list.length; ++i) {
      id = sensor_list[i]["id"];
      name = sensor_list[i]["name"];
      setting = sensor_list[i]["ta"];
      sensor_control = "<tr><td id='name_"+id+"' name='sensors'><div id='name_div_"+id+"' onClick='name_to_input(\""+id+"\")'>"+name+"</div></td><td><input id='"+id+"' type='number' min='0' max='999' style='text-align: right;' size='3' value='"+setting+"'></td><td><img src='ok-button.png' class='ok-button' onClick='set_target(\""+id+"\");'></td></tr>";
      sensor_html += sensor_control;
    }
    sensor_html += "</table>";
    // Now set the HTML of the sensor menu div to the HTML we just built.
    sensor_menu.innerHTML = sensor_html;
    // Walk the blower list.
    for (i = 0; i < blower_list.length; ++i) {
      id = blower_list[i]["id"];
      name = blower_list[i]["name"];
      blower_control = "<tr><td id='name_"+id+"' name='blowers'><div id='name_div_"+id+"' onClick='name_to_input(\""+id+"\")'>"+name+"</div></td><td><select id='"+id+"'>";
      // We need to walk the sensor list to build the select.
      for (c = 0; c < sensor_list.length; c++ ) {
        sensor_id = sensor_list[c]["id"];
        sensor_name = sensor_list[c]["name"];
        // For some reason they associate the blower to the sensor rather than
        // the sensor to the blower. If we hit the right sensor mark it as
        // selected.
        if ( sensor_list[c]["blower"] == id) {
          sensor_selected = " selected";
        } else {
          sensor_selected = "";
        }
        blower_control += "<option value='"+sensor_id+"'"+sensor_selected+">"+sensor_name+"</option>";
      }
      blower_control += "</select></td><td><img src='ok-button.png' class='ok-button' onClick='set_blower(\""+id+"\");'></td></tr>";
      blower_html += blower_control;
    }
    blower_html += "</table>";
    blower_menu.innerHTML = blower_html;
    m.style.display = 'block';
    b.style.display = 'block';
  }
}

// Make sure we've got a series and add a datapoint
function add_point(series_id, series_name, x, y, type, redraw) {
  // Assume we've got a sensor if no type is passed.
  type = typeof type !== 'undefined' ? type : 'sensor';
  // Assume we want to redraw with each point unless we explicitly
  // pass a false.
  redraw = typeof redraw !== 'undefined' ? redraw : true;
  series = chart.get(series_id);
  // If it's a new series
  if ( series == null ) {
    // Set some type-specific Highchart parameters.
    if ( type == 'sensor' ) {
      tooltip_valueSuffix = '°F';
      yAxis = 0;
    } else {
      tooltip_valueSuffix = '';
      yAxis = 1;
    }
    chart.addSeries({
      id: series_id,
      name: series_name,
      yAxis: yAxis,
      connectNulls: false,
      tooltip: {
        valueSuffix: tooltip_valueSuffix
      },
      data: []
    },redraw);
    id_list.push(series_id);
    series = chart.get(series_id);
  }
  // I'm sure there's a cleaner way than constructing a JSON string and 
  // calling JSON.parse...
  point = JSON.parse('['+x+','+y+']');
  timestamp_list[id_list.indexOf(series_id)] = x;
  series.addPoint(point, redraw, false);
  // In case you change a sensor or blower name the series will need an update.
  if ( series.name != series_name ) {
    series.update({name:series_name}, redraw);  
  }
}

// Much of this is from the Highcharts sample code. Grabs data from the 
// server via AJAX and processes it.
function requestData() {
if (last_timestamp == 0) {
  // This is the initial draw, we need the whole log and we don't want 
  // redraw on every data point.
  url = "showlog.php?cook="+cook_to_show;
  redraw = false;
} else {
  // Grab lines since last poll plus one (in case clock skew makes us miss 
  // one, and round up as an extra measure of protection)
  curr_time = (new Date).getTime();
  linecount = Math.ceil((curr_time - last_timestamp) / 60000 + 1);
  url = "showlog.php?cook="+cook_to_show+"&count="+linecount;
  // If we've got a lot of lines it could take a while to redraw.
  if ( linecount < 10 ) {
    redraw = true;
  } else {
    redraw = false;
  }
}
$.ajax({
    url: url,
    success: function(loglines) {
        // Process the log line-by-line
        lines = loglines.trim().split('\n');
        for (index = 0; index < lines.length; ++index) {
          // This 'NULL' catch seems superfluous now that I check if the 
          // JSON.parse() fails.
          if (lines[index] != 'NULL') {
            // Assume the line is JSON.
            is_json = true;
            try {
              stoker_reading = JSON.parse(lines[index]);
            } catch (e) {
              // Except when it's not...
              is_json = false;
            }
            if ( is_json ) {
              timestamp = stoker_reading["stoker"]["timestamp"]*1000;
            } else {
              // A rather ineligant way to say "skip this line".
              timestamp = last_timestamp;
            }
          } else {
            timestamp = last_timestamp;
          }
          // Only process a point if it's newer than the last one, we should 
          // have a point or two of repeat data normally.
          if (timestamp > last_timestamp) {
            last_timestamp = timestamp;
            // Walk through each of the sensors and add datapoints for each
            sensors = stoker_reading["stoker"]["sensors"];
            if ( sensors == null ) {
              length = 0;
            } else {
              length = sensors.length;
            }
            for (sensor_index = 0; sensor_index < length; ++sensor_index) {
              id = sensors[sensor_index]["id"];
              name = sensors[sensor_index]["name"];
              // First add a point for the current temperature
              add_point(id, name, timestamp, sensors[sensor_index]["tc"],'sensor',redraw);
              // Now add a point for the target temperature
              add_point(id+"_target", name+" Target", timestamp, sensors[sensor_index]["ta"],'sensor',redraw);
              // Keep track of each datapoint in an array so I can seen NULLs
              timestamp_list[id_list.indexOf(id)] = timestamp;
            }
            // Now do the same things with the blowers.
            blowers = stoker_reading["stoker"]["blowers"];
            if ( blowers == null ) {
              length = 0;
            } else {
              length = blowers.length;
            }
            for (blower_index = 0; blower_index < length; ++blower_index) {
              // Blowers are different, they are on (1) or off (0).
              blower_id = blowers[blower_index]["id"];
              blower_name = blowers[blower_index]["name"];
              blower_state = blowers[blower_index]["on"];
              add_point(blower_id,blower_name,timestamp,blower_state,'blower',redraw);
            }
          }
        }
        // Explicitly add nulls in hopes that they will trigger a line gap
        for ( id_index = 0; id_index < id_list.length; ++id_index ) {
          if ( timestamp_list[id_index] != timestamp ) {
            console.log("null");
            add_point(id_list[id_index], "", timestamp, 'null', 'none', redraw);
          }
        }
        // At this point I'm done processing points so I should redraw the
        // chart if I hadn't been.
        if (redraw == false) {
          chart.redraw();
        }
        // If the cook isn't active, I'm done and don't need to continue
        // polling for data that will never come.
        if (active_cook == 'false') {
          return;
        }
        // Call it again after one minute
        setTimeout(requestData, 60000);    
    },
    cache: false
});
}

$(document).ready(function() {
  Highcharts.setOptions({
    global: {
      // If your timezone seems off, try setting this to true.
      useUTC: false
    }
  });
    chart = new Highcharts.Chart({
        chart: {
            zoomType: 'x',
            renderTo: 'container',
            defaultSeriesType: 'line',
            events: {
                load: requestData
            }
        },
        title: {
            text: ''
        },
        xAxis: {
            type: 'datetime',
            tickPixelInterval: 150,
            maxZoom: 20 * 1000
        },
        yAxis: [{
            labels: {
              format: '{value}°F'
            },
            minPadding: 0.2,
            maxPadding: 0.2,
            title: {
                text: 'Temperature',
                margin: 80
            }
        },{
            // A second series for the blower state, otherwise it will scale
            // poorly with the temperature.
            minPadding: 0.2,
            maxPadding: 0.2,
            title: {
              enabled: false
            },
            opposite: true,
            labels: {
              enabled: false
            },
            // The blower on/off should be ~10% of the graph height.
            max: 10,
            min: 0
        }],
        tooltip: {
          // The mouseOver event will show data for all series.
          shared: true,
          // These fail to make elegant use of touch interfaces.
          followPointer: true,
          followTouchMove: true
        },
        plotOptions: {
          series: {
            connectNulls: false
          }
        },
        series: []
    });        
});

graph_title(display_name);

if ( active_cook == 'false' ) {
  document.getElementById('control_tab').style.display = 'none';
}

</script>
