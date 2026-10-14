# ==============================================================================
# NetFusion Framework — Production Dockerfile (PHP 8.2-FPM Alpine)
# Author: Md. Atiquzzaman Ashish (DotNetBD)
# Website: www.dotnetbd.com
# ==============================================================================

FROM php:8.2-fpm-alpine AS base

# Install system runtime & build dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    sqlite-dev \
    linux-headers \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        mbstring \
        bcmath \
        gd \
        zip \
        opcache \
        pcntl

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Copy configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html

# Install Composer dependencies with optimized classmap
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Ensure proper file permissions for web server and background workers
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/public \
    && chmod -R 775 /var/www/html/storage

# Switch to non-root user
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
