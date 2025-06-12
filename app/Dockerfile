FROM php:8.2-apache

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Apache's document root is already /var/www/html by default in this image
# So, no explicit command is needed for that.
