import telnetlib
import time
import os
import threading

# The main datapoint array
datapoints = {}

# A datapoint consists of a sensor_id (redundant), timestamp, and state
class datapoint:
  def __init__(self, sensor_id, timestamp, state):
    self.sensor_id = sensor_id
    self.timestamp = timestamp
    self.state = state

# Updates the variables in /tmp/[id].fan
def update_var():
  # First, make a thread that runs once a second
  threading.Timer(1.0, update_var).start()
  # Grab the datapoint array
  global datapoints
  # Iterate over each sensor in the datapoints
  for id, datapoint_list in datapoints.iteritems():
    total_points = 0
    total_on = 0
    total_off = 0
    # For each datapoint for each sensor...
    for curr_datapoint in datapoint_list:
      # If the timestamp is more than a minute old, remove it.
      if int(time.time()) - curr_datapoint.timestamp > 60:
        datapoint_list.pop(0)
      else:
        # Technically we don't need total_points...
        total_points = total_points + 1
        if curr_datapoint.state == 'on':
          total_on = total_on + 1
        else:
          total_off = total_off + 1
    # If there are datapoints calculate the duty cycle and cast as float.
    if total_points > 0:
      duty_cycle = total_on / float(total_points)
    else:
      duty_cycle = 0
    # Clobber the old file and write a new one.
    outfile = open("/tmp/"+id+".fan", "w")
    outfile.write(str(duty_cycle));
    outfile.close()

# Add a new datapoint to the series
def update_datapoint(id, state):
  # Grab the global variable
  global datapoints
  # Grab the time, note that this is the time of processing not the time
  # the message came in. This should be close enough.
  timestamp = int(time.time())
  new_point = datapoint(id, timestamp, state)
  # Try adding the datapoint to the series
  try:
    datapoints[id].append(new_point)
  except KeyError:
    # This must be the first datapoint for the sensor, create an array first
    datapoints[id] = [];
    datapoints[id].append(new_point)

# Start the update thread.
update_var()
  
print ("Starting Client...")
host    = "172.16.14.10"
timeout = 10

print ("Connecting...")
try:
    session = telnetlib.Telnet(host, 23, timeout)
except socket.timeout:
    print ("socket timeout")
else:
    print ("Connected...waiting for login")
    time.sleep(30)
    print ("Sending login.")
    session.write("root\r")
    time.sleep(1)
    print ("Sending password.")
    session.write("tini\r")
    time.sleep(1)
    print ("Sending bbq command.")
    session.write("bbq -temps\r")
    print ("Reading data.")

    while True:
      line = session.read_until("\n",timeout);
      if len(line.strip()) != 0:
# Uncomment to debug
#        print(str(len(line))+" "+str(int(time.time()))+" "+line);
        # I have no idea why these don't work when they work above...
        if line.endswith("login: "):
          print ("Sending login.")
          try:
            session.write("root\r")
          except:
            print ("Couldn't login")
        if line.endswith("password: "):
          print ("Sending password.")
          session.write("tini\r")
        if line.endswith("/> "):
          print ("Starting BBQ.")
          session.write("bbq -temps\r")

        line_arr = line.rsplit(' ', 1)
        last_word = line_arr[-1].strip()
        if (last_word.startswith("blwr:")):
          # The very first messaure starts with the prompt, so we need to grab
          # the last word after the split by colon
          sensorid = line_arr[0].split(':', 1)[0].rsplit(' ',1)[-1]
          status = last_word.split(':', 1)[1]
          if len(sensorid) == 16:
            update_datapoint(sensorid,status)
