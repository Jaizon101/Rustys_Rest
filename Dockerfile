FROM php:8.2-apache

# Install MySQL support
RUN docker-php-ext-install pdo pdo_mysql

# 🔥 FIX MPM CONFLICT (VERY IMPORTANT)
RUN a2dismod mpm_event || true
RUN a2dismod mpm_worker || true
RUN a2dismod mpm_prefork || true
RUN a2enmod mpm_prefork

# Enable rewrite
RUN a2enmod rewrite

# Copy project
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html
