FROM php:apache
COPY . /var/www/html/
# Exemplo: instale extensões extras se precisar
# RUN docker-php-ext-install mysqli
