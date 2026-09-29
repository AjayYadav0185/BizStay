# ============ Stage 1: build the Vite frontend assets with Node ==============
FROM node:22-alpine AS frontend

WORKDIR /app

# package-lock.json may not exist in the repo (npm registry was unreachable
# when it was last generated locally). Prefer a deterministic `npm ci` when
# the lockfile is present, otherwise fall back to `npm install`.
COPY package.json ./
COPY package-lock.json* ./
RUN if [ -f package-lock.json ]; then \
        npm ci --no-audit --no-fund; \
    else \
        npm install --no-audit --no-fund; \
    fi

# Source files required by Vite/Tailwind (public/build is excluded from the
# build context via .dockerignore, so this always produces a fresh build).
# NOTE: this project keeps its Tailwind/PostCSS config inside vite.config.js
# + CSS (`@import "tailwindcss"`), so no tailwind.config.js / postcss.config.js
# files are copied here — they do not exist in the repo.
COPY resources/ ./resources/
COPY vite.config.js ./

RUN npm run build

# ============ Stage 2: PHP application image =================================
FROM php:8.3-cli

# 1. System dependencies + PHP extensions (pdo_sqlite for the default
#    SQLite-on-disk setup; pdo_pgsql kept so the app can later move to
#    Render PostgreSQL by setting DB_CONNECTION=pgsql + DB_* vars)
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
        pcntl \
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
#    DemoSeeder deliberately avoids Eloquent factories, so `--no-dev` vendor
#    (no fakerphp/faker) is safe for seeding on Render.
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

# 7. Rebuild the Laravel package manifest for --no-dev vendor.
#    Dev-only providers (laravel/pail, laravel/pao, collision, tinker) are
#    absent from the image, but their stale entries linger in the manifest
#    copied from the local repo — so wipe it first, then rediscover against
#    the packages that actually exist in the image.
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && php artisan package:discover --ansi

# 8. Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render Docker services default to port 10000; entrypoint also honours $PORT
EXPOSE 10000

# Create DB if needed, run migrations, seed only when fresh, then start the
# web server and the background queue worker (see docker/entrypoint.sh).
CMD ["/usr/local/bin/entrypoint.sh"]
