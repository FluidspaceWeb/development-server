# syntax=docker/dockerfile:1

# FROM php:8.1-cli
FROM php:8.1.20-cli-alpine3.18
COPY . /usr/src/FluidspaceDevApi
WORKDIR /usr/src/FluidspaceDevApi

RUN apk update && apk upgrade && apk add --update linux-headers \
    && apk --no-cache add autoconf g++ make gmp-dev bash coreutils git openssh-client patch subversion tini unzip zip \
    && cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini \
    && sed -i.bak "s/error_reporting = E_ALL/error_reporting = E_ALL \& ~E_DEPRECATED \& ~E_STRICT/" /usr/local/etc/php/php.ini \
    && chmod +x ./composer-installer.sh \
    && ./composer-installer.sh \
    && mv composer.phar /usr/local/bin/composer \
    && pecl install mongodb && docker-php-ext-enable mongodb \ 
    && docker-php-ext-install bcmath gmp \
    && composer install \
    && php -f gen_integration_crypto.php

CMD ["php", "-S", "0.0.0.0:80", "-t", "/usr/src/FluidspaceDevApi/app/public", "/usr/src/FluidspaceDevApi/app/public/intercept.php"]
EXPOSE 80
