#!/bin/sh
set -eu

if [ -z "${PORT:-}" ]; then
    echo "PORT must be provided by Railway." >&2
    exit 1
fi

case "$PORT" in
    *[!0-9]*)
        echo "PORT must be a valid TCP port." >&2
        exit 1
        ;;
esac

if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo "PORT must be between 1 and 65535." >&2
    exit 1
fi

if [ "${APP_ENV:-}" != "production" ]; then
    echo "APP_ENV must be set to production." >&2
    exit 1
fi

case "${APP_DEBUG:-true}" in
    false|False|FALSE|0) ;;
    *)
        echo "APP_DEBUG must be set to false." >&2
        exit 1
        ;;
esac

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be configured in Railway Variables." >&2
    exit 1
fi

case "${APP_URL:-}" in
    https://*) ;;
    *)
        echo "APP_URL must be set to the public HTTPS URL." >&2
        exit 1
        ;;
esac

if [ "${LOG_CHANNEL:-}" != "stderr" ]; then
    echo "LOG_CHANNEL must be set to stderr." >&2
    exit 1
fi

case "${SESSION_SECURE_COOKIE:-false}" in
    true|True|TRUE|1) ;;
    *)
        echo "SESSION_SECURE_COOKIE must be enabled in production." >&2
        exit 1
        ;;
esac

if [ "${MAIL_MAILER:-}" != "smtp" ]; then
    echo "MAIL_MAILER must be set to smtp." >&2
    exit 1
fi

if [ "${DB_CONNECTION:-}" != "mysql" ]; then
    echo "DB_CONNECTION must be set to mysql." >&2
    exit 1
fi

if [ -z "${DB_URL:-}" ]; then
    for variable in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
        eval "value=\${$variable:-}"
        if [ -z "$value" ]; then
            echo "$variable must be configured in Railway Variables." >&2
            exit 1
        fi
    done
fi

for variable in MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS; do
    eval "value=\${$variable:-}"
    if [ -z "$value" ]; then
        echo "$variable must be configured in Railway Variables." >&2
        exit 1
    fi
done

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data bootstrap/cache storage/framework storage/logs storage/app/private
chown www-data:www-data storage/app/public

if [ ! -L public/storage ]; then
    if [ -e public/storage ]; then
        echo "public/storage exists but is not a symbolic link." >&2
        exit 1
    fi

    php artisan storage:link
fi

php artisan config:cache
php artisan view:cache
if ! php artisan db:show >/dev/null; then
    echo "The configured MySQL connection is unavailable." >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
