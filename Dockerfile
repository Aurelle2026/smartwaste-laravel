# syntax=docker/dockerfile:1.7
# ---------- Stage 1 : build unifié (PHP + Node) ----------
FROM php:8.4-fpm-alpine AS build

RUN apk add --no-cache \
        bash git curl \
        nodejs npm \
        icu-dev libzip-dev oniguruma-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql bcmath intl opcache mbstring zip gd

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

RUN npm run build

# ---------- Stage 2 : runtime PHP-FPM ----------
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        bash \
        icu-dev libzip-dev oniguruma-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        postgresql-dev postgresql-client \
        curl git tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql bcmath intl opcache mbstring zip gd exif pcntl \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=build /app /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
