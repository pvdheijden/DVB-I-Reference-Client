FROM php:8.2-apache
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

ARG STREAMIQ_SERVICELIST_URI

COPY src/ /var/www/html/
