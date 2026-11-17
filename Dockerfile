# syntax=docker/dockerfile:1

###############################################################################
# Stage 1: install production PHP dependencies
###############################################################################
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

###############################################################################
# Stage 2: PHP-FPM + Nginx + Supervisor runtime (single container)
###############################################################################
FROM php:8.4-fpm-bookworm AS app

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        curl \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Application code + production dependencies
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor

# Laravel runtime directories
RUN mkdir -p storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Runtime configuration
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-rawijli.ini
COPY docker/prod/nginx.conf /etc/nginx/sites-available/default
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/rawijli.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
