<?php
/**
 * e-Présence - Export Excel/CSV
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);

// Récupérer la feuille
$stmt = db()->prepare("SELECT * FROM sheets WHERE id = ? AND user_id = ?");
$stmt->execute([$sheetId, getCurrentUserId()]);
$sheet = $stmt->fetch();

if (!$sheet) {
    die('Feuille non trouvée.');
}

// Récupérer les signatures
$signaturesStmt = db()->prepare("
    SELECT * FROM signatures
    WHERE sheet_id = ?
    ORDER BY signed_at ASC
");
$signaturesStmt->execute([$sheetId]);
$signatures = $signaturesStmt->fetchAll();

// Nom du fichier
$filename = 'emargement-' . $sheet['unique_code'] . '-' . date('Y-m-d') . '.csv';

// Headers pour téléchargement CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

// Ouvrir le flux de sortie
$output = fopen('php://output', 'w');

// Informations de la feuille (en-tête)
fputcsv($output, ['FEUILLE D\'ÉMARGEMENT'], ';');
fputcsv($output, ['Titre', $sheet['title']], ';');
fputcsv($output, ['Date', formatDateFr($sheet['event_date'])], ';');
if ($sheet['event_time']) {
    fputcsv($output, ['Heure', formatTime($sheet['event_time'])], ';');
}
if ($sheet['location']) {
    fputcsv($output, ['Lieu', $sheet['location']], ';');
}
fputcsv($output, [''], ';'); // Ligne vide

// En-têtes des colonnes
fputcsv($output, [
    'N°',
    'Nom',
    'Prénom',
    'Email',
    'Téléphone',
    'Téléphone secondaire',
    'Fonction',
    'Structure',
    'Date de signature',
    'Heure de signature',
    'Adresse IP'
], ';');

// Données
$num = 1;
foreach ($signatures as $sig) {
    fputcsv($output, [
        $num++,
        $sig['last_name'],
        $sig['first_name'],
        $sig['email'],
        $sig['phone'],
        $sig['phone_secondary'] ?? '',
        $sig['function_title'] ?? '',
        $sig['structure'] ?? '',
        date('d/m/Y', strtotime($sig['signed_at'])),
        date('H:i:s', strtotime($sig['signed_at'])),
        $sig['ip_address']
    ], ';');
}

// Ligne vide et total
fputcsv($output, [''], ';');
fputcsv($output, ['Total participants', count($signatures)], ';');
fputcsv($output, ['Document généré le', date('d/m/Y à H:i:s')], ';');

fclose($output);
exit;
