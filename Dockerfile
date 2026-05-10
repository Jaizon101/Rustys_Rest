FROM php8.2-apache

RUN a2dismod mpm_event mpm_worker mpm_prefork 2devnull  true 
    && a2enmod mpm_prefork 
    && a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . varwwwhtml

RUN chown -R www-datawww-data varwwwhtml

EXPOSE 80
CMD [apache2-foreground]