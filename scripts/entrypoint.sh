#!/usr/bin/env bash
set -e

# allow override for debug
echo "Starting entrypoint..."

# ensure correct permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# generate app key if not set
if [ -z "${APP_KEY}" ]; then
  echo "APP_KEY not set — generating..."
  php artisan key:generate --force
fi

# clear & cache config for performance (only in production)
if [ "${APP_ENV}" = "production" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

# optionally run migrations on start (set RUN_MIGRATIONS=true in Render env)
if [ "${RUN_MIGRATIONS}" = "true" ]; then
  echo "Running migrations..."
  php artisan migrate --force || echo "migrate failed, continuing..."
fi

# start php-fpm and nginx (php-fpm in background then nginx in foreground)
echo "Starting php-fpm..."
php-fpm --nodaemonize &

echo "Starting nginx..."
nginx -g 'daemon off;'
