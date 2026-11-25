# 1. Gunakan PHP 8.3 FPM berbasis Alpine Linux yang ringan
FROM php:8.3-fpm-alpine

# 2. Instal dependensi sistem Linux yang dibutuhkan ekstensi PHP & build aset
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    nodejs \
    npm

# 3. Kompilasi & aktifkan ekstensi PHP wajib untuk Laravel (MySQL, GD, Zip, Intl, BCMath, PCNTL)
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# 4. Instal ekstensi Redis untuk PHP via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# 5. Salin binary Composer resmi langsung dari image Composer terbaru
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 6. Tetapkan direktori kerja utama di dalam container
WORKDIR /var/www/html

# 7. Salin seluruh kode proyek ke container
COPY . .

# 8. Pastikan web server (www-data) punya izin tulis ke folder storage & cache Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 9. Ekspos port 9000 (port default komunikasi PHP-FPM, bukan port HTTP browser)
EXPOSE 9000

# 10. Jalankan service PHP-FPM di foreground
CMD ["php-fpm"]
