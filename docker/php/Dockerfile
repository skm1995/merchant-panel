ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS libzip-dev libzip5 unzip librabbitmq-dev librabbitmq4 \
    && docker-php-ext-install bcmath pdo_mysql zip \
    && pecl install amqp \
    && pecl install redis \
    && pecl install xdebug \
    && docker-php-ext-enable amqp redis xdebug \
    && a2enmod rewrite \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libzip-dev librabbitmq-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

WORKDIR /var/www/html
