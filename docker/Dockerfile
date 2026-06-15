# PHP 8.2 + Apache (DocumentRoot → public/)
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mbstring zip pdo pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-scripts --prefer-dist

COPY . .

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && composer dump-autoload --optimize \
    && a2enmod rewrite

EXPOSE 80

CMD ["/entrypoint.sh"]
