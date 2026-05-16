FROM php:8.2-apache

# Install system libraries needed for PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions: GD (for image handling) and PDO MySQL (for database)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql

# Enable Apache mod_rewrite (needed for .htaccess routing)
RUN a2dismod mpm_event && a2enmod mpm_prefork rewrite

# Set the Apache document root to the public/ folder only
# This means the browser cannot access PHP source files, config, or .env
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/public>|g' \
        /etc/apache2/apache2.conf \
    && echo '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>' \
        >> /etc/apache2/apache2.conf

# Copy all project files into the container
COPY . /var/www/html/

# Make the uploads folder writable so users can upload images
RUN chown -R www-data:www-data /var/www/html/uploads \
                                /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/uploads \
                    /var/www/html/public/uploads

EXPOSE 80