# VPN Panel — production image
# Build: docker build -t vpnpanel .
# Note: the panel needs host-level control over /etc/ppp, /etc/nginx and systemd;
# in production it is recommended to run directly on the VPN server (see
# deploy/ubuntu-24.04-install.sh). This image is provided for CI and demos.

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        nginx supervisor icu-dev libzip-dev sqlite-libs \
        oniguruma-dev libpng-dev zlib-dev curl bash tini \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath intl pdo pdo_mysql mbstring zip opcache gd \
    && ln -s /usr/bin/php84 /usr/bin/php || true

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN php artisan config:cache \
 && php artisan route:cache \
 && php artisan view:cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/supervisor-vpnpanel.conf /etc/supervisor/conf.d/vpnpanel.conf
COPY deploy/nginx-vpnpanel.conf /etc/nginx/nginx.conf

EXPOSE 80 8291-8999/tcp 8080-8999/tcp

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/vpnpanel.conf"]