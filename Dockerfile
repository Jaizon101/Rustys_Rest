FROM php:8.2-apache

# Enable MySQL support (PDO + MySQL)
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite (important for clean URLs)
RUN a2enmod rewrite

# Copy your project into Apache folder
COPY . /var/www/html/

# Set permissions (important for PHP files)
RUN chown -R www-data:www-data /var/www/html
