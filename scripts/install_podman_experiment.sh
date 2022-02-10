#!/usr/bin/env bash

clear
ARG1=$1
WHO=$(whoami)
APP_ID=$(cat /proc/sys/kernel/random/uuid)
RANDOM_SECRET=$(echo $(($(date +%s%N) / 1000000)) | sha256sum | base64 | head -c 32)
SENTRY_DSN="https://9e7a74326f29422584d2d0bebdc8b7d3@o1082494.ingest.sentry.io/6091062"

UBUNTU_MAJOR_MIN=20
UBUNTU_MINOR_MIN=04
OS_OK="nok"

set -eou pipefail

if [ $ARG1 ] && [ $ARG1 == "-d" ]; then
    set -x
fi

function errorchecker() {
    exitCode=$?
    if [ $exitCode -ne "0" ]; then
        echo "$0 exited unexpectedly with status: $exitCode"
        exit $exitCode
    fi
}
trap 'errorchecker' EXIT

if [ $WHO != 'root' ]; then
    echo 'Run as root please: sudo sh -c "$(curl -fsSL https://get.coollabs.io/coolify/install.sh)"'
    exit 1
fi

. /etc/lsb-release
if [ $DISTRIB_ID != 'Ubuntu' ]; then
    echo 'Not supported OS, please open an issue on Github to get supported version.'
    exit 1
fi

DISTRIB_RELEASE_MAJOR=$(echo "$DISTRIB_RELEASE" | cut -d'.' -f 1)
DISTRIB_RELEASE_MINOR=$(echo "$DISTRIB_RELEASE" | cut -d'.' -f 2)

if [ "$DISTRIB_RELEASE_MAJOR" -ge "$UBUNTU_MAJOR_MIN" ] &&
    [ "$DISTRIB_RELEASE_MINOR" -ge "$UBUNTU_MINOR_MIN" ]; then
    OS_OK="ok"
fi

if [ $OS_OK == 'nok' ]; then
    echo "Ubuntu version less than $UBUNTU_MAJOR_MIN.$UBUNTU_MINOR_MIN."
    exit 1
fi

function installPodman() {
    apt-get update -y
    apt-get install curl wget gnupg2 -y
    if [ "$DISTRIB_RELEASE_MAJOR" -eq "20" ] && [ "$DISTRIB_RELEASE_MINOR" -eq "04" ]; then
        echo 'Installing on 20.04'
        source /etc/os-release
        sh -c "echo 'deb http://download.opensuse.org/repositories/devel:/kubic:/libcontainers:/stable/xUbuntu_${VERSION_ID}/ /' > /etc/apt/sources.list.d/devel:kubic:libcontainers:stable.list"
        wget -nv https://download.opensuse.org/repositories/devel:kubic:libcontainers:stable/xUbuntu_${VERSION_ID}/Release.key -O- | apt-key add -
        apt-get update -y
        apt-get -y install podman
        return 0
    elif [ "$DISTRIB_RELEASE_MAJOR" -eq "20" ] && [ "$DISTRIB_RELEASE_MINOR" -eq "10" ]; then
        apt-get -y install podman
        return 0
    elif [ "$DISTRIB_RELEASE_MAJOR" -gt "20" ]; then
        apt-get -y install podman
        return 0
    else
        exit 1
    fi

}

if [ ! -x "$(command -v podman)" ]; then
    while true; do
        read -p "Podman not found, should I install it automatically? [Yy/Nn] " yn
        case $yn in
        [Yy]*)
            installPodman
            break
            ;;
        [Nn]*)
            echo "Please install docker manually and update it to the latest, but at least to $DOCKER_MAJOR.$DOCKER_MINOR"
            exit 0
            ;;
        *) echo "Please answer Yy or Nn." ;;
        esac
    done
fi

# Making base directory for coolify
if [ ! -d coolify ]; then
    mkdir coolify
fi

echo "COOLIFY_APP_ID=$APP_ID
COOLIFY_SECRET_KEY=$RANDOM_SECRET
COOLIFY_DATABASE_URL=file:../db/prod.db
COOLIFY_SENTRY_DSN=$SENTRY_DSN
COOLIFY_HOSTED_ON=docker" >coolify/.env

systemctl start podman.socket
systemctl enable podman.socket

podman volume create coolify-db
podman volume create coolify-ssl-certs 
podman volume create coolify-letsencrypt


cd coolify && podman run --privileged -tid --env-file .env -v /var/run/podman/podman.sock:/var/run/podman/podman.sock -v coolify-db-sqlite:/app/db docker.io/coollabsio/coolify:latest /bin/sh -c "env | grep COOLIFY > .env && docker-compose up -d --force-recreate"
echo "Done"
exit 0
