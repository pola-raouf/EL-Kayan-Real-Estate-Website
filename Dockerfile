# 1️⃣ Base image
FROM php:8.2-fpm

# 2️⃣ Set working directory
WORKDIR /var/www/html

# 3️⃣ Install system dependencies & PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# 4️⃣ Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 5️⃣ Copy project files
COPY . .

# 6️⃣ Set permissions (Linux)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 7️⃣ Composer install
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction

# 8️⃣ Generate application key
RUN php artisan key:generate

# 9️⃣ Clear caches
RUN php artisan config:clear \
    && php artisan cache:clear \
    && php artisan route:clear \
    && php artisan view:clear

# 10️⃣ Expose port 9000 and start PHP-FPM
EXPOSE 9000
CMD ["php-fpm"]
