FROM php:8.2-apache

# Installer les extensions PHP nécessaires (PostgreSQL uniquement)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite headers

# Configuration PHP pour les sessions sur disque persistant
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/errors.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 12M" >> /usr/local/etc/php/conf.d/uploads.ini

# Copier les fichiers de l'application
COPY . /var/www/html/

# Copier et rendre exécutable le script d'entrée
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Créer le répertoire des sessions local (fallback) et définir les permissions
RUN mkdir -p /var/www/html/sessions \
    && mkdir -p /var/www/html/uploads/documents \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 700 /var/www/html/sessions

# Préparer le point de montage du disque persistant
RUN mkdir -p /var/data \
    && chown www-data:www-data /var/data

# Configuration Apache pour le répertoire racine
RUN sed -i 's|/var/www/html|/var/www/html|g' /etc/apache2/sites-available/000-default.conf

# Exposer le port
EXPOSE 80

# Utiliser le script d'entrée pour configurer les permissions au démarrage
ENTRYPOINT ["docker-entrypoint.sh"]
