FROM php:8.3-fpm-alpine

# Установка системных пакетов и PHP-расширений
RUN apk add --no-cache \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql zip

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html