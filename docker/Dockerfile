# SPDX-License-Identifier: Apache-2.0
# odac.tune.audio: PHP-FPM image. nginx runs in front (docker/nginx.conf).

FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json vite.config.js ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache sqlite-libs \
 && docker-php-ext-install opcache
WORKDIR /var/www/odac
COPY --from=vendor /build ./
COPY --from=assets /build/public/build ./public/build
RUN rm -rf tests node_modules \
 && mkdir -p storage/app/odac/artifacts storage/framework/cache storage/framework/views storage/logs database/data \
 && chown -R www-data:www-data storage database bootstrap/cache
USER www-data
# Artifacts and the SQLite base live in volumes: never lost on a redeploy.
VOLUME ["/var/www/odac/storage", "/var/www/odac/database/data"]
CMD ["php-fpm"]

# nginx with the public files of THIS build (no shared volume to go stale).
FROM nginx:1.27-alpine AS web
COPY --from=vendor /build/public /var/www/odac/public
COPY --from=assets /build/public/build /var/www/odac/public/build
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
