FROM php:8.2-apache
# Copiar apenas a pasta backend para a raiz do servidor web
COPY ./backend /var/www/html/
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql
# Ativar mod_rewrite para APIs limpas se necessário
RUN a2enmod rewrite
EXPOSE ${PORT}
