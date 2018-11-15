#!/bin/bash

if [[ $(sudo docker node ls | grep Down) != "" ]];
then
    sudo docker node rm  $(sudo docker node ls | grep Down| awk '{print $1}');
fi
