<?php
/**
 * e-Présence - API d'enregistrement des signatures
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

// Vérifier le token CSRF
if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    jsonResponse(['error' => 'Session expirée. Veuillez recharger la page.'], 403);
}

// Récupérer les données
$sheetCode = $_POST['sheet_code'] ?? '';
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$phoneSecondary = trim($_POST['phone_secondary'] ?? '');
$functionTitle = trim($_POST['function_title'] ?? '');
$structure = trim($_POST['structure'] ?? '');
$signatureData = $_POST['signature_data'] ?? '';
$signedDays = $_POST['signed_days'] ?? []; // Jours sélectionnés pour événement multi-jours

// Validation
$errors = [];

if (empty($sheetCode)) {
    $errors[] = "Code de feuille manquant.";
}

if (empty($firstName)) {
    $errors[] = "Le prénom est obligatoire.";
}

if (empty($lastName)) {
    $errors[] = "Le nom est obligatoire.";
}

if (empty($email) || !isValidEmail($email)) {
    $errors[] = "L'adresse email n'est pas valide.";
}

if (empty($phone)) {
    $errors[] = "Le numéro de téléphone est obligatoire.";
}

if (empty($signatureData) || strpos($signatureData, 'data:image/png;base64,') !== 0) {
    $errors[] = "La signature est obligatoire.";
}

// Vérifier que la signature n'est pas vide (juste un canvas blanc)
if (!empty($signatureData)) {
    $base64Data = str_replace('data:image/png;base64,', '', $signatureData);
    $imageData = base64_decode($base64Data);
    if (strlen($imageData) < 1000) { // Une signature vide fait environ 500 bytes
        $errors[] = "Veuillez signer avant de valider.";
    }
}

if (!empty($errors)) {
    jsonResponse(['success' => false, 'errors' => $errors], 400);
}

// Vérifier la feuille
$stmt = db()->prepare("SELECT id, status, event_date, end_date FROM sheets WHERE unique_code = ?");
$stmt->execute([$sheetCode]);
$sheet = $stmt->fetch();

if (!$sheet) {
    jsonResponse(['error' => 'Feuille d\'émargement non trouvée.'], 404);
}

if ($sheet['status'] !== 'active') {
    jsonResponse(['error' => 'Cette feuille est clôturée et n\'accepte plus de signatures.'], 403);
}

// Déterminer si c'est un événement multi-jours
$isMultiDay = !empty($sheet['end_date']) && $sheet['end_date'] !== $sheet['event_date'];

// Valider le jour sélectionné
if (empty($signedDays) || !is_array($signedDays)) {
    jsonResponse(['error' => 'Veuillez sélectionner un jour de présence.'], 400);
}

// Prendre le premier jour (un seul jour sélectionné avec radio button)
$selectedDay = $signedDays[0];
$startDate = new DateTime($sheet['event_date']);
$endDate = new DateTime($sheet['end_date'] ?? $sheet['event_date']);
$dayDate = new DateTime($selectedDay);

// Valider que le jour est dans la plage de l'événement
if ($dayDate < $startDate || $dayDate > $endDate) {
    jsonResponse(['error' => 'Le jour sélectionné n\'est pas valide pour cet événement.'], 400);
}

// Vérifier si l'email a déjà signé pour ce jour
$checkStmt = db()->prepare("
    SELECT id FROM signatures
    WHERE sheet_id = ? AND email = ? AND signed_for_date = ?
");
$checkStmt->execute([$sheet['id'], strtolower($email), $selectedDay]);

if ($checkStmt->fetch()) {
    if ($isMultiDay) {
        jsonResponse(['error' => 'Vous avez déjà signé pour ce jour.'], 409);
    } else {
        jsonResponse(['error' => 'Cette adresse email a déjà signé cette feuille.'], 409);
    }
}

// Enregistrer la signature
try {
    $stmt = db()->prepare("
        INSERT INTO signatures (
            sheet_id, first_name, last_name, email, phone, phone_secondary,
            function_title, structure, signature_data, ip_address, user_agent, signed_for_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $sheet['id'],
        $firstName,
        strtoupper($lastName), // Nom en majuscules
        strtolower($email),
        $phone,
        $phoneSecondary ?: null,
        $functionTitle ?: null,
        $structure ?: null,
        $signatureData,
        getClientIP(),
        getUserAgent(),
        $selectedDay
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Signature enregistrée avec succès.'
    ]);

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        jsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
    } else {
        jsonResponse(['error' => 'Erreur lors de l\'enregistrement. Veuillez réessayer.'], 500);
    }
}
