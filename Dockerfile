FROM php:8.2-apache

# Installer les extensions PHP nécessaires
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite headers

# Configuration PHP
RUN echo "session.save_path = /var/www/html/sessions" >> /usr/local/etc/php/conf.d/sessions.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/conf.d/errors.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini

# Copier les fichiers de l'application
COPY . /var/www/html/

# Créer le répertoire des sessions et définir les permissions
RUN mkdir -p /var/www/html/sessions \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 700 /var/www/html/sessions

# Configuration Apache pour le répertoire racine
RUN sed -i 's|/var/www/html|/var/www/html|g' /etc/apache2/sites-available/000-default.conf

# Exposer le port
EXPOSE 80

# Démarrer Apache
CMD ["apache2-foreground"]
