<?php
/**
 * e-Présence - Voir une feuille d'émargement
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);

// Récupérer la feuille
$stmt = db()->prepare("SELECT * FROM sheets WHERE id = ? AND user_id = ?");
$stmt->execute([$sheetId, getCurrentUserId()]);
$sheet = $stmt->fetch();

if (!$sheet) {
    setFlash('error', 'Feuille non trouvée.');
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

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'close' && $sheet['status'] === 'active') {
        $updateStmt = db()->prepare("UPDATE sheets SET status = 'closed' WHERE id = ?");
        $updateStmt->execute([$sheetId]);
        setFlash('success', 'Feuille clôturée avec succès.');
        redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
    }

    if ($action === 'reopen' && $sheet['status'] === 'closed') {
        $updateStmt = db()->prepare("UPDATE sheets SET status = 'active' WHERE id = ?");
        $updateStmt->execute([$sheetId]);
        setFlash('success', 'Feuille réouverte avec succès.');
        redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
    }

    if ($action === 'delete') {
        $deleteStmt = db()->prepare("DELETE FROM sheets WHERE id = ?");
        $deleteStmt->execute([$sheetId]);
        setFlash('success', 'Feuille supprimée avec succès.');
        redirect(SITE_URL . '/pages/dashboard/index.php');
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
    </div>
</div>

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
                    <?php if ($sheet['event_time']): ?>
                        à <?= formatTime($sheet['event_time']) ?>
                    <?php endif; ?>
                </p>
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
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="downloadQRCode('qrcode', 'qr-<?= $sheet['unique_code'] ?>.png')">
                            <i class="bi bi-download me-2"></i>Télécharger
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="printQRCode('qrcode')">
                            <i class="bi bi-printer me-2"></i>Imprimer
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
                    <!-- Exports -->
                    <a href="<?= SITE_URL ?>/pages/export/pdf.php?id=<?= $sheet['id'] ?>" class="btn btn-primary" target="_blank">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Exporter en PDF
                    </a>
                    <a href="<?= SITE_URL ?>/pages/export/excel.php?id=<?= $sheet['id'] ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel me-2"></i>Exporter en Excel
                    </a>

                    <hr>

                    <!-- Gestion -->
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
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>Nom complet</th>
                                <th class="d-none d-md-table-cell">Structure</th>
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
                                        <?php if ($sig['function_title']): ?>
                                            <br><small class="text-muted"><?= sanitize($sig['function_title']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= $sig['structure'] ? sanitize($sig['structure']) : '-' ?></td>
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
