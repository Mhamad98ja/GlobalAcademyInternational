#!/bin/sh

set -eu

PORT="${PORT:-10000}"

# Existing Render services may provide the database connection as
# DATABASE_URL, while this Laravel project conventionally reads DB_URL.
# Normalize both names and never let a supplied Render PostgreSQL URL fall
# back to the image's read-only SQLite database.
if [ -z "${DB_URL:-}" ] && [ -n "${DATABASE_URL:-}" ]; then
    export DB_URL="${DATABASE_URL}"
fi

if [ -n "${DB_URL:-}" ]; then
    export DB_CONNECTION="pgsql"
elif [ "${APP_ENV:-production}" = "production" ]; then
    echo "ERROR: No Render PostgreSQL connection was supplied. Set DB_URL or DATABASE_URL from the Render database connection string." >&2
    exit 1
fi

# Render provides its canonical HTTPS URL at runtime. Always prefer it over
# an inherited APP_URL so Vite assets and form actions never downgrade to HTTP.
if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="$(printf '%s' "${RENDER_EXTERNAL_URL}" | sed 's|^http://|https://|')"
elif [ "${RENDER:-}" = "true" ] && [ -n "${APP_URL:-}" ]; then
    export APP_URL="$(printf '%s' "${APP_URL}" | sed 's|^http://|https://|')"
fi

# Render's generated secrets are Base64 values. Laravel expects its encryption
# key to be prefixed with "base64:" so that it is decoded to 32 bytes. The
# fallback keeps the application bootable while a Blueprint sync is pending.
if [ -n "${SBM_APP_KEY:-}" ]; then
    export APP_KEY="base64:${SBM_APP_KEY}"
elif [ -z "${APP_KEY:-}" ] || { [ "${APP_KEY#base64:}" = "${APP_KEY}" ] && [ "${#APP_KEY}" -ne 32 ]; }; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force --seed
php artisan config:cache

exec apache2-foreground
