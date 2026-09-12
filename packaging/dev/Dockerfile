FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev \
    && docker-php-ext-install intl mbstring mysqli zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
RUN printf '%s\n' '<Directory /var/www/html>' 'AllowOverride All' 'Require all granted' '</Directory>' > /etc/apache2/conf-enabled/support-k.conf
ENV SUPPORT_K_DOCUMENT_ROOT=/var/www/html/public
ENV SUPPORT_K_HTTP_PORT=80
RUN printf '%s\n' 'Listen ${SUPPORT_K_HTTP_PORT}' > /etc/apache2/ports.conf \
    && printf '%s\n' '<VirtualHost *:${SUPPORT_K_HTTP_PORT}>' 'DocumentRoot "${SUPPORT_K_DOCUMENT_ROOT}"' 'ErrorLog /proc/self/fd/2' 'CustomLog /proc/self/fd/1 combined' '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
