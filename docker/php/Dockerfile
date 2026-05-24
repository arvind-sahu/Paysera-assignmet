FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* symfony.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
