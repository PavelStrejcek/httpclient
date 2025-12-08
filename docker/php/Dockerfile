# syntax=docker/dockerfile:1.7
# PHP 8.4 FPM for web application
FROM php:8.4-fpm-alpine

# Install system dependencies and PHP extensions
RUN set -eux; \
    apk add --no-cache \
        bash \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        zlib-dev \
        curl \
        git; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        opcache \
        intl \
        mbstring \
        zip

# Enable recommended opcache settings
RUN set -eux; \
    { \
      echo "opcache.enable=1"; \
      echo "opcache.memory_consumption=256"; \
      echo "opcache.max_accelerated_files=20000"; \
      echo "opcache.validate_timestamps=1"; \
      echo "opcache.revalidate_freq=0"; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Expose FPM port (internal)
EXPOSE 9000

# Use non-root user for better security in containers (optional)
# Uncomment the following lines if you want to run as www-data without sudo needs
# RUN addgroup -g 1000 www && adduser -D -G www -u 1000 www
# USER www
