# Build frontend assets.
FROM node:22-alpine AS frontend-build

WORKDIR /app

COPY package.json ./
RUN npm install --ignore-scripts --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build


# Install production PHP dependencies.
FROM composer:2 AS composer-build

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer validate --no-check-publish \
    && composer install \
       --no-dev \
       --no-interaction \
       --no-progress \
       --prefer-dist \
       --optimize-autoloader \
       --no-scripts

COPY . .
RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --no-interaction


# PHP-FPM runtime.
FROM php:8.4-fpm-bookworm AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libpq-dev \
       libzip-dev \
       unzip \
    && docker-php-ext-install pdo_pgsql opcache \
    && apt-get purge -y --auto-remove libpq-dev libzip-dev unzip \
    && apt-get install -y --no-install-recommends libpq5 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer-build --chown=www-data:www-data /app /var/www/html
COPY --from=frontend-build --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN mkdir -p \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
