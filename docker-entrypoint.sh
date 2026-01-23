#!/bin/bash
set -e

# Créer et configurer le disque persistant Render
PERSISTENT_DISK="/var/data/uploads"

if [ -d "/var/data" ]; then
    echo "Configuration du disque persistant..."

    # Créer le répertoire uploads s'il n'existe pas
    mkdir -p "$PERSISTENT_DISK"
    mkdir -p "$PERSISTENT_DISK/documents"
    mkdir -p "$PERSISTENT_DISK/sessions"

    # Définir les permissions pour www-data (utilisateur Apache)
    chown -R www-data:www-data "$PERSISTENT_DISK"
    chmod -R 755 "$PERSISTENT_DISK"
    chmod 700 "$PERSISTENT_DISK/sessions"

    echo "Disque persistant configuré avec succès!"
else
    echo "Disque persistant non détecté, utilisation du stockage local."
fi

# Démarrer Apache
exec apache2-foreground
