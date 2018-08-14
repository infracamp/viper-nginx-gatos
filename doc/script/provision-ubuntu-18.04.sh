#!/bin/bash

set -e
apt-get -y update && apt install -y docker.io curl git vim

service docker restart

docker swarm init

docker network create --driver overlay --attachable gatos-net

curl -o /root/cluster-conf.env ""
