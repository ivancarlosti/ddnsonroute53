# Use official PHP 8.4 FPM Alpine base
FROM php:8.4-fpm-alpine

# Install Nginx and MariaDB client; install PHP extensions (mysqli) and do cleanup
RUN apk add --no-cache --update nginx mariadb-client \
    && docker-php-ext-install mysqli \
    # Clean up any cached files
    && rm -rf /var/cache/apk/* /tmp/*

# Copy your application code
COPY . /var/www/html/

# Nginx config: place a minimal nginx.conf into the image
COPY nginx.conf /etc/nginx/nginx.conf

# Make sure Nginx and PHP-FPM can access/serve project files
RUN chown -R www-data:www-data /var/www/html

# Expose HTTP port
EXPOSE 80

# Start both PHP-FPM and Nginx when the container launches
CMD php-fpm -D \
    && nginx -g 'daemon off;'
