<?php
/**
 * Migration: Créer la table sheet_documents
 * Exécuter une seule fois puis supprimer ce fichier
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "<h1>Migration: Table sheet_documents</h1>";

try {
    $pdo = db();

    // Vérifier si la table existe déjà
    if (DB_TYPE === 'pgsql') {
        $checkTable = $pdo->query("SELECT to_regclass('public.sheet_documents')");
        $tableExists = $checkTable->fetchColumn() !== null;
    } else {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'sheet_documents'");
        $tableExists = $checkTable->rowCount() > 0;
    }

    if ($tableExists) {
        echo "<p style='color: orange;'>⚠️ La table sheet_documents existe déjà.</p>";
    } else {
        // Créer la table
        if (DB_TYPE === 'pgsql') {
            $sql = "
                CREATE TABLE sheet_documents (
                    id SERIAL PRIMARY KEY,
                    sheet_id INTEGER REFERENCES sheets(id) ON DELETE CASCADE,
                    original_name VARCHAR(255) NOT NULL,
                    stored_name VARCHAR(255) NOT NULL,
                    file_type VARCHAR(100) NOT NULL,
                    file_size INTEGER NOT NULL,
                    document_type VARCHAR(50) DEFAULT 'other',
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ";
        } else {
            $sql = "
                CREATE TABLE sheet_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sheet_id INT NOT NULL,
                    original_name VARCHAR(255) NOT NULL,
                    stored_name VARCHAR(255) NOT NULL,
                    file_type VARCHAR(100) NOT NULL,
                    file_size INT NOT NULL,
                    document_type VARCHAR(50) DEFAULT 'other',
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sheet_id) REFERENCES sheets(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
        }

        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Table sheet_documents créée avec succès!</p>";

        // Créer l'index
        if (DB_TYPE === 'pgsql') {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sheet_documents_sheet_id ON sheet_documents(sheet_id)");
        } else {
            $pdo->exec("CREATE INDEX idx_sheet_documents_sheet_id ON sheet_documents(sheet_id)");
        }
        echo "<p style='color: green;'>✅ Index créé avec succès!</p>";
    }

    // Afficher la structure de la table
    echo "<h2>Structure de la table:</h2>";
    if (DB_TYPE === 'pgsql') {
        $columns = $pdo->query("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = 'sheet_documents'
            ORDER BY ordinal_position
        ");
    } else {
        $columns = $pdo->query("DESCRIBE sheet_documents");
    }

    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Nullable</th><th>Défaut</th></tr>";
    foreach ($columns as $col) {
        if (DB_TYPE === 'pgsql') {
            echo "<tr><td>{$col['column_name']}</td><td>{$col['data_type']}</td><td>{$col['is_nullable']}</td><td>{$col['column_default']}</td></tr>";
        } else {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
        }
    }
    echo "</table>";

    echo "<br><p><strong>Migration terminée!</strong></p>";
    echo "<p style='color: red;'>⚠️ N'oubliez pas de supprimer ce fichier après la migration.</p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
