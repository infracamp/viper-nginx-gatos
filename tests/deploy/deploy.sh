#!/bin/bash

set -e

echo "deploying service..."
curl  -X POST --data-binary @.kick.yml --header "X-Deploy-Image: $CI_REGISTRY_IMAGE" "http://localhost:4000/deploy/path/to/your_service?key=secret_deploy_key"

echo "[OK]";
