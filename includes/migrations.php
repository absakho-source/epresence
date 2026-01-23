<?php
/**
 * e-Présence - Système de migrations automatiques
 *
 * Ce fichier vérifie et applique les migrations nécessaires
 * au démarrage de l'application.
 */

/**
 * Exécute les migrations en attente
 */
function runMigrations() {
    try {
        $pdo = db();

        // Migration: Table password_resets
        $checkSql = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'password_resets')";
        $stmt = $pdo->query($checkSql);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
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
            $pdo->exec($sql);
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_user_id ON password_resets(user_id);");

            // Log la migration
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("[e-Présence] Migration: Table password_resets créée avec succès");
            }
        }

        return true;
    } catch (PDOException $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("[e-Présence] Erreur migration: " . $e->getMessage());
        }
        return false;
    }
}

// Exécuter les migrations automatiquement
runMigrations();
