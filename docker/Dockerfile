FROM php:8.2-apache

LABEL maintainer="GED Documentaire"

# ── Dépendances système ───────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    libexif-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    poppler-utils \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ── Extensions PHP ────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        gd \
        exif \
    && docker-php-ext-enable exif

# ── Configuration Apache ──────────────────────────────────────────────────────
RUN a2enmod rewrite headers

# Virtual host — DocumentRoot pointe sur public/
COPY docker/apache-ged.conf /etc/apache2/sites-available/000-default.conf

# ── PHP ini ───────────────────────────────────────────────────────────────────
COPY docker/php-ged.ini /usr/local/etc/php/conf.d/ged.ini

# ── Code source ───────────────────────────────────────────────────────────────
WORKDIR /var/www/ged
COPY . /var/www/ged

# Permissions
RUN chown -R www-data:www-data /var/www/ged/storage \
    && chmod -R 750 /var/www/ged/storage \
    && chmod -R 755 /var/www/ged/public

EXPOSE 80
