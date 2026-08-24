# Imagen del API Laravel (PHP-FPM). Nginx la expone en el servicio "nginx".
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash git unzip icu-dev libzip-dev libpng-dev freetype-dev libjpeg-turbo-dev \
        postgresql-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql gd zip intl bcmath opcache exif \
    && apk del --no-network git \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/backend/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/backend/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

# El código llega por bind mount; solo se prepara la estructura de storage.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
             storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
