<?php
/**
 * e-Présence - API de téléchargement de documents
 * Sert les fichiers depuis le disque persistant
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$docId = intval($_GET['id'] ?? 0);

if ($docId <= 0) {
    http_response_code(400);
    exit('Document invalide');
}

// Récupérer les infos du document
$stmt = db()->prepare("
    SELECT sd.*, s.status as sheet_status
    FROM sheet_documents sd
    JOIN sheets s ON sd.sheet_id = s.id
    WHERE sd.id = ?
");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Document non trouvé');
}

// Construire le chemin du fichier
$filePath = DOCUMENTS_PATH . '/' . $doc['stored_name'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('Fichier non trouvé sur le serveur');
}

// Déterminer le type MIME
$mimeType = $doc['file_type'] ?: mime_content_type($filePath);

// Envoyer les headers appropriés
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $doc['original_name'] . '"');
header('Cache-Control: private, max-age=3600');

// Envoyer le fichier
readfile($filePath);
exit;
