#!/usr/bin/env bash
set -e

echo "Entrypoint: starting..."

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# Generate app key if not set
if [ -z "${APP_KEY}" ]; then
  echo "APP_KEY not set — generating..."
  php artisan key:generate --force || true
fi

# Replace listen socket with TCP port 9000 in php-fpm pool config (works for php:8.x images)
# Safely update www.conf to listen on 127.0.0.1:9000
PHP_FPM_POOL="/usr/local/etc/php-fpm.d/www.conf"
if [ -f "${PHP_FPM_POOL}" ]; then
  echo "Configuring php-fpm to listen on 127.0.0.1:9000"
  sed -i 's/^listen = .*$/listen = 127.0.0.1:9000/' "${PHP_FPM_POOL}"
fi

# Cache and optimize in production
if [ "${APP_ENV}" = "production" ]; then
  echo "Caching config & routes..."
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

# Optionally run migrations
if [ "${RUN_MIGRATIONS}" = "true" ]; then
  echo "Running migrations..."
  php artisan migrate --force || echo "migrations failed (continuing)"
fi

# Start php-fpm (foreground) in background, then start nginx foreground
echo "Starting php-fpm..."
php-fpm --nodaemonize &

# Wait a moment for php-fpm to start listening
sleep 1

echo "Starting nginx..."
nginx -g 'daemon off;'
