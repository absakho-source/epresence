<?php
/**
 * e-Présence - Script de migration de base de données
 *
 * Usage CLI: php migrate.php
 * Usage Web: Accéder à /migrate.php (protégé par token)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Token de sécurité pour l'accès web (à changer en production)
define('MIGRATION_TOKEN', 'dgppe-migrate-2026');

// Vérifier si c'est une requête web
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    // Vérifier le token pour l'accès web
    $token = $_GET['token'] ?? '';
    if ($token !== MIGRATION_TOKEN) {
        http_response_code(403);
        die('Accès refusé. Token invalide.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function output($message) {
    echo $message . "\n";
    if (php_sapi_name() !== 'cli') {
        flush();
    }
}

output("=== Migration de la base de données e-Présence ===\n");

try {
    $pdo = db();
    output("Connexion à la base de données: OK\n");

    // Liste des migrations à exécuter
    $migrations = [
        [
            'name' => 'Ajouter colonne phone_secondary',
            'check' => "SELECT column_name FROM information_schema.columns WHERE table_name = 'signatures' AND column_name = 'phone_secondary'",
            'sql' => "ALTER TABLE signatures ADD COLUMN phone_secondary VARCHAR(20)"
        ],
        [
            'name' => 'Ajouter colonne role aux utilisateurs',
            'check' => "SELECT column_name FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'role'",
            'sql' => "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'"
        ],
        [
            'name' => 'Ajouter colonne is_structure_admin aux utilisateurs',
            'check' => "SELECT column_name FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'is_structure_admin'",
            'sql' => "ALTER TABLE users ADD COLUMN is_structure_admin BOOLEAN DEFAULT FALSE"
        ],
        [
            'name' => 'Ajouter colonne closed_at aux feuilles',
            'check' => "SELECT column_name FROM information_schema.columns WHERE table_name = 'sheets' AND column_name = 'closed_at'",
            'sql' => "ALTER TABLE sheets ADD COLUMN closed_at TIMESTAMP DEFAULT NULL"
        ],
        [
            'name' => 'Ajouter colonne closed_by aux feuilles',
            'check' => "SELECT column_name FROM information_schema.columns WHERE table_name = 'sheets' AND column_name = 'closed_by'",
            'sql' => "ALTER TABLE sheets ADD COLUMN closed_by INTEGER REFERENCES users(id)"
        ],
        [
            'name' => 'Ajouter colonne end_time aux feuilles',
            'check' => "SELECT column_name FROM information_schema.columns WHERE table_name = 'sheets' AND column_name = 'end_time'",
            'sql' => "ALTER TABLE sheets ADD COLUMN end_time TIME DEFAULT NULL"
        ],
        [
            'name' => 'Ajouter colonne auto_close aux feuilles',
            'check' => "SELECT column_name FROM information_schema.columns WHERE table_name = 'sheets' AND column_name = 'auto_close'",
            'sql' => "ALTER TABLE sheets ADD COLUMN auto_close BOOLEAN DEFAULT FALSE"
        ],
    ];

    $applied = 0;
    $skipped = 0;

    foreach ($migrations as $migration) {
        output("Migration: {$migration['name']}");

        // Vérifier si la migration est déjà appliquée
        $checkStmt = $pdo->query($migration['check']);
        $exists = $checkStmt->fetch();

        if ($exists) {
            output("  -> Déjà appliquée (ignorée)\n");
            $skipped++;
        } else {
            // Appliquer la migration
            $pdo->exec($migration['sql']);
            output("  -> Appliquée avec succès\n");
            $applied++;
        }
    }

    output("\n=== Résumé ===");
    output("Migrations appliquées: $applied");
    output("Migrations ignorées: $skipped");
    output("\nMigration terminée avec succès!");

} catch (PDOException $e) {
    output("\nERREUR: " . $e->getMessage());
    exit(1);
}
