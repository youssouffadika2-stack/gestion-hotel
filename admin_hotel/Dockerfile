FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html
WORKDIR /var/www/html
COPY . .
RUN mkdir -p uploads && chown -R www-data:www-data uploads
VOLUME ["/var/www/html/uploads"]
