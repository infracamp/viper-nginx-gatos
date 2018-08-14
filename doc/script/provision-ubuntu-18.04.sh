#!/bin/bash

set -e

## Install docker and curl
apt-get -y update && apt install -y docker.io curl git vim

## Start docker daemon
service docker restart

## Init the swarm
docker swarm init

## Create the gatos-net default network
docker network create --driver overlay --attachable gatos-net

## Copy default cluster config
curl -o /root/cluster-conf.env "https://raw.githubusercontent.com/infracamp/viper-nginx-gatos/master/doc/script/config-dist.env"


echo "Please edit /root/cluster-conf.env to your needs"
exit;

## Run this manually after you edited cluster-conf.env

docker pull infracamp/viper-nginx-gatos:testing

docker run -d --env-file /root/cluster-conf.env -p 80:80 -p 443:443 --net host --network gatos-net -v /var/run/docker.sock:/var/run/docker.sock --name cloudfront infracamp/viper-nginx-gatos:testing






