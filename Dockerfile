# ============ Stage 1: build the Vite frontend assets with Node ==============
FROM node:22-alpine AS frontend

WORKDIR /app

# Node dependencies (only re-run when the lockfile changes; npm ci is
# deterministic — installs exactly what package-lock.json pins)
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Source files required by Vite/Tailwind (public/build is excluded from the
# build context via .dockerignore, so this always produces a fresh build)
COPY resources/ ./resources/
COPY vite.config.js tailwind.config.js postcss.config.js ./

RUN npm run build

# ============ Stage 2: PHP application image =================================
FROM php:8.3-cli

# 1. System dependencies + PHP extensions (including pdo_pgsql so the app
#    can be switched to Render PostgreSQL by setting DB_CONNECTION=pgsql)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libsqlite3-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        pdo_pgsql \
        intl \
        zip \
        gd \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. PHP production settings (opcache + upload limits)
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=10M'; \
    } > /usr/local/etc/php/conf.d/zz-prod.ini

# The `php artisan serve` dev server handles concurrency through this
ENV PHP_CLI_SERVER_WORKERS=8

# 3. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 4. PHP dependencies (only re-run when composer files change).
#    NOTE: fakerphp/faker is a REGULAR dependency (composer.json "require"),
#    not require-dev, because the entrypoint seeds demo data with Eloquent
#    factories on every boot — and Laravel's Factory always needs Faker.
#    Installing with --no-dev used to throw: Class "Faker\Factory" not found.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-progress \
        --no-scripts

# 5. Frontend assets built in the Node stage (no npm/node in the PHP image)
COPY --from=frontend /app/public/build ./public/build

# 6. Application source (vendor/node_modules/etc. excluded via .dockerignore)
COPY . .

# 7. Regenerate the Laravel package manifest
RUN php artisan package:discover --ansi

# 8. Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render Docker services default to port 10000; entrypoint also honours $PORT
EXPOSE 10000

# Create DB if needed, run migrations, seed only when fresh, then start the
# web server and the background queue worker (see docker/entrypoint.sh).
CMD ["/usr/local/bin/entrypoint.sh"]
