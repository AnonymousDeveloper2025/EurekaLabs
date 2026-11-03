FROM php:8.2-apache
ENV PORT=10000

# Dependências PHP necessárias para PostgreSQL, mPDF e imagens.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql mbstring gd \
    && rm -rf /var/lib/apt/lists/*

# Composer oficial para instalar as dependências do backend durante o build.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar o backend para a raiz do Apache.
COPY ./backend /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html

RUN a2enmod rewrite \
    && sed -i "s/80/${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE ${PORT}
