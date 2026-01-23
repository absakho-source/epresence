<?php
/**
 * Migration: Création de la table password_resets
 *
 * Exécuter ce script une seule fois pour créer la table nécessaire
 * au système de réinitialisation de mot de passe.
 *
 * Usage: php sql/migrate_password_resets.php
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Migration: Table password_resets ===\n\n";

try {
    $pdo = db();

    echo "Type de base de données: PostgreSQL\n";

    // Créer la table
    $sql = "
        CREATE TABLE IF NOT EXISTS password_resets (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
            token VARCHAR(64) UNIQUE NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";

    echo "Création de la table password_resets...\n";
    $pdo->exec($sql);
    echo "✓ Table créée avec succès!\n";

    // Créer les index
    echo "Création des index...\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);");
    echo "✓ Index sur token créé.\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_user_id ON password_resets(user_id);");
    echo "✓ Index sur user_id créé.\n";

    echo "\n=== Migration terminée avec succès! ===\n";

} catch (PDOException $e) {
    echo "\n❌ Erreur lors de la migration:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
