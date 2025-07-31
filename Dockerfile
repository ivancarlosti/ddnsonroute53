FROM php:apache

RUN docker-php-ext-install mysqli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
