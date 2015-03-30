# stoker
The stoker_mon controller, a web-based UI for viewing and controlling the 
Stoker Power Draft Controller

As a warning, this is currently deemed to be pre-alpha code. It works on my
system, but has known issues and feature gaps listed at the top of the files.
Use at your own risk.

INSTRUCTIONS:
* Move stoker_pull to someplace safe, I use /var/lib/stoker/
* Create /var/lib/stoker/cooks and run:
    chown www-data:www-data /var/lib/stoker/cooks
* Edit stoker.ini to include your Google+ client ID, your user ID, and the 
relevant directories.
* Copy the pi directory to your Raspberry Pi if you use that as a 
controller/firewall. Delete this directory if you don't need it.
* Copy the rest of the files to /var/www/stoker

See https://www.ebower.com/wiki/Stoker_mon for additional details.
