<?php
/**
 * Script de migration - Ajout du statut 'pending' pour validation admin
 * A exécuter UNE SEULE FOIS puis supprimer ce fichier
 *
 * Accès: https://epresence.onrender.com/migrate-pending.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Sécurité: vérifier un token ou autoriser uniquement en local/debug
$allowedToken = getenv('MIGRATION_TOKEN') ?: 'DGPPE2026-migrate';
$providedToken = isset($_GET['token']) ? $_GET['token'] : '';

if ($providedToken !== $allowedToken) {
    http_response_code(403);
    die("Accès refusé. Token requis: ?token=VOTRE_TOKEN");
}

echo "<h1>Migration: Statut 'pending' pour validation admin</h1>";
echo "<pre>";

try {
    $pdo = db();

    // 1. Modifier la contrainte de statut
    echo "1. Modification de la contrainte de statut...\n";
    try {
        $pdo->exec("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check");
        echo "   - Ancienne contrainte supprimée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'active', 'suspended'))");
        echo "   - Nouvelle contrainte ajoutée (pending, active, suspended)\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    // 2. Ajouter les colonnes approved_at et approved_by
    echo "\n2. Ajout des colonnes de suivi de validation...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP");
        echo "   - Colonne approved_at ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT");
        echo "   - Colonne approved_by ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    // 3. Mettre le statut par défaut à 'pending'
    echo "\n3. Modification du statut par défaut...\n";
    try {
        $pdo->exec("ALTER TABLE users ALTER COLUMN status SET DEFAULT 'pending'");
        echo "   - Statut par défaut mis à 'pending'\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    // 4. Créer l'index pour les utilisateurs en attente
    echo "\n4. Création de l'index pour les recherches...\n";
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_pending ON users(status) WHERE status = 'pending'");
        echo "   - Index idx_users_pending créé\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    // 5. Vérifier la structure de la table
    echo "\n5. Vérification de la structure...\n";
    $result = $pdo->query("SELECT column_name, data_type, column_default FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position");
    echo "   Colonnes de la table users:\n";
    foreach ($result as $row) {
        echo "   - {$row['column_name']} ({$row['data_type']}) default: {$row['column_default']}\n";
    }

    // 6. Compter les utilisateurs par statut
    echo "\n6. Statistiques des utilisateurs...\n";
    $stats = $pdo->query("SELECT status, COUNT(*) as count FROM users GROUP BY status")->fetchAll();
    foreach ($stats as $stat) {
        echo "   - {$stat['status']}: {$stat['count']} utilisateur(s)\n";
    }

    // === MIGRATION PROFIL UTILISATEUR ===
    echo "\n\n=== MIGRATION PROFIL UTILISATEUR ===\n";

    // 6b. Ajouter la colonne function_title (fonction/poste)
    echo "\n6b. Ajout de la colonne function_title...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS function_title VARCHAR(255)");
        echo "   - Colonne function_title ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    // 6c. Ajouter la colonne is_structure_admin (super-utilisateur structure)
    echo "\n6c. Ajout de la colonne is_structure_admin...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_structure_admin BOOLEAN DEFAULT FALSE");
        echo "   - Colonne is_structure_admin ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    // === MIGRATION FEUILLES: Heure de fin et clôture automatique ===
    echo "\n\n=== MIGRATION FEUILLES ===\n";

    // 7. Ajouter les colonnes pour l'heure de fin
    echo "\n7. Ajout des colonnes pour l'heure de fin des feuilles...\n";
    try {
        $pdo->exec("ALTER TABLE sheets ADD COLUMN IF NOT EXISTS end_time TIME");
        echo "   - Colonne end_time ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE sheets ADD COLUMN IF NOT EXISTS auto_close BOOLEAN DEFAULT FALSE");
        echo "   - Colonne auto_close ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE sheets ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP");
        echo "   - Colonne closed_at ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE sheets ADD COLUMN IF NOT EXISTS closed_by INT");
        echo "   - Colonne closed_by ajoutée\n";
    } catch (Exception $e) {
        echo "   - Note: " . $e->getMessage() . "\n";
    }

    // 8. Vérifier la structure de la table sheets
    echo "\n8. Vérification de la structure de la table sheets...\n";
    $result = $pdo->query("SELECT column_name, data_type, column_default FROM information_schema.columns WHERE table_name = 'sheets' ORDER BY ordinal_position");
    echo "   Colonnes de la table sheets:\n";
    foreach ($result as $row) {
        echo "   - {$row['column_name']} ({$row['data_type']}) default: {$row['column_default']}\n";
    }

    echo "\n✅ Migration terminée avec succès!\n";
    echo "\n⚠️ IMPORTANT: Supprimez ce fichier (migrate-pending.php) après exécution.\n";

} catch (PDOException $e) {
    echo "\n❌ Erreur: " . $e->getMessage() . "\n";
}

echo "</pre>";
