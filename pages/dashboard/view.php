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

    // Super-utilisateur Direction générale: peut voir TOUT
    if ($userCategory === 'Direction générale') {
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

// Récupérer les signatures
$signaturesStmt = db()->prepare("
    SELECT * FROM signatures
    WHERE sheet_id = ?
    ORDER BY signed_at ASC
");
$signaturesStmt->execute([$sheetId]);
$signatures = $signaturesStmt->fetchAll();

// Récupérer les documents attachés
$documentsStmt = db()->prepare("
    SELECT * FROM sheet_documents
    WHERE sheet_id = ?
    ORDER BY document_type, uploaded_at
");
$documentsStmt->execute([$sheetId]);
$documents = $documentsStmt->fetchAll();

// Traitement des actions (uniquement pour le propriétaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '') && $isOwner) {
    $action = $_POST['action'] ?? '';

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
            $filePath = __DIR__ . '/../../uploads/documents/' . $docFile['stored_name'];
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
                $filePath = __DIR__ . '/../../uploads/documents/' . $docFile['stored_name'];
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
                <p class="mb-2">
                    <i class="bi bi-calendar-event me-2 text-muted"></i>
                    <strong>Date :</strong> <?= formatDateFr($sheet['event_date']) ?>
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
                <?php if ($sheet['description']): ?>
                    <p class="mb-2">
                        <i class="bi bi-card-text me-2 text-muted"></i>
                        <strong>Description :</strong><br>
                        <?= nl2br(sanitize($sheet['description'])) ?>
                    </p>
                <?php endif; ?>
                <p class="mb-0">
                    <i class="bi bi-vector-pen me-2 text-muted"></i>
                    <strong>Signatures :</strong> <?= count($signatures) ?>
                </p>
            </div>
        </div>

        <?php if (!empty($documents)): ?>
        <!-- Documents attachés -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-folder2-open me-2"></i>Documents (<?= count($documents) ?>)</h5>
            </div>
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
                            <a href="<?= SITE_URL ?>/uploads/documents/<?= sanitize($doc['stored_name']) ?>"
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
        </div>
        <?php endif; ?>

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
                <h5 class="mb-0"><i class="bi bi-vector-pen me-2"></i>Signatures (<?= count($signatures) ?>)</h5>
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
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>Nom complet</th>
                                <th>Fonction</th>
                                <th>Structure</th>
                                <th>Contact</th>
                                <th style="width: 80px;">Signature</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($signatures as $index => $sig): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= sanitize($sig['first_name']) ?> <?= sanitize($sig['last_name']) ?></strong>
                                    </td>
                                    <td><?= $sig['function_title'] ? sanitize($sig['function_title']) : '-' ?></td>
                                    <td><?= $sig['structure'] ? sanitize($sig['structure']) : '-' ?></td>
                                    <td>
                                        <small><?= sanitize($sig['email']) ?></small>
                                        <?php if ($sig['phone']): ?>
                                            <br><small class="text-muted"><?= formatPhone($sig['phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <img src="<?= $sig['signature_data'] ?>" alt="Signature" class="signature-preview">
                                    </td>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Le QR code est généré automatiquement par initQRCodes() dans app.js
    // grâce à l'attribut data-qr-code sur le conteneur
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
