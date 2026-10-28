# -------------------------------------------------------------
# 1. Build frontend assets
# -------------------------------------------------------------
FROM node:22-alpine AS frontend-build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then \
        npm ci --ignore-scripts --no-audit --no-fund; \
    else \
        npm install --ignore-scripts --no-audit --no-fund; \
    fi

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build


# -------------------------------------------------------------
# 2. Common PHP base with required extensions
# -------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pdo_sqlite \
        opcache \
        sockets \
        pcntl \
        zip \
    && apt-get purge -y --auto-remove libpq-dev libsqlite3-dev libzip-dev \
    && apt-get install -y --no-install-recommends libpq5 libzip4 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html


# -------------------------------------------------------------
# 3. Install production PHP dependencies
# Use the same PHP version and extensions as the runtime.
# -------------------------------------------------------------
FROM php-base AS composer-build

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN php -m | grep -ix sockets \
    && composer validate --no-check-publish \
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
    --no-interaction \
    && php artisan package:discover --ansi


# -------------------------------------------------------------
# 4. PHP-FPM runtime
# -------------------------------------------------------------
FROM php-base AS runtime

WORKDIR /var/www/html

COPY --from=composer-build --chown=www-data:www-data /app /var/www/html

COPY --from=frontend-build --chown=www-data:www-data \
    /app/public/ /var/www/html/public/

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]