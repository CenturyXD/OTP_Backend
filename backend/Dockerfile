# ใช้ Official PHP image พร้อม FPM และ Alpine Linux
FROM php:8.2-fpm-alpine

# ติดตั้ง dependencies ที่จำเป็น
RUN apk add --no-cache \
    bash \
    build-base \
    libzip-dev \
    zip \
    postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# ติดตั้ง Composer (PHP package manager)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ตั้งค่า working directory
WORKDIR /var/www/html

# Copy โค้ด Laravel
COPY . .

# ติดตั้ง dependencies ของ Laravel
RUN composer install --no-interaction --no-plugins --no-scripts --prefer-dist

# เปลี่ยน owner ของไฟล์ให้เป็น user ของ web server
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure framework cache/view/session directories exist during image build.
RUN mkdir -p /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views

# Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN apk add --no-cache imap-dev \
    && docker-php-ext-configure imap --with-imap-ssl \
    && docker-php-ext-install imap

# Increase upload limits for image file uploads.
RUN printf "file_uploads=On\nupload_max_filesize=10M\npost_max_size=12M\nmemory_limit=256M\nmax_file_uploads=20\n" > /usr/local/etc/php/conf.d/uploads.ini

# Expose port 8000
EXPOSE 8000

# Start Laravel server (for development only)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]


