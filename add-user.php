<?php
/**
 * Ajouter un utilisateur admin
 * À supprimer après utilisation
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();

    // Ajouter Abou Sakho
    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, structure, role) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (email) DO NOTHING");
    $stmt->execute(['abou.sakho@economie.gouv.sn', password_hash('Start123', PASSWORD_DEFAULT), 'Abou', 'Sakho', 'DGPPE', 'admin']);

    echo "Utilisateur ajouté :\n";
    echo "  Email: abou.sakho@economie.gouv.sn\n";
    echo "  Mot de passe: Start123\n\n";
    echo "Supprimez ce fichier après utilisation.\n";

} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}
