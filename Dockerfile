# ---------- STAGE 1: composer (install PHP deps) ----------
FROM composer:2 AS composer
WORKDIR /app

# Copy only composer files first (for caching), but install WITHOUT running scripts
COPY composer.json composer.lock /app/

# Prevent scripts that require artisan from running during this step
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy the rest of the application after dependencies installed
COPY . /app

# Now run post-install scripts (package discover) and regenerate autoloads
# This runs after the source (artisan, config, etc.) is present.
RUN composer dump-autoload --optimize && composer run-script post-autoload-dump || true

# ---------- STAGE 2: node build (build frontend assets) ----------
FROM node:20-bullseye AS node_builder
WORKDIR /app

COPY package.json package-lock.json* /app/
RUN npm ci --legacy-peer-deps || npm install --legacy-peer-deps
COPY . /app
RUN npm run build || npm run prod || true

# ---------- STAGE 3: runtime (php-fpm + nginx) ----------
FROM php:8.2-fpm-bullseye

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

# install system packages & PHP extensions
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    git \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# copy application (including vendor produced in composer stage)
COPY --from=composer /app /var/www/html

# copy node build assets (if produced) into public
COPY --from=node_builder /app/public /var/www/html/public

# copy nginx config
COPY ./docker/nginx/default.conf /etc/nginx/sites-available/default

# set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

# copy entrypoint and make executable
COPY ./scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
