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
    exit('Document non trouvé dans la base de données');
}

// Construire le chemin du fichier
$filePath = DOCUMENTS_PATH . '/' . $doc['stored_name'];

if (!file_exists($filePath)) {
    // En mode debug, afficher plus d'informations
    if (DEBUG_MODE) {
        $info = [
            'error' => 'Fichier non trouvé sur le serveur',
            'doc_id' => $docId,
            'stored_name' => $doc['stored_name'],
            'expected_path' => $filePath,
            'documents_path' => DOCUMENTS_PATH,
            'uploads_path' => UPLOADS_PATH,
            'persistent_disk_exists' => is_dir('/var/data/uploads'),
            'documents_dir_exists' => is_dir(DOCUMENTS_PATH),
        ];
        http_response_code(404);
        header('Content-Type: application/json');
        exit(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

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
