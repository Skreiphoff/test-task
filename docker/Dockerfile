FROM php:8.3-fpm-alpine

ARG APP_UID=1000

RUN apk add --no-cache git unzip postgresql-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pcntl bcmath zip opcache \
    && adduser -D -u ${APP_UID} -h /home/app app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# php-fpm without a `user` directive keeps the master and workers under the current user.
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-rootless.conf

WORKDIR /var/www/html
USER app

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
