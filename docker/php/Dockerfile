ARG PHP_VERSION=8.5

# Extensions and PHP-FPM configuration shared by development and production.
FROM php:${PHP_VERSION}-fpm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS libzip-dev libzip5 unzip librabbitmq-dev librabbitmq4 libfcgi-bin \
    && docker-php-ext-install bcmath pdo_mysql zip \
    && pecl install amqp \
    && pecl install redis \
    && docker-php-ext-enable amqp redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS libzip-dev librabbitmq-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/hardening.ini /usr/local/etc/php/conf.d/90-hardening.ini

WORKDIR /app/backend

# Development and tests: the repository is mounted at /app. Xdebug is available for
# debugging, and PCOV measures test coverage, much faster than Xdebug can.
FROM base AS dev

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && pecl install xdebug pcov \
    && docker-php-ext-enable xdebug pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
COPY docker/php/pcov.ini /usr/local/etc/php/conf.d/99-pcov.ini

# Production: the backend code and its runtime dependencies are part of the image.
FROM base AS prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/99-opcache.ini

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-autoloader --prefer-dist

COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative
