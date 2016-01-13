#!/usr/bin/env bash

set -o errexit
set -o pipefail
set -o nounset

__dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
__file="${__dir}/$(basename "${BASH_SOURCE[0]}")"

# Populate configuration files, this is always necessary.
php ${__dir}/bootstrap.php

case $1 in
    '')
        echo "Usage: (convenience shortcuts)"
        echo "  ./entrypoint.sh demo        Populate database with demo user."
        echo "  ./entrypoint.sh worker      Execute worker."
        echo "  ./entrypoint.sh fpm         Execute php-fpm."
        echo ""
        echo "You can also pass other commands:"
        echo "  ./entrypoint.sh bash"
        echo "  ./entrypoint.sh uptime"
        echo "  ./entrypoint.sh ls -l /"
        exit 0
        ;;
    'demo')
        php -d memory_limit=-1 ${__dir}/../symfony tools:purge --demo
        exit 0
        ;;
    'worker')
        php -d memory_limit=-1 ${__dir}/../symfony jobs:worker
        exit 0
        ;;
    'fpm')
        trap 'kill -INT $PID' TERM INT
        php-fpm --nodaemonize --allow-to-run-as-root -c /atom/php.ini --fpm-config /atom/fpm.ini &
        PID=$!
        wait $PID
        trap - TERM INT
        wait $PID
        exit $?
        ;;
esac

exec "${@}"
