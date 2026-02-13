<?php
/**
 * e-Présence - Voir une feuille d'émargement
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);
$currentUser = getCurrentUser();
$isStructureAdmin = !empty($currentUser['is_structure_admin']) && !empty($currentUser['structure']);
$isStructureView = isset($_GET['structure']) && $_GET['structure'] == '1';

// Récupérer la feuille avec infos du créateur
$stmt = db()->prepare("
    SELECT s.*, u.first_name as creator_first_name, u.last_name as creator_last_name, u.structure as creator_structure
    FROM sheets s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$sheetId]);
$sheet = $stmt->fetch();

if (!$sheet) {
    setFlash('error', 'Feuille non trouvée.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

// Vérifier les droits d'accès
$isOwner = ($sheet['user_id'] == getCurrentUserId());
$canViewAsStructureAdmin = false;
$canViewAsDGAdmin = false;
$canViewAsGlobalAdmin = isAdmin();

if (!$isOwner && $isStructureAdmin) {
    $userCategory = getStructureCategory($currentUser['structure']);
    $sheetCategory = getStructureCategory($sheet['creator_structure']);

    // Super-utilisateur de catégorie (Services propres ou Direction Générale)
    // peut voir TOUTES les feuilles de sa catégorie
    $isCategorySuperAdmin = isCategorySuperStructure($currentUser['structure']);

    if ($isCategorySuperAdmin && $userCategory === $sheetCategory) {
        $canViewAsDGAdmin = true;
    } else {
        // Vérifier si le créateur appartient à la même catégorie de structure
        $structureCodes = getStructureCodesInCategory($currentUser['structure']);
        $canViewAsStructureAdmin = in_array($sheet['creator_structure'], $structureCodes);
    }
}

if (!$isOwner && !$canViewAsStructureAdmin && !$canViewAsDGAdmin && !$canViewAsGlobalAdmin) {
    setFlash('error', 'Vous n\'avez pas accès à cette feuille.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

// Déterminer si c'est un événement multi-jours
$isMultiDay = !empty($sheet['end_date']) && $sheet['end_date'] !== $sheet['event_date'];

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

// Récupérer les signatures
$signaturesStmt = db()->prepare("
    SELECT * FROM signatures
    WHERE sheet_id = ?
    ORDER BY signed_for_date ASC, signed_at ASC
");
$signaturesStmt->execute([$sheetId]);
$signatures = $signaturesStmt->fetchAll();

// Grouper les signatures par jour pour les événements multi-jours
$signaturesByDay = [];
foreach ($eventDays as $day) {
    $signaturesByDay[$day] = [];
}
foreach ($signatures as $sig) {
    $day = $sig['signed_for_date'] ?? $sheet['event_date'];
    if (!isset($signaturesByDay[$day])) {
        $signaturesByDay[$day] = [];
    }
    $signaturesByDay[$day][] = $sig;
}

// Récupérer les documents attachés
$documentsStmt = db()->prepare("
    SELECT * FROM sheet_documents
    WHERE sheet_id = ?
    ORDER BY document_type, uploaded_at
");
$documentsStmt->execute([$sheetId]);
$documents = $documentsStmt->fetchAll();

// Droits de gestion des signatures (propriétaire, admin, ou super-utilisateur de la structure)
$canManageSignatures = $isOwner || $canViewAsGlobalAdmin || $canViewAsStructureAdmin || $canViewAsDGAdmin;

// Traitement des actions (propriétaire ou admin)
$canManage = $isOwner || $canViewAsGlobalAdmin;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    // Actions sur les signatures (accessible aux gestionnaires de signatures)
    if ($canManageSignatures) {
        if ($action === 'edit_signature') {
            $sigId = intval($_POST['signature_id'] ?? 0);
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $functionTitle = trim($_POST['function_title'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $phoneSecondary = trim($_POST['phone_secondary'] ?? '');
            $structure = trim($_POST['structure'] ?? '');

            if ($sigId > 0 && $firstName && $lastName) {
                // Vérifier que la signature appartient à cette feuille
                $checkStmt = db()->prepare("SELECT id FROM signatures WHERE id = ? AND sheet_id = ?");
                $checkStmt->execute([$sigId, $sheetId]);
                if ($checkStmt->fetch()) {
                    $updateStmt = db()->prepare("
                        UPDATE signatures
                        SET first_name = ?, last_name = ?, function_title = ?, phone = ?, phone_secondary = ?, structure = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$firstName, $lastName, $functionTitle, $phone, $phoneSecondary, $structure, $sigId]);
                    setFlash('success', 'Signature modifiée avec succès.');
                }
            }
            redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
        }

        if ($action === 'delete_signature') {
            $sigId = intval($_POST['signature_id'] ?? 0);
            if ($sigId > 0) {
                // Vérifier que la signature appartient à cette feuille
                $checkStmt = db()->prepare("SELECT id FROM signatures WHERE id = ? AND sheet_id = ?");
                $checkStmt->execute([$sigId, $sheetId]);
                if ($checkStmt->fetch()) {
                    $deleteStmt = db()->prepare("DELETE FROM signatures WHERE id = ?");
                    $deleteStmt->execute([$sigId]);
                    setFlash('success', 'Signature supprimée avec succès.');
                }
            }
            redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
        }
    }

    // Actions de gestion de la feuille (propriétaire ou admin global uniquement)
    if (!$canManage) {
        regenerateCsrfToken();
    }

    if ($action === 'close' && $sheet['status'] === 'active') {
        $updateStmt = db()->prepare("UPDATE sheets SET status = 'closed', closed_at = CURRENT_TIMESTAMP, closed_by = ? WHERE id = ?");
        $updateStmt->execute([getCurrentUserId(), $sheetId]);
        setFlash('success', 'Feuille clôturée avec succès.');
        redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
    }

    if ($action === 'reopen' && $sheet['status'] === 'closed') {
        $updateStmt = db()->prepare("UPDATE sheets SET status = 'active', closed_at = NULL, closed_by = NULL WHERE id = ?");
        $updateStmt->execute([$sheetId]);
        setFlash('success', 'Feuille réouverte avec succès.');
        redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
    }

    if ($action === 'delete') {
        // Supprimer les fichiers physiques des documents
        $docsStmt = db()->prepare("SELECT stored_name FROM sheet_documents WHERE sheet_id = ?");
        $docsStmt->execute([$sheetId]);
        while ($docFile = $docsStmt->fetch()) {
            $filePath = DOCUMENTS_PATH . '/' . $docFile['stored_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $deleteStmt = db()->prepare("DELETE FROM sheets WHERE id = ?");
        $deleteStmt->execute([$sheetId]);
        setFlash('success', 'Feuille supprimée avec succès.');
        redirect(SITE_URL . '/pages/dashboard/index.php');
    }

    if ($action === 'delete_document') {
        $docId = intval($_POST['document_id'] ?? 0);
        if ($docId > 0) {
            // Récupérer le nom du fichier
            $docStmt = db()->prepare("SELECT stored_name FROM sheet_documents WHERE id = ? AND sheet_id = ?");
            $docStmt->execute([$docId, $sheetId]);
            $docFile = $docStmt->fetch();

            if ($docFile) {
                // Supprimer le fichier physique
                $filePath = DOCUMENTS_PATH . '/' . $docFile['stored_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Supprimer de la base
                $deleteDocStmt = db()->prepare("DELETE FROM sheet_documents WHERE id = ?");
                $deleteDocStmt->execute([$docId]);
                setFlash('success', 'Document supprimé.');
            }
        }
        redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
    }

    regenerateCsrfToken();
}

$signUrl = getSheetUrl($sheet['unique_code']);

$pageTitle = $sheet['title'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div class="flex-grow-1">
        <h1 class="h3 mb-1"><?= sanitize($sheet['title']) ?></h1>
        <span class="badge badge-<?= $sheet['status'] ?>">
            <?= match($sheet['status']) {
                'active' => 'Active',
                'closed' => 'Clôturée',
                'archived' => 'Archivée',
                default => $sheet['status']
            } ?>
        </span>
        <?php if (!$isOwner): ?>
            <span class="badge bg-warning text-dark ms-1">
                <i class="bi bi-people me-1"></i>Feuille de structure
            </span>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isOwner): ?>
<div class="alert <?= $canViewAsDGAdmin ? 'alert-danger' : 'alert-warning' ?> mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-<?= $canViewAsDGAdmin ? 'shield-lock' : 'info-circle' ?> me-2"></i>
        <div>
            <strong>Consultation en mode <?= $canViewAsDGAdmin ? 'Direction générale' : 'super-utilisateur' ?></strong><br>
            <small>Cette feuille a été créée par <?= sanitize($sheet['creator_first_name'] . ' ' . $sheet['creator_last_name']) ?>
            <?php if (!empty($sheet['creator_structure'])): ?>
                (<?= sanitize(getStructureName($sheet['creator_structure'])) ?>)
            <?php endif; ?>
            . Seul le créateur peut la modifier.</small>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Informations et QR Code -->
    <div class="col-lg-4 mb-4">
        <!-- Infos -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($sheet['description'])): ?>
                <p class="mb-3">
                    <i class="bi bi-text-paragraph me-2 text-muted"></i>
                    <?= nl2br(sanitize($sheet['description'])) ?>
                </p>
                <hr class="my-2">
                <?php endif; ?>
                <p class="mb-2">
                    <i class="bi bi-calendar-event me-2 text-muted"></i>
                    <?php if ($isMultiDay): ?>
                        <strong>Dates :</strong> Du <?= formatDateFr($sheet['event_date']) ?> au <?= formatDateFr($sheet['end_date']) ?>
                        <span class="badge bg-info ms-1"><?= count($eventDays) ?> jours</span>
                    <?php else: ?>
                        <strong>Date :</strong> <?= formatDateFr($sheet['event_date']) ?>
                    <?php endif; ?>
                </p>
                <?php if ($sheet['event_time'] || (isset($sheet['end_time']) && $sheet['end_time'])): ?>
                <p class="mb-2">
                    <i class="bi bi-clock me-2 text-muted"></i>
                    <strong>Horaires :</strong>
                    <?php if ($sheet['event_time']): ?>
                        <?= formatTime($sheet['event_time']) ?>
                    <?php endif; ?>
                    <?php if (isset($sheet['end_time']) && $sheet['end_time']): ?>
                        - <?= formatTime($sheet['end_time']) ?>
                        <?php if (isset($sheet['auto_close']) && $sheet['auto_close']): ?>
                            <span class="badge bg-info ms-1" title="Les signatures seront refusées après cette heure">
                                <i class="bi bi-lock"></i> Auto
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
                <?php if ($sheet['location']): ?>
                    <p class="mb-2">
                        <i class="bi bi-geo-alt me-2 text-muted"></i>
                        <strong>Lieu :</strong> <?= sanitize($sheet['location']) ?>
                    </p>
                <?php endif; ?>
                <?php $attendanceRate = calculateAttendanceRate(count($signatures), $sheet['expected_participants'] ?? null); ?>
                <p class="mb-0">
                    <i class="bi bi-vector-pen me-2 text-muted"></i>
                    <strong>Signatures :</strong>
                    <?php if ($attendanceRate): ?>
                        <span class="badge bg-<?= $attendanceRate['badge'] ?>">
                            <?= $attendanceRate['ratio'] ?> (<?= $attendanceRate['percentage'] ?>%)
                        </span>
                    <?php else: ?>
                        <?= count($signatures) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Documents attachés -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-folder2-open me-2"></i>Documents (<?= count($documents) ?>)</h5>
                <?php if ($isOwner && $sheet['status'] === 'active'): ?>
                <a href="<?= SITE_URL ?>/pages/dashboard/edit.php?id=<?= $sheetId ?>" class="btn btn-sm btn-outline-primary" title="Ajouter des documents">
                    <i class="bi bi-plus-circle"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php if (!empty($documents)): ?>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($documents as $doc):
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
                    <li class="list-group-item d-flex align-items-center">
                        <i class="bi <?= $docIcon ?> me-2" style="font-size: 1.2rem;"></i>
                        <div class="flex-grow-1 me-2" style="min-width: 0;">
                            <div class="text-truncate" title="<?= sanitize($doc['original_name']) ?>">
                                <?= sanitize($doc['original_name']) ?>
                            </div>
                            <small class="text-muted"><?= $docTypeLabels[$doc['document_type']] ?? 'Document' ?></small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= SITE_URL ?>/api/document.php?id=<?= $doc['id'] ?>"
                               target="_blank" class="btn btn-outline-primary" title="Voir">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($isOwner): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Supprimer ce document ?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_document">
                                <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php else: ?>
            <div class="card-body text-center text-muted py-3">
                <i class="bi bi-folder2 d-block mb-2" style="font-size: 1.5rem;"></i>
                <small>Aucun document attaché</small>
            </div>
            <?php endif; ?>
        </div>

        <!-- QR Code -->
        <?php if ($sheet['status'] === 'active'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-qr-code me-2"></i>QR Code</h5>
                </div>
                <div class="card-body text-center">
                    <div class="qr-code-container mb-3" id="qrcode" data-qr-code="<?= sanitize($signUrl) ?>" data-qr-size="200">
                    </div>
                    <div class="d-grid gap-2">
                        <a href="<?= SITE_URL ?>/pages/dashboard/print-qr.php?id=<?= $sheet['id'] ?>" class="btn btn-primary btn-sm" target="_blank">
                            <i class="bi bi-file-earmark-text me-2"></i>Document à distribuer
                        </a>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="downloadQRCode('qrcode', 'qr-<?= $sheet['unique_code'] ?>.png')">
                            <i class="bi bi-download me-2"></i>Télécharger QR
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lien de partage -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Lien de partage</h5>
                </div>
                <div class="card-body">
                    <div class="input-group mb-2">
                        <input type="text" class="form-control form-control-sm" value="<?= sanitize($signUrl) ?>" readonly id="shareLink">
                        <button class="btn btn-outline-primary" type="button" data-copy="<?= sanitize($signUrl) ?>">
                            <i class="bi bi-clipboard me-1"></i>Copier
                        </button>
                    </div>
                    <small class="text-muted">Partagez ce lien avec les participants pour qu'ils puissent signer.</small>
                </div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <!-- Exports (disponible pour tous) -->
                    <a href="<?= SITE_URL ?>/pages/export/pdf.php?id=<?= $sheet['id'] ?>" class="btn btn-primary" target="_blank">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Exporter en PDF
                    </a>
                    <a href="<?= SITE_URL ?>/pages/export/excel.php?id=<?= $sheet['id'] ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel me-2"></i>Exporter en Excel
                    </a>

                    <?php if ($isOwner): ?>
                    <hr>

                    <!-- Gestion (propriétaire uniquement) -->
                    <?php if ($sheet['status'] === 'active'): ?>
                        <a href="<?= SITE_URL ?>/pages/dashboard/edit.php?id=<?= $sheet['id'] ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil me-2"></i>Modifier
                        </a>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="close">
                            <button type="submit" class="btn btn-outline-warning w-100" onclick="return confirm('Êtes-vous sûr de vouloir clôturer cette feuille ?')">
                                <i class="bi bi-lock me-2"></i>Clôturer
                            </button>
                        </form>
                    <?php elseif ($sheet['status'] === 'closed'): ?>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reopen">
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="bi bi-unlock me-2"></i>Réouvrir
                            </button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette feuille et toutes ses signatures ? Cette action est irréversible.')">
                            <i class="bi bi-trash me-2"></i>Supprimer
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des signatures -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-vector-pen me-2"></i>Signatures (<?= count($signatures) ?>)
                    <?php if ($isMultiDay): ?>
                        <small class="text-muted ms-2">sur <?= count($eventDays) ?> jours</small>
                    <?php endif; ?>
                </h5>
                <?php if ($sheet['status'] === 'active'): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Actualiser
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($signatures)): ?>
                    <div class="empty-state py-5">
                        <div class="empty-state-icon">
                            <i class="bi bi-vector-pen"></i>
                        </div>
                        <h4>Aucune signature pour le moment</h4>
                        <?php if ($sheet['status'] === 'active'): ?>
                            <p>Partagez le QR code ou le lien pour commencer à collecter les signatures.</p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($isMultiDay): ?>
                    <!-- Affichage multi-jours avec onglets -->
                    <?php
                    $dayNames = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                    $monthNames = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
                    ?>
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <?php foreach ($eventDays as $idx => $day):
                            $dateObj = new DateTime($day);
                            $dayCount = count($signaturesByDay[$day] ?? []);
                            $dayLabel = $dayNames[(int)$dateObj->format('w')] . ' ' . $dateObj->format('j');
                        ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $idx === 0 ? 'active' : '' ?>" id="day-<?= $idx ?>-tab"
                                data-bs-toggle="tab" data-bs-target="#day-<?= $idx ?>" type="button"
                                role="tab" aria-controls="day-<?= $idx ?>" aria-selected="<?= $idx === 0 ? 'true' : 'false' ?>">
                                <?= $dayLabel ?>
                                <span class="badge <?= $dayCount > 0 ? 'bg-primary' : 'bg-secondary' ?> ms-1"><?= $dayCount ?></span>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="tab-content">
                        <?php foreach ($eventDays as $idx => $day):
                            $daySigs = $signaturesByDay[$day] ?? [];
                        ?>
                        <div class="tab-pane fade <?= $idx === 0 ? 'show active' : '' ?>" id="day-<?= $idx ?>"
                             role="tabpanel" aria-labelledby="day-<?= $idx ?>-tab">
                            <?php if (empty($daySigs)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-calendar-x d-block mb-2" style="font-size: 2rem;"></i>
                                    Aucune signature pour ce jour
                                </div>
                            <?php else: ?>
                            <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">#</th>
                                        <th>Nom complet</th>
                                        <th>Structure</th>
                                        <th>Fonction</th>
                                        <th>Contacts</th>
                                        <th style="width: 80px;">Signature</th>
                                        <?php if ($canManageSignatures): ?>
                                        <th style="width: 70px;">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daySigs as $index => $sig): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <strong><?= sanitize($sig['first_name']) ?> <?= sanitize($sig['last_name']) ?></strong>
                                            </td>
                                            <td><?= $sig['structure'] ? sanitize($sig['structure']) : '-' ?></td>
                                            <td><?= $sig['function_title'] ? sanitize($sig['function_title']) : '-' ?></td>
                                            <td>
                                                <small><?= sanitize($sig['email']) ?></small>
                                                <?php if ($sig['phone']): ?>
                                                    <br><small class="text-muted"><i class="bi bi-telephone me-1"></i><?= formatPhone($sig['phone']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <img src="<?= $sig['signature_data'] ?>" alt="Signature" class="signature-preview">
                                            </td>
                                            <?php if ($canManageSignatures): ?>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" title="Modifier"
                                                        data-bs-toggle="modal" data-bs-target="#editSignatureModal"
                                                        data-sig-id="<?= $sig['id'] ?>"
                                                        data-sig-firstname="<?= sanitize($sig['first_name']) ?>"
                                                        data-sig-lastname="<?= sanitize($sig['last_name']) ?>"
                                                        data-sig-function="<?= sanitize($sig['function_title'] ?? '') ?>"
                                                        data-sig-phone="<?= sanitize($sig['phone'] ?? '') ?>"
                                                        data-sig-phone2="<?= sanitize($sig['phone_secondary'] ?? '') ?>"
                                                        data-sig-structure="<?= sanitize($sig['structure'] ?? '') ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" title="Supprimer"
                                                        data-bs-toggle="modal" data-bs-target="#deleteSignatureModal"
                                                        data-sig-id="<?= $sig['id'] ?>"
                                                        data-sig-name="<?= sanitize($sig['first_name'] . ' ' . $sig['last_name']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>Nom complet</th>
                                <th>Structure</th>
                                <th>Fonction</th>
                                <th>Contacts</th>
                                <th style="width: 80px;">Signature</th>
                                <?php if ($canManageSignatures): ?>
                                <th style="width: 70px;">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($signatures as $index => $sig): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= sanitize($sig['first_name']) ?> <?= sanitize($sig['last_name']) ?></strong>
                                    </td>
                                    <td><?= $sig['structure'] ? sanitize($sig['structure']) : '-' ?></td>
                                    <td><?= $sig['function_title'] ? sanitize($sig['function_title']) : '-' ?></td>
                                    <td>
                                        <small><?= sanitize($sig['email']) ?></small>
                                        <?php if ($sig['phone']): ?>
                                            <br><small class="text-muted"><i class="bi bi-telephone me-1"></i><?= formatPhone($sig['phone']) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($sig['phone_secondary'])): ?>
                                            <br><small class="text-muted"><i class="bi bi-telephone me-1"></i><?= formatPhone($sig['phone_secondary']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <img src="<?= $sig['signature_data'] ?>" alt="Signature" class="signature-preview">
                                    </td>
                                    <?php if ($canManageSignatures): ?>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" title="Modifier"
                                                data-bs-toggle="modal" data-bs-target="#editSignatureModal"
                                                data-sig-id="<?= $sig['id'] ?>"
                                                data-sig-firstname="<?= sanitize($sig['first_name']) ?>"
                                                data-sig-lastname="<?= sanitize($sig['last_name']) ?>"
                                                data-sig-function="<?= sanitize($sig['function_title'] ?? '') ?>"
                                                data-sig-phone="<?= sanitize($sig['phone'] ?? '') ?>"
                                                data-sig-phone2="<?= sanitize($sig['phone_secondary'] ?? '') ?>"
                                                data-sig-structure="<?= sanitize($sig['structure'] ?? '') ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Supprimer"
                                                data-bs-toggle="modal" data-bs-target="#deleteSignatureModal"
                                                data-sig-id="<?= $sig['id'] ?>"
                                                data-sig-name="<?= sanitize($sig['first_name'] . ' ' . $sig['last_name']) ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canManageSignatures): ?>
<!-- Modal d'édition de signature -->
<div class="modal fade" id="editSignatureModal" tabindex="-1" aria-labelledby="editSignatureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_signature">
                <input type="hidden" name="signature_id" id="editSigId">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSignatureModalLabel">
                        <i class="bi bi-pencil me-2"></i>Modifier la signature
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-1"></i>
                        La signature manuscrite et l'horodatage restent inchangés.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editFirstName" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editLastName" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editLastName" name="last_name" required>
                        </div>
                        <div class="col-12">
                            <label for="editStructure" class="form-label">Structure</label>
                            <input type="text" class="form-control" id="editStructure" name="structure">
                        </div>
                        <div class="col-12">
                            <label for="editFunction" class="form-label">Fonction</label>
                            <input type="text" class="form-control" id="editFunction" name="function_title">
                        </div>
                        <div class="col-md-6">
                            <label for="editPhone" class="form-label">Téléphone principal</label>
                            <input type="tel" class="form-control" id="editPhone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="editPhone2" class="form-label">Téléphone secondaire</label>
                            <input type="tel" class="form-control" id="editPhone2" name="phone_secondary">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de suppression de signature -->
<div class="modal fade" id="deleteSignatureModal" tabindex="-1" aria-labelledby="deleteSignatureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_signature">
                <input type="hidden" name="signature_id" id="deleteSigId">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteSignatureModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Supprimer la signature
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la signature de <strong id="deleteSigName"></strong> ?</p>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Cette action est irréversible. La signature sera définitivement supprimée.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Le QR code est généré automatiquement par initQRCodes() dans app.js
    // grâce à l'attribut data-qr-code sur le conteneur

    <?php if ($canManageSignatures): ?>
    // Gestion du modal d'édition de signature
    const editModal = document.getElementById('editSignatureModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('editSigId').value = button.dataset.sigId;
            document.getElementById('editFirstName').value = button.dataset.sigFirstname;
            document.getElementById('editLastName').value = button.dataset.sigLastname;
            document.getElementById('editFunction').value = button.dataset.sigFunction || '';
            document.getElementById('editPhone').value = button.dataset.sigPhone || '';
            document.getElementById('editPhone2').value = button.dataset.sigPhone2 || '';
            document.getElementById('editStructure').value = button.dataset.sigStructure || '';
        });
    }

    // Gestion du modal de suppression de signature
    const deleteModal = document.getElementById('deleteSignatureModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            document.getElementById('deleteSigId').value = button.dataset.sigId;
            document.getElementById('deleteSigName').textContent = button.dataset.sigName;
        });
    }
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
