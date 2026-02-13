<?php
/**
 * e-Présence - API de recherche globale
 */

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$query = trim($_GET['q'] ?? '');

// Minimum 2 caractères
if (strlen($query) < 2) {
    jsonResponse(['results' => [], 'sheets' => [], 'participants' => []]);
}

$searchTerm = '%' . $query . '%';
$userId = getCurrentUserId();
$currentUser = getCurrentUser();
$isAdmin = ($currentUser['role'] === 'admin');
$isStructureAdmin = !empty($currentUser['is_structure_admin']) && !empty($currentUser['structure']);

$results = [
    'sheets' => [],
    'participants' => []
];

// Recherche de feuilles
if ($isAdmin) {
    // Admin voit toutes les feuilles
    $sheetsStmt = db()->prepare("
        SELECT s.id, s.title, s.event_date, s.status, u.first_name, u.last_name
        FROM sheets s
        JOIN users u ON s.user_id = u.id
        WHERE s.title ILIKE ? OR s.location ILIKE ?
        ORDER BY s.event_date DESC
        LIMIT 10
    ");
    $sheetsStmt->execute([$searchTerm, $searchTerm]);
} elseif ($isStructureAdmin) {
    // Super-utilisateur voit les feuilles de sa structure
    require_once __DIR__ . '/../config/structures.php';
    $structureCodes = getStructureCodesInCategory($currentUser['structure']);
    $placeholders = implode(',', array_fill(0, count($structureCodes), '?'));

    $sheetsStmt = db()->prepare("
        SELECT s.id, s.title, s.event_date, s.status, u.first_name, u.last_name
        FROM sheets s
        JOIN users u ON s.user_id = u.id
        WHERE (s.title ILIKE ? OR s.location ILIKE ?)
        AND (s.user_id = ? OR u.structure IN ($placeholders))
        ORDER BY s.event_date DESC
        LIMIT 10
    ");
    $sheetsStmt->execute(array_merge([$searchTerm, $searchTerm, $userId], $structureCodes));
} else {
    // Utilisateur standard voit uniquement ses feuilles
    $sheetsStmt = db()->prepare("
        SELECT s.id, s.title, s.event_date, s.status, u.first_name, u.last_name
        FROM sheets s
        JOIN users u ON s.user_id = u.id
        WHERE s.user_id = ? AND (s.title ILIKE ? OR s.location ILIKE ?)
        ORDER BY s.event_date DESC
        LIMIT 10
    ");
    $sheetsStmt->execute([$userId, $searchTerm, $searchTerm]);
}

while ($sheet = $sheetsStmt->fetch()) {
    $results['sheets'][] = [
        'id' => $sheet['id'],
        'title' => $sheet['title'],
        'date' => formatDateFr($sheet['event_date']),
        'status' => $sheet['status'],
        'creator' => $sheet['first_name'] . ' ' . $sheet['last_name']
    ];
}

// Recherche de participants (dans les signatures)
if ($isAdmin) {
    $participantsStmt = db()->prepare("
        SELECT DISTINCT sig.first_name, sig.last_name, sig.email, sig.structure,
               s.id as sheet_id, s.title as sheet_title
        FROM signatures sig
        JOIN sheets s ON sig.sheet_id = s.id
        WHERE sig.first_name ILIKE ? OR sig.last_name ILIKE ? OR sig.email ILIKE ?
        ORDER BY sig.last_name, sig.first_name
        LIMIT 15
    ");
    $participantsStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
} elseif ($isStructureAdmin) {
    require_once __DIR__ . '/../config/structures.php';
    $structureCodes = getStructureCodesInCategory($currentUser['structure']);
    $placeholders = implode(',', array_fill(0, count($structureCodes), '?'));

    $participantsStmt = db()->prepare("
        SELECT DISTINCT sig.first_name, sig.last_name, sig.email, sig.structure,
               s.id as sheet_id, s.title as sheet_title
        FROM signatures sig
        JOIN sheets s ON sig.sheet_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE (sig.first_name ILIKE ? OR sig.last_name ILIKE ? OR sig.email ILIKE ?)
        AND (s.user_id = ? OR u.structure IN ($placeholders))
        ORDER BY sig.last_name, sig.first_name
        LIMIT 15
    ");
    $participantsStmt->execute(array_merge([$searchTerm, $searchTerm, $searchTerm, $userId], $structureCodes));
} else {
    $participantsStmt = db()->prepare("
        SELECT DISTINCT sig.first_name, sig.last_name, sig.email, sig.structure,
               s.id as sheet_id, s.title as sheet_title
        FROM signatures sig
        JOIN sheets s ON sig.sheet_id = s.id
        WHERE s.user_id = ? AND (sig.first_name ILIKE ? OR sig.last_name ILIKE ? OR sig.email ILIKE ?)
        ORDER BY sig.last_name, sig.first_name
        LIMIT 15
    ");
    $participantsStmt->execute([$userId, $searchTerm, $searchTerm, $searchTerm]);
}

while ($participant = $participantsStmt->fetch()) {
    $results['participants'][] = [
        'name' => $participant['first_name'] . ' ' . $participant['last_name'],
        'email' => $participant['email'],
        'structure' => $participant['structure'] ?? '',
        'sheet_id' => $participant['sheet_id'],
        'sheet_title' => $participant['sheet_title']
    ];
}

jsonResponse($results);
