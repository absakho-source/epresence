<?php
/**
 * e-Présence DGPPE - Page de signature publique
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/structures.php';

$code = $_GET['code'] ?? '';

if (empty($code)) {
    $pageTitle = 'Erreur';
    $error = 'Code de feuille manquant.';
} else {
    // Récupérer la feuille avec la structure du créateur
    $stmt = db()->prepare("
        SELECT s.*, u.structure as creator_structure
        FROM sheets s
        JOIN users u ON s.user_id = u.id
        WHERE s.unique_code = ?
    ");
    $stmt->execute([$code]);
    $sheet = $stmt->fetch();

    if (!$sheet) {
        $pageTitle = 'Erreur';
        $error = 'Feuille d\'émargement non trouvée.';
    } elseif ($sheet['status'] !== 'active') {
        $pageTitle = 'Feuille clôturée';
        $error = 'Cette feuille d\'émargement est clôturée et n\'accepte plus de signatures.';
    } else {
        // Récupérer les documents attachés
        $docStmt = db()->prepare("SELECT * FROM sheet_documents WHERE sheet_id = ? ORDER BY document_type, uploaded_at");
        $docStmt->execute([$sheet['id']]);
        $documents = $docStmt->fetchAll();

        // Déterminer si c'est un événement multi-jours
        $isMultiDay = isset($sheet['end_date']) && $sheet['end_date'] && $sheet['end_date'] !== $sheet['event_date'];

        // Générer la liste des jours si multi-jours
        $eventDays = [];
        if ($isMultiDay) {
            $startDate = new DateTime($sheet['event_date']);
            $endDate = new DateTime($sheet['end_date']);
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
            foreach ($period as $date) {
                $eventDays[] = $date->format('Y-m-d');
            }
        } else {
            $eventDays[] = $sheet['event_date'];
        }
        // Déterminer le logo de structure du créateur (DGPPE, FONGIP, ANSD)
        $structureLogo = null;
        $creatorStructure = $sheet['creator_structure'] ?? '';
        if (!empty($creatorStructure)) {
            // DGPPE - toutes les structures de la DGPPE
            $dgppeKeywords = ['DGPPE', 'DAP/DGPPE', 'Direction de la Planification', 'Direction de la Prévision',
                              'DPEE', 'DDCH', 'CEPOD', 'CSI', 'UCSPE', 'SRP', 'Capital Humain'];
            foreach ($dgppeKeywords as $keyword) {
                if (stripos($creatorStructure, $keyword) !== false) {
                    $structureLogo = 'logo-dgppe.png';
                    break;
                }
            }

            // ANSD
            if (!$structureLogo && stripos($creatorStructure, 'ANSD') !== false) {
                $structureLogo = 'logo-ansd.png';
            }

            // FONGIP
            if (!$structureLogo && stripos($creatorStructure, 'FONGIP') !== false) {
                $structureLogo = 'logo-fongip.png';
            }
        }

        // Vérifier si la feuille doit être auto-clôturée
        if (isset($sheet['auto_close']) && $sheet['auto_close'] && isset($sheet['end_time']) && $sheet['end_time']) {
            $eventDate = $sheet['event_date'];
            $endTime = $sheet['end_time'];
            $endDateTime = strtotime($eventDate . ' ' . $endTime);
            $now = time();

            if ($now > $endDateTime) {
                // Clôturer automatiquement la feuille
                $updateStmt = db()->prepare("UPDATE sheets SET status = 'closed', closed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'active'");
                $updateStmt->execute([$sheet['id']]);

                $pageTitle = 'Feuille clôturée';
                $error = 'Cette feuille d\'émargement a été automatiquement clôturée à ' . formatTime($endTime) . '. Les signatures ne sont plus acceptées.';
            }
        }
    }
}

$pageTitle = $pageTitle ?? $sheet['title'];
$bodyClass = 'sign-page';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= sanitize($pageTitle) ?> | <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/img/<?= LOGO_DGPPE ?>">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/img/<?= LOGO_DGPPE ?>">
    <link rel="shortcut icon" href="<?= SITE_URL ?>/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body.sign-page {
            background: linear-gradient(135deg, #00703c 0%, #005a30 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .sign-card {
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .signature-canvas {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            background-color: #fff;
            touch-action: none;
            cursor: crosshair;
            width: 100%;
            height: 200px;
        }
        .signature-canvas.signing {
            border-color: #00703c;
            border-style: solid;
        }
        .event-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #00703c;
        }
        .sign-header {
            background: linear-gradient(135deg, #00703c 0%, #005a30 100%);
            border-radius: 20px 20px 0 0;
        }
        .sign-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 0 10px;
        }
        .sign-logos img {
            height: 45px;
            background: white;
            padding: 5px;
            border-radius: 5px;
        }
        .sign-logos .logo-placeholder {
            width: 55px;
        }
        .documents-section {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 15px;
        }
        .documents-section h6 {
            color: #00703c;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .doc-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .doc-list li {
            padding: 8px 10px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .doc-list li:last-child {
            margin-bottom: 0;
        }
        .doc-list .doc-icon {
            font-size: 1.2rem;
            color: #00703c;
        }
        .doc-list .doc-info {
            flex: 1;
            min-width: 0;
        }
        .doc-list .doc-name {
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .doc-list .doc-type {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .doc-list .btn-view {
            font-size: 0.8rem;
            padding: 4px 10px;
        }
        /* Phone input styling */
        .phone-country-code {
            flex-shrink: 0;
            max-width: 70px;
            text-align: center;
            font-weight: 500;
        }
        .phone-input {
            flex: 1;
            min-width: 0;
        }
        /* Days selection for multi-day events */
        .days-selection {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        .day-check {
            padding: 8px 12px;
            margin-bottom: 4px;
            background: white;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
        }
        .day-check:hover {
            border-color: #00703c;
        }
        .day-check.today {
            border-color: #00703c;
            background: #f0fff4;
        }
        .day-check .form-check-input:checked + .form-check-label {
            font-weight: 600;
            color: #00703c;
        }
        .day-check:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body class="sign-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <?php if (isset($error)): ?>
                    <!-- Error State -->
                    <div class="card sign-card text-center p-5">
                        <div class="card-body">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                            <h2 class="mt-4"><?= sanitize($pageTitle) ?></h2>
                            <p class="text-muted"><?= sanitize($error) ?></p>
                            <a href="<?= SITE_URL ?>" class="btn btn-primary mt-3">
                                <i class="bi bi-house me-2"></i>Retour à l'accueil
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Sign Form -->
                    <div class="card sign-card">
                        <div class="card-header sign-header text-center py-4">
                            <div class="sign-logos">
                                <img src="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>" alt="Logo MEPC">
                                <?php if (!empty($structureLogo)): ?>
                                <img src="<?= SITE_URL ?>/assets/img/<?= $structureLogo ?>" alt="Logo Structure">
                                <?php else: ?>
                                <span class="logo-placeholder"></span>
                                <?php endif; ?>
                            </div>
                            <h4 class="text-white mb-0">
                                <i class="bi bi-vector-pen me-2"></i>Feuille d'émargement
                            </h4>
                            <small class="text-white opacity-75"><?= ORG_NAME ?></small>
                        </div>
                        <div class="card-body p-4">
                            <!-- Event Info -->
                            <div class="event-info mb-4">
                                <h5 class="mb-2"><?= sanitize($sheet['title']) ?></h5>
                                <?php if (!empty($sheet['description'])): ?>
                                <p class="mb-2 small"><?= nl2br(sanitize($sheet['description'])) ?></p>
                                <?php endif; ?>
                                <div class="small text-muted">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?php if ($isMultiDay): ?>
                                        Du <?= formatDateFr($sheet['event_date']) ?> au <?= formatDateFr($sheet['end_date']) ?>
                                        <span class="badge bg-info ms-1"><?= count($eventDays) ?> jours</span>
                                    <?php else: ?>
                                        <?= formatDateFr($sheet['event_date']) ?>
                                    <?php endif; ?>
                                    <?php if ($sheet['event_time']): ?>
                                        à <?= formatTime($sheet['event_time']) ?>
                                        <?php if (isset($sheet['end_time']) && $sheet['end_time']): ?>
                                            - <?= formatTime($sheet['end_time']) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($sheet['location']): ?>
                                        <br><i class="bi bi-geo-alt me-1"></i><?= sanitize($sheet['location']) ?>
                                    <?php endif; ?>
                                    <?php if (isset($sheet['auto_close']) && $sheet['auto_close'] && isset($sheet['end_time']) && $sheet['end_time']): ?>
                                        <br><small class="text-warning">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            Signatures acceptées jusqu'à <?= formatTime($sheet['end_time']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($documents)): ?>
                            <!-- Documents attachés -->
                            <div class="documents-section">
                                <h6><i class="bi bi-folder2-open me-2"></i>Documents de la réunion</h6>
                                <ul class="doc-list">
                                    <?php foreach ($documents as $doc):
                                        $docTypeLabels = [
                                            'agenda' => 'Agenda',
                                            'tdr' => 'TDR',
                                            'report' => 'Rapport',
                                            'other' => 'Document'
                                        ];
                                        $docIcon = 'bi-file-earmark';
                                        if (str_contains($doc['file_type'], 'pdf')) {
                                            $docIcon = 'bi-file-earmark-pdf';
                                        } elseif (str_contains($doc['file_type'], 'word') || str_contains($doc['file_type'], 'document')) {
                                            $docIcon = 'bi-file-earmark-word';
                                        } elseif (str_contains($doc['file_type'], 'excel') || str_contains($doc['file_type'], 'sheet')) {
                                            $docIcon = 'bi-file-earmark-excel';
                                        } elseif (str_contains($doc['file_type'], 'image')) {
                                            $docIcon = 'bi-file-earmark-image';
                                        }
                                    ?>
                                    <li>
                                        <i class="bi <?= $docIcon ?> doc-icon"></i>
                                        <div class="doc-info">
                                            <div class="doc-name"><?= sanitize($doc['original_name']) ?></div>
                                            <div class="doc-type"><?= $docTypeLabels[$doc['document_type']] ?? 'Document' ?></div>
                                        </div>
                                        <a href="<?= SITE_URL ?>/api/document.php?id=<?= $doc['id'] ?>"
                                           target="_blank" class="btn btn-sm btn-outline-primary btn-view">
                                            <i class="bi bi-eye me-1"></i>Voir
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <!-- Sign Form -->
                            <form id="signForm" method="POST" action="../../api/signature.php">
                                <input type="hidden" name="sheet_code" value="<?= sanitize($code) ?>">
                                <?= csrfField() ?>

                                <div id="formErrors" class="alert alert-danger d-none"></div>

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required autocomplete="given-name">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required autocomplete="family-name">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-sm-6 mb-3">
                                        <label for="structure" class="form-label">Structure</label>
                                        <input type="text" class="form-control" id="structure" name="structure"
                                               list="structures-list" placeholder="Tapez pour rechercher..."
                                               autocomplete="organization">
                                        <datalist id="structures-list">
                                            <?php foreach (getStructuresGrouped() as $category => $structures): ?>
                                                <?php foreach ($structures as $name): ?>
                                            <option value="<?= sanitize($name) ?>">
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    <div class="col-12 col-sm-6 mb-3">
                                        <label for="function_title" class="form-label">Fonction</label>
                                        <input type="text" class="form-control" id="function_title" name="function_title" autocomplete="organization-title">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                                </div>

                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <label for="phone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control phone-country-code" id="phone_country" value="+221" maxlength="5">
                                            <input type="tel" class="form-control phone-input" id="phone" name="phone" required autocomplete="tel" placeholder="77 123 45 67">
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label for="phone_secondary" class="form-label">Tél. secondaire</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control phone-country-code" id="phone_secondary_country" value="+221" maxlength="5">
                                            <input type="tel" class="form-control phone-input" id="phone_secondary" name="phone_secondary" autocomplete="tel" placeholder="Optionnel">
                                        </div>
                                    </div>
                                </div>

                                <?php if ($isMultiDay): ?>
                                <!-- Sélection du jour (événement multi-jours) -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        Jour de signature <span class="text-danger">*</span>
                                        <small class="text-muted d-block">Sélectionnez le jour pour lequel vous signez</small>
                                    </label>
                                    <div class="days-selection">
                                        <?php
                                        $dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                                        $monthNames = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
                                        $today = date('Y-m-d');
                                        $hasToday = in_array($today, $eventDays);
                                        $firstDay = true;
                                        foreach ($eventDays as $index => $day):
                                            $dateObj = new DateTime($day);
                                            $dayNum = (int)$dateObj->format('w');
                                            $dayLabel = $dayNames[$dayNum] . ' ' . $dateObj->format('j') . ' ' . $monthNames[(int)$dateObj->format('n')];
                                            $isToday = $day === $today;
                                            // Présélectionner aujourd'hui si dans la période, sinon le premier jour
                                            $isChecked = $hasToday ? $isToday : $firstDay;
                                            $firstDay = false;
                                        ?>
                                        <div class="form-check day-check <?= $isToday ? 'today' : '' ?>">
                                            <input class="form-check-input" type="radio" name="signed_days[]"
                                                   value="<?= $day ?>" id="day_<?= $index ?>"
                                                   <?= $isChecked ? 'checked' : '' ?> required>
                                            <label class="form-check-label" for="day_<?= $index ?>">
                                                <?= $dayLabel ?>
                                                <?php if ($isToday): ?>
                                                    <span class="badge bg-success ms-1">Aujourd'hui</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted mt-1 d-block">
                                        <i class="bi bi-info-circle me-1"></i>Pour signer plusieurs jours, signez une fois par jour.
                                    </small>
                                </div>
                                <?php else: ?>
                                <input type="hidden" name="signed_days[]" value="<?= $sheet['event_date'] ?>">
                                <?php endif; ?>

                                <!-- Signature -->
                                <div class="mb-3">
                                    <label class="form-label">Signature <span class="text-danger">*</span></label>
                                    <canvas id="signatureCanvas" class="signature-canvas"></canvas>
                                    <input type="hidden" name="signature_data" id="signature_data">
                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle me-1"></i>Signez avec votre doigt ou souris
                                        </small>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearSignature">
                                            <i class="bi bi-eraser me-1"></i>Effacer
                                        </button>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                        <i class="bi bi-check-circle me-2"></i>Valider ma signature
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <small class="text-white opacity-75">
                        <?= SITE_NAME ?> - <?= ORG_FULL_NAME ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Clé localStorage pour les données du participant
        const STORAGE_KEY = 'epresence_participant_data';

        // Charger les données sauvegardées et préremplir le formulaire
        function loadSavedData() {
            try {
                const savedData = localStorage.getItem(STORAGE_KEY);
                if (savedData) {
                    const data = JSON.parse(savedData);

                    // Préremplir les champs si ils existent
                    if (data.first_name) document.getElementById('first_name').value = data.first_name;
                    if (data.last_name) document.getElementById('last_name').value = data.last_name;
                    if (data.email) document.getElementById('email').value = data.email;
                    if (data.structure) document.getElementById('structure').value = data.structure;
                    if (data.function_title) document.getElementById('function_title').value = data.function_title;

                    // Téléphones avec indicatifs
                    if (data.phone_country) document.getElementById('phone_country').value = data.phone_country;
                    if (data.phone) document.getElementById('phone').value = data.phone;
                    if (data.phone_secondary_country) document.getElementById('phone_secondary_country').value = data.phone_secondary_country;
                    if (data.phone_secondary) document.getElementById('phone_secondary').value = data.phone_secondary;

                    console.log('Données participant restaurées depuis le cache local');
                }
            } catch (e) {
                console.log('Impossible de charger les données sauvegardées:', e);
            }
        }

        // Sauvegarder les données du formulaire
        function saveFormData() {
            try {
                const data = {
                    first_name: document.getElementById('first_name').value,
                    last_name: document.getElementById('last_name').value,
                    email: document.getElementById('email').value,
                    structure: document.getElementById('structure').value,
                    function_title: document.getElementById('function_title').value,
                    phone_country: document.getElementById('phone_country').value,
                    phone: document.getElementById('phone').value.replace(/^\+\d+\s*/, ''), // Sans indicatif
                    phone_secondary_country: document.getElementById('phone_secondary_country').value,
                    phone_secondary: document.getElementById('phone_secondary').value.replace(/^\+\d+\s*/, ''),
                    saved_at: new Date().toISOString()
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                console.log('Données participant sauvegardées');
            } catch (e) {
                console.log('Impossible de sauvegarder les données:', e);
            }
        }

        // Charger les données au démarrage
        loadSavedData();

        // Formats par pays (indicatif => {maxDigits, format})
        const phoneFormats = {
            '+221': { maxDigits: 9, format: [2, 3, 2, 2] },       // Sénégal: XX XXX XX XX
            '+33': { maxDigits: 9, format: [1, 2, 2, 2, 2] },     // France: X XX XX XX XX
            '+1': { maxDigits: 10, format: [3, 3, 4] },           // USA/Canada: XXX XXX XXXX
            '+225': { maxDigits: 10, format: [2, 2, 2, 2, 2] },   // Côte d'Ivoire: XX XX XX XX XX
            '+223': { maxDigits: 8, format: [2, 2, 2, 2] },       // Mali: XX XX XX XX
            '+222': { maxDigits: 8, format: [2, 2, 2, 2] },       // Mauritanie: XX XX XX XX
            '+212': { maxDigits: 9, format: [1, 2, 2, 2, 2] },    // Maroc: X XX XX XX XX
            '+216': { maxDigits: 8, format: [2, 3, 3] },          // Tunisie: XX XXX XXX
            '+224': { maxDigits: 9, format: [3, 2, 2, 2] },       // Guinée: XXX XX XX XX
            '+237': { maxDigits: 9, format: [3, 2, 2, 2] },       // Cameroun: XXX XX XX XX
            '+229': { maxDigits: 8, format: [2, 2, 2, 2] },       // Bénin: XX XX XX XX
            '+228': { maxDigits: 8, format: [2, 2, 2, 2] },       // Togo: XX XX XX XX
            '+226': { maxDigits: 8, format: [2, 2, 2, 2] },       // Burkina: XX XX XX XX
            '+227': { maxDigits: 8, format: [2, 2, 2, 2] },       // Niger: XX XX XX XX
        };

        // Formatage automatique des numéros selon l'indicatif pays
        function formatPhoneNumber(value, countryCode) {
            // Supprimer tout sauf les chiffres
            let digits = value.replace(/\D/g, '');

            // Obtenir le format pour ce pays (ou format par défaut)
            const formatConfig = phoneFormats[countryCode] || { maxDigits: 12, format: null };

            // Limiter les chiffres
            digits = digits.substring(0, formatConfig.maxDigits);

            // Si pas de format défini, retourner les chiffres sans formatage
            if (!formatConfig.format) {
                return digits;
            }

            // Appliquer le format avec espaces
            let formatted = '';
            let pos = 0;
            for (let i = 0; i < formatConfig.format.length && pos < digits.length; i++) {
                const chunk = digits.substring(pos, pos + formatConfig.format[i]);
                if (chunk) {
                    if (formatted) formatted += ' ';
                    formatted += chunk;
                    pos += formatConfig.format[i];
                }
            }

            return formatted;
        }

        // S'assurer que l'indicatif commence par +
        document.querySelectorAll('.phone-country-code').forEach(input => {
            input.addEventListener('input', function() {
                let val = this.value.replace(/[^0-9+]/g, '');
                if (!val.startsWith('+')) {
                    val = '+' + val.replace(/\+/g, '');
                }
                this.value = val;

                // Re-formater le numéro associé quand l'indicatif change
                const phoneInputId = this.id.replace('_country', '');
                const phoneInput = document.getElementById(phoneInputId);
                if (phoneInput && phoneInput.value) {
                    phoneInput.value = formatPhoneNumber(phoneInput.value, val);
                }
            });
        });

        // Appliquer le formatage aux champs téléphone
        document.querySelectorAll('.phone-input').forEach(input => {
            input.addEventListener('input', function(e) {
                const cursorPos = this.selectionStart;
                const oldLength = this.value.length;

                // Trouver l'indicatif pays associé
                const countryInputId = this.id + '_country';
                const countryInput = document.getElementById(countryInputId);
                const countryCode = countryInput ? countryInput.value : '+221';

                this.value = formatPhoneNumber(this.value, countryCode);

                // Ajuster la position du curseur
                const newLength = this.value.length;
                const newPos = cursorPos + (newLength - oldLength);
                this.setSelectionRange(newPos, newPos);
            });
        });

        const canvas = document.getElementById('signatureCanvas');
        if (!canvas) return;

        // Initialize signature pad
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)',
            minWidth: 1,
            maxWidth: 3
        });

        // Resize canvas
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear();
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Visual feedback
        canvas.addEventListener('pointerdown', () => canvas.classList.add('signing'));
        canvas.addEventListener('pointerup', () => canvas.classList.remove('signing'));

        // Clear signature
        document.getElementById('clearSignature').addEventListener('click', function() {
            signaturePad.clear();
        });

        // Form submission
        document.getElementById('signForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const errorsDiv = document.getElementById('formErrors');
            const submitBtn = document.getElementById('submitBtn');

            errorsDiv.classList.add('d-none');

            // Validate signature
            if (signaturePad.isEmpty()) {
                errorsDiv.textContent = 'Veuillez signer avant de valider.';
                errorsDiv.classList.remove('d-none');
                return;
            }

            // Set signature data
            document.getElementById('signature_data').value = signaturePad.toDataURL('image/png');

            // Prepend country codes to phone numbers (only if not already prepended)
            const phoneInput = document.getElementById('phone');
            const phoneCountry = document.getElementById('phone_country');
            const phoneSecondary = document.getElementById('phone_secondary');
            const phoneSecondaryCountry = document.getElementById('phone_secondary_country');

            // Sauvegarder les valeurs originales pour les restaurer en cas d'erreur
            const originalPhone = phoneInput.value;
            const originalPhoneSecondary = phoneSecondary.value;

            // Ne pas ajouter l'indicatif s'il est déjà présent
            if (phoneInput.value && !phoneInput.value.startsWith('+')) {
                phoneInput.value = phoneCountry.value + ' ' + phoneInput.value;
            }
            if (phoneSecondary.value && !phoneSecondary.value.startsWith('+')) {
                phoneSecondary.value = phoneSecondaryCountry.value + ' ' + phoneSecondary.value;
            }

            // Disable button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner me-2"></span>Validation...';

            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin', // Important pour envoyer les cookies sur mobile
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Sauvegarder les données pour le prochain usage
                    saveFormData();
                    window.location.href = 'confirm.php?code=<?= urlencode($code) ?>';
                } else {
                    // Si session expirée, proposer de recharger la page
                    if (data.error && data.error.includes('Session expirée')) {
                        if (confirm('Votre session a expiré. Voulez-vous recharger la page pour réessayer ?')) {
                            location.reload();
                            return;
                        }
                    }
                    // Restaurer les valeurs originales
                    phoneInput.value = originalPhone;
                    phoneSecondary.value = originalPhoneSecondary;

                    errorsDiv.innerHTML = data.errors ? data.errors.join('<br>') : data.error;
                    errorsDiv.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Valider ma signature';
                }
            } catch (error) {
                // Restaurer les valeurs originales
                phoneInput.value = originalPhone;
                phoneSecondary.value = originalPhoneSecondary;

                errorsDiv.textContent = 'Erreur de connexion. Vérifiez votre connexion internet et réessayez.';
                errorsDiv.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Valider ma signature';
            }
        });
    });
    </script>
</body>
</html>
