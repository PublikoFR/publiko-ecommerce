FROM php:8.3-apache

ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        ca-certificates \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libwebp-dev \
        libxml2-dev \
        libonig-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        libexif-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        zip \
        pdo_mysql \
        mysqli \
        exif \
        opcache \
        mbstring \
        xml \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN groupadd -g ${GID} sail 2>/dev/null || true \
    && useradd -u ${UID} -g ${GID} -ms /bin/bash sail 2>/dev/null || true \
    && mkdir -p /tmp/composer \
    && chown -R ${UID}:${GID} /tmp/composer /var/www

ENV APACHE_RUN_USER=sail
ENV APACHE_RUN_GROUP=sail

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
