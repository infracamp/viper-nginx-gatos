#!/bin/bash

set -e

CI_REGISTRY_IMAGE=registry.gitlab.com/talpasolutions/webservice/cockpit
SECRET_KEY=secret_deploy_key

echo "deploying service..."
curl  --fail -X POST --data-binary @.kick.yml "http://localhost:4000/deploy/$CI_REGISTRY_IMAGE?secret=$SECRET_KEY"

echo "[OK]";
