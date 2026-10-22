FROM php:8.3-fpm-alpine

RUN apk add --no-cache git unzip postgresql-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pcntl bcmath zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
