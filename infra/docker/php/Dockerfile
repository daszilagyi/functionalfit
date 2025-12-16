# PHP-FPM Dockerfile for FunctionalFit Laravel Application
# Multi-stage build for optimized production images

ARG PHP_VERSION=8.3

# ============================================================================
# Base Stage - Common dependencies and configuration
# ============================================================================
FROM php:${PHP_VERSION}-fpm-alpine AS base

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    # Core utilities
    bash \
    curl \
    wget \
    git \
    unzip \
    # Image processing (for potential future features)
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    # Database extensions
    libpq-dev \
    # Compression
    libzip-dev \
    # ICU (internationalization)
    icu-dev \
    # Process control
    linux-headers \
    # Health check utilities
    fcgi

# Install PHP extensions required by Laravel 11
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    gd \
    zip \
    intl \
    opcache \
    pcntl \
    bcmath \
    exif

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Create application user and group
RUN addgroup -g 1000 www && \
    adduser -D -u 1000 -G www www

# Copy PHP configuration
COPY --chown=www:www . /var/www/html

# Set proper permissions
RUN chown -R www:www /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# ============================================================================
# Development Stage - With debugging tools
# ============================================================================
FROM base AS development

# Install Xdebug for development
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install xdebug-3.3.1 \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

# Copy Xdebug configuration
RUN echo "xdebug.mode=develop,debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer dependencies (with dev dependencies)
USER www
RUN composer install --prefer-dist --no-interaction --no-progress --optimize-autoloader

USER root

# Expose PHP-FPM port
EXPOSE 9000

# Health check script
COPY --chown=www:www infra/docker/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# Start PHP-FPM
CMD ["php-fpm"]

# ============================================================================
# Production Stage - Optimized for production
# ============================================================================
FROM base AS production

# Copy application files
COPY --chown=www:www . /var/www/html

# Install Composer dependencies (no dev dependencies)
USER www
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --classmap-authoritative

# Optimize Laravel for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

USER root

# Remove Composer after installation
RUN rm -rf /usr/bin/composer

# Set environment to production
ENV APP_ENV=production
ENV APP_DEBUG=false

# Expose PHP-FPM port
EXPOSE 9000

# Health check script
COPY --chown=www:www infra/docker/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# Start PHP-FPM
CMD ["php-fpm"]
