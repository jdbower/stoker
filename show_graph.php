<!-- 
Highcharts is stored locally because I don't want to deal with change control,
but Highcharts needs jquery which I pull from Google with an explicit 
version.
-->

<!-- 
This should fix most font issues, but Chrome's default font in Win8 is pretty
bad and has few UTF-8 characters. Change it to Tahoma if you have problems.
-->
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" type="text/css" href="stoker.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="js/highcharts.js"></script>

<!-- Spacer that holds the control tab -->
<div id="tab_div" class="tab-div"></div>

<!-- The graph container -->
<div id="container" style="width:100%; height:80%;"></div>

<div id="control_output" align="center" style="width:100%; height:100px; display: none">
<iframe id="control_output_frame"></iframe>
</div>

<!-- Cook control tab -->
<div id="control_tab" class="show-menu" onClick="toggle_menu();">Cook Controls</div>

<!-- Background div when the menu is up -->
<div id="menu_background" class="menu-background-div" onClick="toggle_menu();">
  <!-- Container div to help center the menu -->
  <div id="menu_container" class="menu-container-div">
    <!-- The actual menu -->
    <div id="menu" class="menu-div" onClick="event.stopPropagation();">
      <h3>Probe List</h3>
      <!-- Shows a list of sensors and temperature -->
      <div id="sensor_menu"></div>
      <h3>Blower List</h3>
      <!-- Shows a list of blowers and associations -->
      <div id="blower_menu"></div>
    </div>
  </div>
</div>
<script>
/*
Bug tracker:
I can't seem to get connectNulls=false to work.
Better error checking.
I need to work on getting fan_mode=1m_dutycycle when polling the JSON
I need to change the probe name to a text input onClick and handle name changes
I need to list old cooks.
Anyplace else I should be using CSS? Check for style=
Stop Cook should be moved to Cook Controls, should Start Cook?
Can I allow a blower to be uncontrolled? potentially an option with sensor ID "-remove"?
Add indicator to blower as to which probe is controlling it.
*/

<?php
// Pass some variables from php-land to JavaScript.
print "
var cook_to_show = \"".$cook_to_show."\";
var active_cook = \"".$active_cook."\";
var mode = \"".$mode."\";
var authenticated = \"".$_SESSION['authenticated']."\";
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
      sensor_control = "<tr><td>"+name+"</td><td><input id='"+id+"' type='number' min='0' max='999' style='text-align: right;' size='3' value='"+setting+"'></td><td><img src='ok-button.png' class='ok-button' onClick='set_target(\""+id+"\");'></td></tr>";
      sensor_html += sensor_control;
    }
    sensor_html += "</table>";
    // Now set the HTML of the sensor menu div to the HTML we just built.
    sensor_menu.innerHTML = sensor_html;
    // Walk the blower list.
    for (i = 0; i < blower_list.length; ++i) {
      id = blower_list[i]["id"];
      name = blower_list[i]["name"];
      blower_control = "<tr><td>"+name+"</td><td><select id='"+id+"'>";
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
            text: 'Stoker Cook '+cook_to_show
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

</script>
