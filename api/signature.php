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

// Valider les jours sélectionnés
if (empty($signedDays) || !is_array($signedDays)) {
    jsonResponse(['error' => 'Veuillez sélectionner au moins un jour de présence.'], 400);
}

// Valider que les jours sélectionnés sont dans la plage de l'événement
$validDays = [];
$startDate = new DateTime($sheet['event_date']);
$endDate = new DateTime($sheet['end_date'] ?? $sheet['event_date']);

foreach ($signedDays as $day) {
    $dayDate = new DateTime($day);
    // Le jour doit être dans la plage de l'événement
    if ($dayDate >= $startDate && $dayDate <= $endDate) {
        $validDays[] = $day;
    }
}

if (empty($validDays)) {
    jsonResponse(['error' => 'Les jours sélectionnés ne sont pas valides pour cet événement.'], 400);
}

// Vérifier si l'email a déjà signé pour ces jours spécifiques
$placeholders = implode(',', array_fill(0, count($validDays), '?'));
$checkStmt = db()->prepare("
    SELECT signed_for_date FROM signatures
    WHERE sheet_id = ? AND email = ? AND signed_for_date IN ($placeholders)
");
$checkParams = array_merge([$sheet['id'], strtolower($email)], $validDays);
$checkStmt->execute($checkParams);
$alreadySigned = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

// Filtrer les jours déjà signés
$daysToSign = array_diff($validDays, $alreadySigned);

if (empty($daysToSign)) {
    if ($isMultiDay) {
        jsonResponse(['error' => 'Vous avez déjà signé pour tous les jours sélectionnés.'], 409);
    } else {
        jsonResponse(['error' => 'Cette adresse email a déjà signé cette feuille.'], 409);
    }
}

// Enregistrer la signature (une entrée par jour)
try {
    $stmt = db()->prepare("
        INSERT INTO signatures (
            sheet_id, first_name, last_name, email, phone, phone_secondary,
            function_title, structure, signature_data, ip_address, user_agent, signed_for_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $signaturesAdded = 0;
    foreach ($daysToSign as $day) {
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
            $day
        ]);
        $signaturesAdded++;
    }

    // Message de succès adapté
    if ($isMultiDay) {
        $dayWord = $signaturesAdded > 1 ? 'jours' : 'jour';
        $message = "Signature enregistrée pour $signaturesAdded $dayWord.";
        if (!empty($alreadySigned)) {
            $message .= " (Vous aviez déjà signé pour " . count($alreadySigned) . " autre(s) jour(s).)";
        }
    } else {
        $message = 'Signature enregistrée avec succès.';
    }

    jsonResponse([
        'success' => true,
        'message' => $message,
        'days_signed' => $signaturesAdded
    ]);

} catch (PDOException $e) {
    if (DEBUG_MODE) {
        jsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
    } else {
        jsonResponse(['error' => 'Erreur lors de l\'enregistrement. Veuillez réessayer.'], 500);
    }
}
