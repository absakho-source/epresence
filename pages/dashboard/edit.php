<?php
/**
 * e-Présence - Modifier une feuille d'émargement
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);
$currentUser = getCurrentUser();
$isGlobalAdmin = isAdmin();

// Récupérer la feuille (admins peuvent accéder à toutes les feuilles)
if ($isGlobalAdmin) {
    $stmt = db()->prepare("SELECT * FROM sheets WHERE id = ?");
    $stmt->execute([$sheetId]);
} else {
    $stmt = db()->prepare("SELECT * FROM sheets WHERE id = ? AND user_id = ?");
    $stmt->execute([$sheetId, getCurrentUserId()]);
}
$sheet = $stmt->fetch();

if (!$sheet) {
    setFlash('error', 'Feuille non trouvée ou accès refusé.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

if ($sheet['status'] !== 'active') {
    setFlash('error', 'Impossible de modifier une feuille clôturée ou archivée.');
    redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
}

// Récupérer les documents existants
$documentsStmt = db()->prepare("SELECT * FROM sheet_documents WHERE sheet_id = ? ORDER BY document_type, uploaded_at");
$documentsStmt->execute([$sheetId]);
$existingDocuments = $documentsStmt->fetchAll();

// Configuration upload
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
$maxFileSize = MAX_DOCUMENT_SIZE;
$uploadDir = DOCUMENTS_PATH . '/';

$errors = [];
$formData = [
    'title' => $sheet['title'],
    'description' => $sheet['description'],
    'event_date' => $sheet['event_date'],
    'event_time' => $sheet['event_time'],
    'end_time' => isset($sheet['end_time']) ? $sheet['end_time'] : '',
    'auto_close' => isset($sheet['auto_close']) ? $sheet['auto_close'] : false,
    'location' => $sheet['location']
];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = "Session expirée. Veuillez réessayer.";
    } else {
        $formData = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'event_time' => $_POST['event_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'auto_close' => isset($_POST['auto_close']),
            'location' => trim($_POST['location'] ?? '')
        ];

        if (empty($formData['title'])) {
            $errors[] = "Le titre est obligatoire.";
        }

        if (empty($formData['event_date'])) {
            $errors[] = "La date est obligatoire.";
        }

        if (empty($formData['event_time'])) {
            $errors[] = "L'heure de début est obligatoire.";
        }

        if (empty($formData['location'])) {
            $errors[] = "Le lieu est obligatoire.";
        }

        if (empty($errors)) {
            try {
                $stmt = db()->prepare("
                    UPDATE sheets SET
                        title = ?,
                        description = ?,
                        event_date = ?,
                        event_time = ?,
                        end_time = ?,
                        auto_close = ?,
                        location = ?
                    WHERE id = ?
                ");
                $autoCloseValue = $formData['auto_close'] ? 't' : 'f';
                $stmt->execute([
                    $formData['title'],
                    $formData['description'] ?: null,
                    $formData['event_date'],
                    $formData['event_time'],
                    $formData['end_time'] ?: null,
                    $autoCloseValue,
                    $formData['location'],
                    $sheetId
                ]);

                // Traiter les nouveaux fichiers uploadés
                if (!empty($_FILES['documents']['name'][0])) {
                    // Vérifier que le dossier d'upload existe et est accessible
                    if (!is_dir($uploadDir)) {
                        if (!@mkdir($uploadDir, 0755, true)) {
                            $errors[] = "Impossible de créer le dossier d'upload. Contactez l'administrateur.";
                        }
                    }

                    if (!is_writable($uploadDir)) {
                        $errors[] = "Le dossier d'upload n'est pas accessible en écriture.";
                    }

                    $fileCount = count($_FILES['documents']['name']);

                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['documents']['tmp_name'][$i];
                            $originalName = $_FILES['documents']['name'][$i];
                            $fileSize = $_FILES['documents']['size'][$i];
                            $mimeType = mime_content_type($tmpName);

                            if (!in_array($mimeType, $allowedMimeTypes)) {
                                continue;
                            }

                            if ($fileSize > $maxFileSize) {
                                continue;
                            }

                            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                            $storedName = uniqid('doc_') . '_' . time() . '.' . $extension;
                            $targetPath = $uploadDir . $storedName;

                            if (move_uploaded_file($tmpName, $targetPath)) {
                                $docType = $_POST['document_types'][$i] ?? 'other';

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
                            }
                        }
                    }
                }

                setFlash('success', 'Feuille modifiée avec succès.');
                redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);

            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la modification. Veuillez réessayer.";
            }
        }
    }

    regenerateCsrfToken();
}

$pageTitle = 'Modifier - ' . $sheet['title'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheetId ?>" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0">Modifier la feuille</h1>
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
                               value="<?= sanitize($formData['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="3"><?= sanitize($formData['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="event_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event_date" name="event_date"
                                   value="<?= sanitize($formData['event_date']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="event_time" class="form-label">Heure de début <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="event_time" name="event_time"
                                   value="<?= sanitize($formData['event_time']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="end_time" class="form-label">Heure de fin</label>
                            <input type="time" class="form-control" id="end_time" name="end_time"
                                   value="<?= sanitize($formData['end_time']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
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
                               value="<?= sanitize($formData['location']) ?>" required>
                    </div>

                    <!-- Documents existants -->
                    <?php if (!empty($existingDocuments)): ?>
                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-folder2-open me-1"></i>Documents existants</label>
                        <div class="list-group">
                            <?php foreach ($existingDocuments as $doc):
                                $docTypeLabels = [
                                    'agenda' => 'Agenda',
                                    'tdr' => 'TDR',
                                    'report' => 'Rapport',
                                    'other' => 'Document'
                                ];
                                $docIcon = 'bi-file-earmark';
                                if (str_contains($doc['file_type'], 'pdf')) {
                                    $docIcon = 'bi-file-earmark-pdf text-danger';
                                } elseif (str_contains($doc['file_type'], 'word') || str_contains($doc['file_type'], 'document')) {
                                    $docIcon = 'bi-file-earmark-word text-primary';
                                } elseif (str_contains($doc['file_type'], 'excel') || str_contains($doc['file_type'], 'sheet')) {
                                    $docIcon = 'bi-file-earmark-excel text-success';
                                } elseif (str_contains($doc['file_type'], 'image')) {
                                    $docIcon = 'bi-file-earmark-image text-info';
                                }
                            ?>
                            <div class="list-group-item d-flex align-items-center">
                                <i class="bi <?= $docIcon ?> me-2"></i>
                                <div class="flex-grow-1 me-2">
                                    <span class="text-truncate d-block" style="max-width: 300px;">
                                        <?= sanitize($doc['original_name']) ?>
                                    </span>
                                    <small class="text-muted"><?= $docTypeLabels[$doc['document_type']] ?? 'Document' ?></small>
                                </div>
                                <a href="<?= SITE_URL ?>/api/document.php?id=<?= $doc['id'] ?>"
                                   target="_blank" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Pour supprimer un document, utilisez la page de visualisation de la feuille.
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Ajouter de nouveaux documents -->
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-paperclip me-1"></i>Ajouter des documents
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
                            <i class="bi bi-check-circle me-2"></i>Enregistrer
                        </button>
                        <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheetId ?>" class="btn btn-outline-secondary btn-lg">
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
    const endTimeInput = document.getElementById('end_time');
    const autoCloseCheckbox = document.getElementById('auto_close');
    const autoCloseLabel = document.querySelector('label[for="auto_close"]');
    const autoCloseHint = document.getElementById('auto_close_hint');

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
