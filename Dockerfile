# Circulation Dashboard - Production Docker Image
FROM php:8.2-apache

LABEL maintainer="circulation-dashboard"
LABEL description="Circulation Dashboard with PHP 8.2, Apache, and all required extensions"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www

# Copy migration files and config
COPY db/migrations/ /var/www/migrations/
COPY phinx.php /var/www/phinx.php
COPY composer.json composer.lock* /var/www/

# Install Phinx and dependencies
RUN composer install --no-dev --optimize-autoloader || \
    composer require robmorgan/phinx --no-dev --optimize-autoloader

# Copy application files
COPY web/ /var/www/html/

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Use custom entrypoint that runs migrations before starting Apache
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
