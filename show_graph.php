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
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
<script src="js/highcharts.js"></script>

<div id="container" style="width:100%; height:80%;"></div>
<div id="control" align="center" style="width:100%; display: none"></div>
<div id="control_output" align="center" style="width:100%; height:100px; display: none">
<iframe id="control_output_frame"></iframe>

</div>
<script>
/*
Bug tracker:
I can't seem to get connectNulls=false to work.
Better error checking.
Rather than use the "control" div, I should move it to the side and make it auto-hide. What I have now works, but is ugly.
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
  console.log(url);
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
    // If you've got the access, add a button to set a target.
    if (( mode == 'full' ) && 
      (series_id.match(/.*_target$/) != null)) {
      control_div = document.getElementById('control');
      // I'm just stacking buttons on top of each other here. This could be
      // done more intelligently, I'm sure.
      control_def = series_name+" <input id='"+series_id+"' type='text' size='3' value='"+y+"'><button onClick='set_target(\""+series_id+"\");'>Set</button><br>";
      control_div.innerHTML += control_def;
      control_div.style.display = 'block';
    }
    id_list.push(series_id);
    series = chart.get(series_id);
  }
  // I'm sure there's a cleaner way than constructing a JSON string and 
  // calling JSON.parse...
  point = JSON.parse('['+x+','+y+']');
  timestamp_list[id_list.indexOf(series_id)] = x;
  series.addPoint(point, redraw, false);
  // Here we check to see if the target temp has changed and update the 
  // text box appropriately. 
  if (( mode == 'full' ) && 
    (series_id.match(/.*_target$/) != null)) {
    set_field = document.getElementById(series_id);
    // Please don't change the field as I'm typing. kthxbai!
    if (( set_field.value != y ) && ( set_field != document.activeElement )) {
      set_field.value = y;
    }
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
                text: 'Fan',
                margin: 80
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
