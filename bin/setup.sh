#!/bin/bash
# useradd1.sh - A simple shell script to display the form dialog on screen
# set field names i.e. shell variables
shell=""
groups=""
user=""
home=""

# open fd
exec 3>&1

# Store data to $VALUES variable
VALUES=$(dialog --ok-label "Save and restart" \
	  --backtitle "infracamp's rudl-manager" \
	  --title "rudl manager" \
	  --form "Welcome.\n\nWe need two options to know before we can setup your cluster." \
15 80 0 \
	"Hostname:" 1 1	"$user" 	1 10 120 0 \
	"Repo-URL:" 2 1	"$shell"  	2 10 120 0 \
2>&1 1>&3)

# close fd
exec 3>&-

# display values just entered
echo "$VALUES"
