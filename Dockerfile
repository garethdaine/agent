# Stage 1: Composer dependencies
FROM composer:2.8 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Stage 2: Node build
FROM node:22-alpine AS node-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources/ resources/
COPY vite.config.* tsconfig*.json postcss.config.* tailwind.config.* ./
RUN npm run build

# Stage 3: Production image
FROM php:8.3-fpm-alpine AS production

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        linux-headers \
        supervisor \
        nginx \
    && docker-php-ext-install \
        pdo_pgsql \
        zip \
        intl \
        pcntl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-app.ini" 2>/dev/null || true

# Create non-root user
RUN addgroup -g 1000 appuser && adduser -u 1000 -G appuser -s /bin/sh -D appuser

WORKDIR /app

# Copy application
COPY --chown=appuser:appuser . .
COPY --from=composer-deps --chown=appuser:appuser /app/vendor ./vendor
COPY --from=node-build --chown=appuser:appuser /app/public/build ./public/build

# Post-install optimizations
RUN php artisan config:cache 2>/dev/null || true \
    && php artisan route:cache 2>/dev/null || true \
    && php artisan view:cache 2>/dev/null || true \
    && php artisan event:cache 2>/dev/null || true

# Create required directories
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R appuser:appuser storage bootstrap/cache

# Supervisor configuration for multiple entrypoints
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf 2>/dev/null || true

# Nginx configuration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf 2>/dev/null || true

USER appuser

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php artisan about --json > /dev/null 2>&1 || exit 1

# Default entrypoint: web server via PHP-FPM
CMD ["php-fpm"]
