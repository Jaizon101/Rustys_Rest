FROM php:8.2-apache

RUN a2dismod mpm_event 2>/dev/null || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]