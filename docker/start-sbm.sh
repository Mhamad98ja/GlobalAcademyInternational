#!/bin/sh

set -eu

PORT="${PORT:-10000}"

# Render's generated secrets are Base64 values. Laravel expects its encryption
# key to be prefixed with "base64:" so that it is decoded to 32 bytes.
if [ -n "${SBM_APP_KEY:-}" ]; then
    export APP_KEY="base64:${SBM_APP_KEY}"
fi

sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force --seed
php artisan config:cache

exec apache2-foreground
