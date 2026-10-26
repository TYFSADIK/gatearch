FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && sed -ri 's#/var/www/html#/var/www/gatearch/public#g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's#AllowOverride None#AllowOverride All#g' /etc/apache2/apache2.conf \
 && printf '<Directory /var/www/gatearch/public>\n  AllowOverride All\n  Require all granted\n</Directory>\n' > /etc/apache2/conf-enabled/gatearch.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY . /var/www/gatearch
