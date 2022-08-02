FROM composer:latest AS composer

FROM php:8.1-fpm

ARG TIMEZONE

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    libzip-dev \
    unzip

RUN docker-php-ext-configure pcntl --enable-pcntl \
  && docker-php-ext-install \
    pcntl \
    zip

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/cron

CMD ["php-fpm"]
