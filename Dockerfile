FROM node:22-bookworm-slim AS node

FROM php:8.5-fpm-bookworm AS base

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        unzip \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install -j2 \
        bcmath \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . .

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

RUN npm ci \
    && npm run build \
    && rm -rf node_modules

FROM base AS production

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html

RUN mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

FROM nginx:stable-alpine AS web

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public
