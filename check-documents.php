<?php
/**
 * Diagnostic: Vérifier les documents uploadés
 * À SUPPRIMER après utilisation
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "<h1>Diagnostic Documents</h1>";

// 1. Vérifier si la table sheet_documents existe
echo "<h2>1. Table sheet_documents</h2>";
try {
    if (DB_TYPE === 'pgsql') {
        $checkTable = db()->query("SELECT to_regclass('public.sheet_documents')");
        $tableExists = $checkTable->fetchColumn() !== null;
    } else {
        $checkTable = db()->query("SHOW TABLES LIKE 'sheet_documents'");
        $tableExists = $checkTable->rowCount() > 0;
    }
    echo $tableExists ? "<p style='color:green'>✅ Table existe</p>" : "<p style='color:red'>❌ Table n'existe PAS</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 2. Compter les documents en base
echo "<h2>2. Documents en base de données</h2>";
try {
    $countStmt = db()->query("SELECT COUNT(*) as total FROM sheet_documents");
    $count = $countStmt->fetch()['total'];
    echo "<p>Total documents en base: <strong>{$count}</strong></p>";

    if ($count > 0) {
        $docsStmt = db()->query("SELECT sd.*, s.title as sheet_title FROM sheet_documents sd JOIN sheets s ON sd.sheet_id = s.id ORDER BY sd.uploaded_at DESC LIMIT 10");
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>Feuille</th><th>Nom original</th><th>Nom stocké</th><th>Type</th><th>Taille</th><th>Date</th></tr>";
        while ($doc = $docsStmt->fetch()) {
            echo "<tr>";
            echo "<td>{$doc['id']}</td>";
            echo "<td>{$doc['sheet_title']}</td>";
            echo "<td>" . htmlspecialchars($doc['original_name']) . "</td>";
            echo "<td>" . htmlspecialchars($doc['stored_name']) . "</td>";
            echo "<td>{$doc['document_type']}</td>";
            echo "<td>" . round($doc['file_size'] / 1024) . " KB</td>";
            echo "<td>{$doc['uploaded_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. Vérifier le dossier uploads
echo "<h2>3. Dossier documents (DOCUMENTS_PATH)</h2>";
$uploadDir = DOCUMENTS_PATH . '/';
echo "<p><strong>DOCUMENTS_PATH:</strong> " . htmlspecialchars(DOCUMENTS_PATH) . "</p>";
echo "<p><strong>UPLOADS_PATH:</strong> " . htmlspecialchars(UPLOADS_PATH) . "</p>";
echo "<p><strong>Render détecté:</strong> " . (getenv('RENDER') ? 'Oui' : 'Non') . "</p>";
if (is_dir($uploadDir)) {
    echo "<p style='color:green'>✅ Dossier existe</p>";
    echo "<p>Chemin: " . htmlspecialchars($uploadDir) . "</p>";

    // Vérifier les permissions
    echo "<p>Écriture possible: " . (is_writable($uploadDir) ? "<span style='color:green'>✅ Oui</span>" : "<span style='color:red'>❌ Non</span>") . "</p>";

    // Lister les fichiers
    $files = glob($uploadDir . '*');
    $fileCount = count($files);
    echo "<p>Fichiers présents: <strong>{$fileCount}</strong></p>";

    if ($fileCount > 0 && $fileCount <= 20) {
        echo "<ul>";
        foreach ($files as $file) {
            if (basename($file) !== '.gitkeep' && basename($file) !== '.htaccess') {
                echo "<li>" . htmlspecialchars(basename($file)) . " (" . round(filesize($file) / 1024) . " KB)</li>";
            }
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color:red'>❌ Dossier n'existe PAS</p>";
}

// 4. Vérifier la cohérence
echo "<h2>4. Cohérence Base/Fichiers</h2>";
try {
    $docsStmt = db()->query("SELECT stored_name FROM sheet_documents");
    $missing = [];
    $found = [];
    while ($doc = $docsStmt->fetch()) {
        $filePath = $uploadDir . $doc['stored_name'];
        if (file_exists($filePath)) {
            $found[] = $doc['stored_name'];
        } else {
            $missing[] = $doc['stored_name'];
        }
    }

    if (count($missing) > 0) {
        echo "<p style='color:orange'>⚠️ Documents en base mais fichiers MANQUANTS (" . count($missing) . "):</p>";
        echo "<ul>";
        foreach ($missing as $m) {
            echo "<li style='color:red'>" . htmlspecialchars($m) . "</li>";
        }
        echo "</ul>";
        echo "<p><strong>Cause probable:</strong> Sur Render sans disque persistant, le système de fichiers est éphémère. Ajoutez un Persistent Disk dans les paramètres Render.</p>";
    } else if (count($found) > 0) {
        echo "<p style='color:green'>✅ Tous les fichiers en base existent sur le disque (" . count($found) . ")</p>";
    } else {
        echo "<p>Aucun document en base de données.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p><em>Pensez à supprimer ce fichier après diagnostic.</em></p>";
?>
