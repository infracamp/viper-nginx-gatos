#!/bin/bash

curl -o - -X POST --data-binary @rudl-provision.yml "http://localhost:4000/deploy/path/to/your_service?key=secret_deploy_key"

