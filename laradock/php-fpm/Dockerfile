ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm

# Ekstensi yang benar-benar dipakai CMS:
#   gd    - pemrosesan gambar unggahan
#   zip   - unggahan dokumen .zip dan paket Composer
#   intl  - pemformatan tanggal/angka lokal Indonesia
#   exif  - validasi berkas gambar oleh Laravel
#   redis - driver cache dan session
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd zip intl exif pdo_mysql bcmath opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Batas unggahan disamakan dengan client_max_body_size di nginx; kalau berbeda,
# berkas besar ditolak dengan galat yang tidak menjelaskan sebabnya.
RUN { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=512M'; \
        echo 'max_execution_time=300'; \
    } > /usr/local/etc/php/conf.d/cms.ini

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]
