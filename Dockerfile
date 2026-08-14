FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY storage ./storage
COPY artisan ./artisan
RUN composer dump-autoload --no-dev --optimize

FROM php:8.2-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libpq-dev libzip-dev \
    && docker-php-ext-install mbstring pdo_pgsql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=dependencies /app ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/start-sbm.sh /usr/local/bin/start-sbm

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/start-sbm

EXPOSE 10000

CMD ["/usr/local/bin/start-sbm"]
