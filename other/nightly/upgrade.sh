#!/bin/bash
## Do not modify this file. You will lose the ability to autoupdate!

VERSION="1.1"
CDN="https://cdn.coollabs.io/devlab-nightly"
LATEST_IMAGE=${1:-latest}
LATEST_HELPER_VERSION=${2:-latest}

curl -fsSL $CDN/docker-compose.yml -o /data/devlab/source/docker-compose.yml
curl -fsSL $CDN/docker-compose.prod.yml -o /data/devlab/source/docker-compose.prod.yml
curl -fsSL $CDN/.env.production -o /data/devlab/source/.env.production

# Merge .env and .env.production. New values will be added to .env
awk -F '=' '!seen[$1]++' /data/devlab/source/.env /data/devlab/source/.env.production  > /data/devlab/source/.env.tmp && mv /data/devlab/source/.env.tmp /data/devlab/source/.env

# Check if PUSHER_APP_ID or PUSHER_APP_KEY or PUSHER_APP_SECRET is empty in /data/devlab/source/.env
if grep -q "PUSHER_APP_ID=$" /data/devlab/source/.env; then
    sed -i "s|PUSHER_APP_ID=.*|PUSHER_APP_ID=$(openssl rand -hex 32)|g" /data/devlab/source/.env
fi

if grep -q "PUSHER_APP_KEY=$" /data/devlab/source/.env; then
    sed -i "s|PUSHER_APP_KEY=.*|PUSHER_APP_KEY=$(openssl rand -hex 32)|g" /data/devlab/source/.env
fi

if grep -q "PUSHER_APP_SECRET=$" /data/devlab/source/.env; then
    sed -i "s|PUSHER_APP_SECRET=.*|PUSHER_APP_SECRET=$(openssl rand -hex 32)|g" /data/devlab/source/.env
fi

# Make sure devlab network exists
# It is created when starting Devlab with docker compose
docker network create --attachable devlab 2>/dev/null
# docker network create --attachable --driver=overlay devlab-overlay 2>/dev/null

if [ -f /data/devlab/source/docker-compose.custom.yml ]; then
    echo "docker-compose.custom.yml detected."
    docker run -v /data/devlab/source:/data/devlab/source -v /var/run/docker.sock:/var/run/docker.sock --rm ghcr.io/coollabsio/coolify-helper:${LATEST_HELPER_VERSION:-latest} bash -c "LATEST_IMAGE=${1:-} docker compose --env-file /data/devlab/source/.env -f /data/devlab/source/docker-compose.yml -f /data/devlab/source/docker-compose.prod.yml -f /data/devlab/source/docker-compose.custom.yml up -d --remove-orphans --force-recreate --wait --wait-timeout 60"
else
    docker run -v /data/devlab/source:/data/devlab/source -v /var/run/docker.sock:/var/run/docker.sock --rm ghcr.io/coollabsio/coolify-helper:${LATEST_HELPER_VERSION:-latest} bash -c "LATEST_IMAGE=${1:-} docker compose --env-file /data/devlab/source/.env -f /data/devlab/source/docker-compose.yml -f /data/devlab/source/docker-compose.prod.yml up -d --remove-orphans --force-recreate --wait --wait-timeout 60"
fi
