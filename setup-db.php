<?php
/**
 * Script d'initialisation de la base de données
 * À exécuter une seule fois puis supprimer
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Initialisation de la base de données e-Présence ===\n\n";

try {
    $pdo = db();
    echo "Connexion à la base de données : OK\n";
    echo "Type de base : " . DB_TYPE . "\n\n";

    // Schéma PostgreSQL
    $schema = "
    -- Suppression des tables existantes
    DROP TABLE IF EXISTS signatures CASCADE;
    DROP TABLE IF EXISTS sheets CASCADE;
    DROP TABLE IF EXISTS users CASCADE;

    -- Table des utilisateurs
    CREATE TABLE users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        structure VARCHAR(255),
        role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('user', 'admin')),
        status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'suspended')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Index utilisateurs
    CREATE INDEX idx_users_email ON users(email);
    CREATE INDEX idx_users_role ON users(role);
    CREATE INDEX idx_users_status ON users(status);

    -- Table des feuilles d'émargement
    CREATE TABLE sheets (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        event_date DATE NOT NULL,
        event_time TIME,
        location VARCHAR(255),
        unique_code VARCHAR(32) UNIQUE NOT NULL,
        status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'closed', 'archived')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    -- Index feuilles
    CREATE INDEX idx_sheets_unique_code ON sheets(unique_code);
    CREATE INDEX idx_sheets_user_id ON sheets(user_id);
    CREATE INDEX idx_sheets_status ON sheets(status);

    -- Table des signatures
    CREATE TABLE signatures (
        id SERIAL PRIMARY KEY,
        sheet_id INT NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        structure VARCHAR(255),
        function_title VARCHAR(255),
        phone VARCHAR(20),
        phone_secondary VARCHAR(20),
        email VARCHAR(255) NOT NULL,
        signature_data TEXT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sheet_id) REFERENCES sheets(id) ON DELETE CASCADE
    );

    -- Index signatures
    CREATE INDEX idx_signatures_sheet_id ON signatures(sheet_id);
    CREATE INDEX idx_signatures_email ON signatures(email);
    ";

    // Exécuter le schéma
    $pdo->exec($schema);
    echo "Tables créées avec succès !\n\n";

    // Créer les utilisateurs admin par défaut
    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, structure, role) VALUES (?, ?, ?, ?, ?, ?)");

    // Admin 1
    $stmt->execute(['admin@economie.gouv.sn', password_hash('Admin@2025', PASSWORD_DEFAULT), 'Admin', 'DGPPE', 'DGPPE', 'admin']);

    // Admin 2 - Abou Sakho
    $stmt->execute(['abou.sakho@economie.gouv.sn', password_hash('Start123', PASSWORD_DEFAULT), 'Abou', 'Sakho', 'DGPPE', 'admin']);

    echo "Utilisateurs admin créés :\n";
    echo "  1. admin@economie.gouv.sn / Admin@2025\n";
    echo "  2. abou.sakho@economie.gouv.sn / Start123\n\n";

    echo "=== INITIALISATION TERMINÉE ===\n";
    echo "\n⚠️  IMPORTANT: Supprimez ce fichier (setup-db.php) après utilisation !\n";

} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}
