<?php
/**
 * e-Présence - Créer une feuille d'émargement
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'event_date' => date('Y-m-d'),
    'end_date' => '', // Vide par défaut = événement d'un jour
    'event_time' => '',
    'end_time' => '',
    'auto_close' => false,
    'location' => ''
];

// Types de fichiers autorisés
$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/vnd.oasis.opendocument.presentation',
    'image/jpeg',
    'image/png',
    'image/gif'
];
$maxFileSize = 10 * 1024 * 1024; // 10 MB
$uploadDir = __DIR__ . '/../../uploads/documents/';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = "Session expirée. Veuillez réessayer.";
    } else {
        // Récupérer les données
        $formData = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'event_time' => $_POST['event_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'auto_close' => isset($_POST['auto_close']),
            'location' => trim($_POST['location'] ?? '')
        ];

        // Si end_date n'est pas spécifié, utiliser event_date
        if (empty($formData['end_date'])) {
            $formData['end_date'] = $formData['event_date'];
        }

        // Valider que end_date >= event_date
        if ($formData['end_date'] < $formData['event_date']) {
            $errors[] = "La date de fin ne peut pas être antérieure à la date de début.";
        }

        // Validation
        if (empty($formData['title'])) {
            $errors[] = "Le titre est obligatoire.";
        }

        if (empty($formData['event_date'])) {
            $errors[] = "La date est obligatoire.";
        }

        // L'heure n'est obligatoire que pour les événements d'un seul jour
        $isMultiDay = $formData['end_date'] !== $formData['event_date'];
        if (!$isMultiDay && empty($formData['event_time'])) {
            $errors[] = "L'heure de début est obligatoire.";
        }

        if (empty($formData['location'])) {
            $errors[] = "Le lieu est obligatoire.";
        }

        if (empty($errors)) {
            // Générer un code unique
            $uniqueCode = generateUniqueCode();

            // Vérifier l'unicité du code
            $checkStmt = db()->prepare("SELECT id FROM sheets WHERE unique_code = ?");
            $checkStmt->execute([$uniqueCode]);
            while ($checkStmt->fetch()) {
                $uniqueCode = generateUniqueCode();
                $checkStmt->execute([$uniqueCode]);
            }

            // Récupérer les infos du créateur pour les stocker dans la feuille
            $currentUser = getCurrentUser();
            $creatorName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
            $creatorStructure = $currentUser['structure'];

            try {
                $stmt = db()->prepare("
                    INSERT INTO sheets (user_id, title, description, event_date, end_date, event_time, end_time, auto_close, location, unique_code, creator_name, creator_structure)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $autoCloseValue = $formData['auto_close'] ? 't' : 'f';
                $stmt->execute([
                    getCurrentUserId(),
                    $formData['title'],
                    $formData['description'] ?: null,
                    $formData['event_date'],
                    $formData['end_date'] ?: null,
                    $formData['event_time'] ?: null,
                    $formData['end_time'] ?: null,
                    $autoCloseValue,
                    $formData['location'],
                    $uniqueCode,
                    $creatorName,
                    $creatorStructure
                ]);

                $sheetId = db()->lastInsertId();

                // Traiter les fichiers uploadés
                if (!empty($_FILES['documents']['name'][0])) {
                    $uploadedFiles = 0;
                    $fileCount = count($_FILES['documents']['name']);

                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['documents']['tmp_name'][$i];
                            $originalName = $_FILES['documents']['name'][$i];
                            $fileSize = $_FILES['documents']['size'][$i];
                            $mimeType = mime_content_type($tmpName);

                            // Vérifier le type de fichier
                            if (!in_array($mimeType, $allowedMimeTypes)) {
                                continue; // Ignorer les fichiers non autorisés
                            }

                            // Vérifier la taille
                            if ($fileSize > $maxFileSize) {
                                continue; // Ignorer les fichiers trop gros
                            }

                            // Générer un nom unique
                            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                            $storedName = uniqid('doc_') . '_' . time() . '.' . $extension;
                            $targetPath = $uploadDir . $storedName;

                            // Déplacer le fichier
                            if (move_uploaded_file($tmpName, $targetPath)) {
                                // Déterminer le type de document
                                $docType = $_POST['document_types'][$i] ?? 'other';

                                // Enregistrer en base
                                $docStmt = db()->prepare("
                                    INSERT INTO sheet_documents (sheet_id, original_name, stored_name, file_type, file_size, document_type)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $docStmt->execute([
                                    $sheetId,
                                    $originalName,
                                    $storedName,
                                    $mimeType,
                                    $fileSize,
                                    $docType
                                ]);
                                $uploadedFiles++;
                            }
                        }
                    }
                }

                setFlash('success', 'Feuille d\'émargement créée avec succès !');
                redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);

            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la création. Veuillez réessayer.";
                if (DEBUG_MODE) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }

    regenerateCsrfToken();
}

$pageTitle = 'Nouvelle feuille';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0">Créer une feuille d'émargement</h1>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="title" class="form-label">Titre de la réunion <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?= sanitize($formData['title']) ?>" required
                               placeholder="Ex: Réunion de service, Comité de pilotage...">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="3" placeholder="Description optionnelle de la réunion..."><?= sanitize($formData['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="event_date" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event_date" name="event_date"
                                   value="<?= sanitize($formData['event_date']) ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="end_date" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date"
                                   value="<?= sanitize($formData['end_date']) ?>">
                            <div class="form-text">Laisser vide si événement d'un jour.</div>
                        </div>
                        <div class="col-md-3 mb-3 time-fields">
                            <label for="event_time" class="form-label">Heure de début <span class="text-danger time-required">*</span></label>
                            <input type="time" class="form-control" id="event_time" name="event_time"
                                   value="<?= sanitize($formData['event_time']) ?>">
                        </div>
                        <div class="col-md-3 mb-3 time-fields">
                            <label for="end_time" class="form-label">Heure de fin</label>
                            <input type="time" class="form-control" id="end_time" name="end_time"
                                   value="<?= sanitize($formData['end_time']) ?>">
                        </div>
                    </div>

                    <div class="mb-3 time-fields">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_close" name="auto_close"
                                   <?= $formData['auto_close'] ? 'checked' : '' ?>
                                   <?= empty($formData['end_time']) ? 'disabled' : '' ?>>
                            <label class="form-check-label <?= empty($formData['end_time']) ? 'text-muted' : '' ?>" for="auto_close">
                                Clôturer automatiquement à l'heure de fin
                            </label>
                            <div class="form-text" id="auto_close_hint">
                                <?= empty($formData['end_time']) ? 'Définissez d\'abord une heure de fin pour activer cette option.' : 'Si coché, les signatures ne seront plus acceptées après l\'heure de fin.' ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="location" class="form-label">Lieu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="location" name="location"
                               value="<?= sanitize($formData['location']) ?>"
                               placeholder="Ex: Salle de réunion A, Bâtiment principal..." required>
                    </div>

                    <!-- Section Documents -->
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-paperclip me-1"></i>Documents joints
                        </label>
                        <div class="card bg-light">
                            <div class="card-body">
                                <div id="documents-container">
                                    <div class="document-row mb-2">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-5">
                                                <input type="file" class="form-control form-control-sm" name="documents[]"
                                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.jpg,.jpeg,.png,.gif">
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" name="document_types[]">
                                                    <option value="agenda">Agenda / Ordre du jour</option>
                                                    <option value="tdr">Termes de référence (TDR)</option>
                                                    <option value="report">Rapport / Compte-rendu</option>
                                                    <option value="other">Autre document</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-doc" style="display:none;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="add-document">
                                    <i class="bi bi-plus-circle me-1"></i>Ajouter un document
                                </button>
                                <div class="form-text mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Formats acceptés : PDF, Word, Excel, PowerPoint, images. Taille max : 10 Mo par fichier.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Créer la feuille
                        </button>
                        <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary btn-lg">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du checkbox auto_close en fonction de l'heure de fin
    const eventTimeInput = document.getElementById('event_time');
    const endTimeInput = document.getElementById('end_time');
    const autoCloseCheckbox = document.getElementById('auto_close');
    const autoCloseLabel = document.querySelector('label[for="auto_close"]');
    const autoCloseHint = document.getElementById('auto_close_hint');
    const eventDateInput = document.getElementById('event_date');
    const endDateInput = document.getElementById('end_date');

    function updateAutoCloseState() {
        const hasEndTime = endTimeInput.value !== '';
        autoCloseCheckbox.disabled = !hasEndTime;
        if (!hasEndTime) {
            autoCloseCheckbox.checked = false;
            autoCloseLabel.classList.add('text-muted');
            autoCloseHint.textContent = "Définissez d'abord une heure de fin pour activer cette option.";
        } else {
            autoCloseLabel.classList.remove('text-muted');
            autoCloseHint.textContent = "Si coché, les signatures ne seront plus acceptées après l'heure de fin.";
        }
    }

    // Synchroniser end_date min avec event_date
    function syncEndDate() {
        // Ne forcer la valeur que si elle est antérieure à event_date (pas si vide)
        if (endDateInput.value && endDateInput.value < eventDateInput.value) {
            endDateInput.value = eventDateInput.value;
        }
        endDateInput.min = eventDateInput.value;
    }

    // Masquer/afficher les champs d'heure selon si c'est multi-jours
    const timeFields = document.querySelectorAll('.time-fields');
    const timeRequired = document.querySelectorAll('.time-required');

    function updateTimeFieldsVisibility() {
        // Multi-jours si end_date est renseigné ET différent de event_date
        const isMultiDay = endDateInput.value && endDateInput.value !== eventDateInput.value;
        timeFields.forEach(field => {
            field.style.display = isMultiDay ? 'none' : '';
        });
        // Rendre l'heure non obligatoire pour multi-jours
        eventTimeInput.required = !isMultiDay;
        timeRequired.forEach(el => {
            el.style.display = isMultiDay ? 'none' : '';
        });
        if (isMultiDay) {
            eventTimeInput.value = '';
            endTimeInput.value = '';
            autoCloseCheckbox.checked = false;
        }
    }

    function onEventDateChange() {
        syncEndDate();
        updateTimeFieldsVisibility();
    }
    function onEndDateChange() {
        if (endDateInput.value && endDateInput.value < eventDateInput.value) {
            endDateInput.value = eventDateInput.value;
        }
        updateTimeFieldsVisibility();
    }

    eventDateInput.addEventListener('change', onEventDateChange);
    eventDateInput.addEventListener('input', onEventDateChange);
    eventDateInput.addEventListener('keyup', onEventDateChange);
    endDateInput.addEventListener('change', onEndDateChange);
    endDateInput.addEventListener('input', onEndDateChange);
    endDateInput.addEventListener('keyup', onEndDateChange);

    // Initialiser
    syncEndDate();
    updateTimeFieldsVisibility();

    endTimeInput.addEventListener('change', updateAutoCloseState);
    endTimeInput.addEventListener('input', updateAutoCloseState);

    // Gestion des documents
    const container = document.getElementById('documents-container');
    const addBtn = document.getElementById('add-document');

    function updateRemoveButtons() {
        const rows = container.querySelectorAll('.document-row');
        rows.forEach((row, index) => {
            const removeBtn = row.querySelector('.remove-doc');
            if (removeBtn) {
                removeBtn.style.display = rows.length > 1 ? 'inline-block' : 'none';
            }
        });
    }

    addBtn.addEventListener('click', function() {
        const firstRow = container.querySelector('.document-row');
        const newRow = firstRow.cloneNode(true);

        // Reset values
        newRow.querySelector('input[type="file"]').value = '';
        newRow.querySelector('select').selectedIndex = 0;

        container.appendChild(newRow);
        updateRemoveButtons();
    });

    container.addEventListener('click', function(e) {
        if (e.target.closest('.remove-doc')) {
            const row = e.target.closest('.document-row');
            if (container.querySelectorAll('.document-row').length > 1) {
                row.remove();
                updateRemoveButtons();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
