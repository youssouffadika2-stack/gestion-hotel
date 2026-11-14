# 1) assets front (Vite)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# 2) application Laravel
FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libpng-dev \
    && docker-php-ext-install pdo_mysql zip intl bcmath gd pcntl \
    && a2enmod rewrite && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www/html
COPY . .
COPY --from=assets /app/public/build public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
