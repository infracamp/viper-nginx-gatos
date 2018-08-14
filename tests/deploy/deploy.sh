#!/bin/bash

echo "deploying service..."
curl --fail -X POST --data-binary @rudl-provision.yml "http://localhost:4000/deploy/path/to/your_service?key=secret_deploy_key"

echo "[OK]";
