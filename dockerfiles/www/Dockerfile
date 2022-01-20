FROM php:7.2-apache

RUN apt-get update -y && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev

RUN docker-php-ext-configure gd --with-freetype-dir --with-jpeg-dir

RUN docker-php-ext-install \
        gd \
        mysqli \
        sockets
