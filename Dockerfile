FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl opcache pdo_mysql zip \
    && a2enmod headers rewrite \
    && find /etc/apache2/mods-enabled -maxdepth 1 \
        \( -type f -o -type l \) \
        \( -name 'mpm_*.load' -o -name 'mpm_*.conf' \) -delete \
    && a2enmod mpm_prefork \
    && enabled_mpms="$(find /etc/apache2/mods-enabled -maxdepth 1 \
        \( -type f -o -type l \) -name 'mpm_*.load' -printf '%f\n')" \
    && echo "Enabled Apache MPM: $enabled_mpms" \
    && [ "$enabled_mpms" = "mpm_prefork.load" ] \
    && active_mpm_loads="$(grep -RInE '^[[:space:]]*LoadModule[[:space:]]+mpm_' \
        /etc/apache2/mods-enabled \
        /etc/apache2/conf-enabled \
        /etc/apache2/sites-enabled \
        /etc/apache2/apache2.conf \
        /etc/apache2/ports.conf)" \
    && printf 'Active Apache MPM LoadModule declarations:\n%s\n' "$active_mpm_loads" \
    && active_mpm_count="$(printf '%s\n' "$active_mpm_loads" | wc -l)" \
    && if [ "$active_mpm_count" -ne 1 ] || ! printf '%s\n' "$active_mpm_loads" | grep -q 'LoadModule mpm_prefork_module'; then \
        echo "Expected exactly one active LoadModule declaration for mpm_prefork_module; found $active_mpm_count MPM declarations." >&2; \
        exit 1; \
    fi \
    && apache2ctl -t \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
WORKDIR /var/www/html

COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && php artisan storage:link \
    && chown -R www-data:www-data bootstrap/cache storage

COPY docker/railway-entrypoint.sh /usr/local/bin/railway-entrypoint
RUN chmod 755 /usr/local/bin/railway-entrypoint

EXPOSE 8080
CMD ["/usr/local/bin/railway-entrypoint"]
