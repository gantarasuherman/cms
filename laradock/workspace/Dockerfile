ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

ARG NODE_VERSION=20

# Sama seperti php-fpm/Dockerfile, ditambah Node/npm dan Composer untuk
# pekerjaan sehari-hari: artisan, composer, npm run dev/build.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd zip intl exif pdo_mysql bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN echo 'memory_limit=-1' > /usr/local/etc/php/conf.d/workspace.ini

WORKDIR /var/www

CMD ["tail", "-f", "/dev/null"]
