# syntax=docker/dockerfile:1.7
# ---------- Stage 1 : build des assets front (Vite + React) ----------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json components.json ./

RUN npm run build

# ---------- Stage 2 : dépendances PHP via Composer ----------
FROM composer:2.9 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY database ./database
COPY artisan ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------- Stage 3 : image finale PHP-FPM ----------
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        bash \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        postgresql-dev \
        postgresql-client \
        curl \
        git \
        supervisor \
        tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        opcache \
        mbstring \
        zip \
        gd \
        exif \
        pcntl \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
