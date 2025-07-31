# Use a slim variant to reduce base image size
FROM php:apache-bullseye

# Install necessary PHP extensions and clean up apt cache to reduce image size
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install mysqli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy only necessary files, avoiding developer files and docs (use .dockerignore for this)
COPY . /var/www/html/
