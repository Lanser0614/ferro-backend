FROM php:8.2-fpm

ARG USER_ID=1000
ARG GROUP_ID=1000

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        nodejs \
        npm \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libcurl4-openssl-dev \
        libssl-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid "${GROUP_ID}" laravel \
    && useradd --uid "${USER_ID}" --gid laravel --shell /bin/bash --create-home laravel \
    && chown -R laravel:laravel /var/www/html \
    && sed -i "s/^user = www-data/user = laravel/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/^group = www-data/group = laravel/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/;*listen.owner = .*/listen.owner = laravel/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/;*listen.group = .*/listen.group = laravel/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/;*listen.mode = .*/listen.mode = 0660/" /usr/local/etc/php-fpm.d/www.conf

USER laravel

EXPOSE 9000

CMD ["php-fpm"]
