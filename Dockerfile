FROM thr3a/php-fpm:latest

WORKDIR /app

COPY . ./

RUN composer install

