#!/usr/bin/env bash
set -e

handle_error() {
    echo "Error on line $LINENO: Command '$BASH_COMMAND' failed with exit status $?." >&2
    exit 1
}

trap handle_error ERR

if [ ! -f /dev/shm/container-started ]; then
    cd /app

    if [ ! -f /usr/local/etc/php/php.ini ]; then
        /app/docker/gen-ini.sh > /usr/local/etc/php/php.ini
    fi

    # Ensure the structure of the storage directory
    while read -r d; do
        if [ ! -d "$d" ]; then
            echo "Missing directory: $d"
            mkdir -p "$d"
        fi
    done < /app/struct.txt

    # Make sure APP_KEY is set
    if [ ! -v APP_KEY ]; then
        DOT_ENV_APP_KEY=$(cat .env | grep "^APP_KEY=" | cut -d '=' -f 2-)
        APP_KEY_FILE=${APP_KEY_FILE:-/app/storage/app/key.txt}
        if [ -z "$DOT_ENV_APP_KEY" ]; then
            if [ -f "$APP_KEY_FILE" ]; then
                export APP_KEY=$(cat "$APP_KEY_FILE")
            else
                export APP_KEY=$(php /app/artisan key:generate --show)
                echo -n "$APP_KEY" > "$APP_KEY_FILE"
            fi
        fi
    fi

    chown -R www-data:www-data /app/storage

    if [ "${LARAVEL_NO_OPTIMIZE:-false}" != "true" ]; then
        /app/docker/as-web.sh php /app/artisan optimize
    fi

    echo -n 1 > /dev/shm/container-started
fi

# Execute the passed command or fall back to the default CMD
if [ $# -eq 0 ]; then
    exec /usr/local/bin/stacker
else
    exec "$@"
fi
