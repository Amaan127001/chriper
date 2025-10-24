# ---------- STAGE 1: composer build (install PHP deps) ----------
FROM composer:2 AS composer
WORKDIR /app

# copy composer files separately for better cache
COPY composer.json composer.lock /app/
RUN composer global require hirak/prestissimo || true
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# copy full php source for vendor (we'll copy /vendor to final)
COPY . /app
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ---------- STAGE 2: node build (build frontend assets) ----------
FROM node:20-bullseye AS node_builder
WORKDIR /app

# copy package files and install node deps
COPY package.json package-lock.json* /app/
# If you use yarn, adapt this section
RUN npm ci --legacy-peer-deps || npm install --legacy-peer-deps

# copy source and run build (adjust npm build script if you use mix/vite)
COPY . /app
RUN if [ -f package.json ]; then npm run build || npm run prod || true; fi

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

# copy application source
COPY --from=composer /app /var/www/html

# copy node build assets (if produced) into public (adjust as needed)
COPY --from=node_builder /app/public /var/www/html/public

# copy nginx config
COPY ./docker/nginx/default.conf /etc/nginx/sites-available/default

# set permissions (adjust user/group if needed)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# expose port 80
EXPOSE 80

# copy entrypoint and make executable
COPY ./scripts/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# final command: run entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
