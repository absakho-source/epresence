<?php
/**
 * e-Présence - Liste des feuilles d'une structure
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireLogin();

$currentUser = getCurrentUser();
$isAdmin = ($currentUser['role'] === 'admin');

if (!$isAdmin) {
    setFlash('error', 'Accès réservé aux administrateurs.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

$structureParam = $_GET['structure'] ?? '';
if (empty($structureParam)) {
    setFlash('error', 'Structure non spécifiée.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

// Normaliser le nom de structure pour l'affichage
$structureName = normalizeStructureName($structureParam);

// Créer la liste des valeurs à chercher (ancien acronyme + nom complet)
$structureVariants = [$structureParam, $structureName];
// Ajouter aussi les anciens acronymes qui correspondent à ce nom complet
global $DGPPE_ACRONYMS_MAP;
foreach ($DGPPE_ACRONYMS_MAP as $acronym => $fullName) {
    if ($fullName === $structureName && !in_array($acronym, $structureVariants)) {
        $structureVariants[] = $acronym;
    }
}
$structureVariants = array_unique($structureVariants);

$userId = getCurrentUserId();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $sheetId = intval($_POST['sheet_id'] ?? 0);

    if ($sheetId > 0) {
        $checkStmt = db()->prepare("SELECT id, status FROM sheets WHERE id = ?");
        $checkStmt->execute([$sheetId]);
        $targetSheet = $checkStmt->fetch();

        if ($targetSheet) {
            if ($action === 'close' && $targetSheet['status'] === 'active') {
                $updateStmt = db()->prepare("UPDATE sheets SET status = 'closed', closed_at = CURRENT_TIMESTAMP, closed_by = ? WHERE id = ?");
                $updateStmt->execute([$userId, $sheetId]);
                setFlash('success', 'Feuille clôturée avec succès.');
            } elseif ($action === 'reopen' && $targetSheet['status'] === 'closed') {
                $updateStmt = db()->prepare("UPDATE sheets SET status = 'active', closed_at = NULL, closed_by = NULL WHERE id = ?");
                $updateStmt->execute([$sheetId]);
                setFlash('success', 'Feuille réouverte avec succès.');
            }
        }
    }
    redirect(SITE_URL . '/pages/dashboard/structure-sheets.php?structure=' . urlencode($structureName));
}

// Récupérer les feuilles de cette structure (via creator_structure de la feuille, pas de l'utilisateur)
$placeholders = implode(',', array_fill(0, count($structureVariants), '?'));
$sheetsQuery = db()->prepare("
    SELECT s.*,
           COALESCE(u.first_name || ' ' || u.last_name, s.creator_name, 'Utilisateur supprimé') as display_creator_name,
           u.first_name as creator_first_name,
           u.last_name as creator_last_name,
           (SELECT COUNT(*) FROM signatures WHERE sheet_id = s.id) as signature_count
    FROM sheets s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.creator_structure IN ($placeholders)
    ORDER BY s.created_at DESC
");
$sheetsQuery->execute($structureVariants);
$sheets = $sheetsQuery->fetchAll();

$pageTitle = 'Feuilles - ' . $structureName;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h1 class="h3 mb-0"><?= sanitize($structureName) ?></h1>
        <p class="text-muted mb-0"><?= count($sheets) ?> feuille(s) d'émargement</p>
    </div>
</div>

<?php if (empty($sheets)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-earmark-text display-4 text-muted"></i>
            <p class="mt-3 text-muted">Aucune feuille pour cette structure.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Créateur</th>
                        <th>Signatures</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sheets as $sheet): ?>
                        <tr>
                            <td>
                                <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheet['id'] ?>&structure=1" class="text-decoration-none">
                                    <?= sanitize($sheet['title']) ?>
                                </a>
                            </td>
                            <td>
                                <?= formatDateFr($sheet['event_date']) ?>
                                <?php if ($sheet['event_time']): ?>
                                    <small class="text-muted">à <?= formatTime($sheet['event_time']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($sheet['display_creator_name'] ?? 'Utilisateur supprimé') ?></td>
                            <td><span class="badge bg-light text-dark"><?= $sheet['signature_count'] ?></span></td>
                            <td>
                                <?php
                                    $statusLabels = ['active' => 'Active', 'closed' => 'Clôturée', 'archived' => 'Archivée'];
                                    $statusLabel = $statusLabels[$sheet['status']] ?? $sheet['status'];
                                ?>
                                <span class="badge badge-<?= $sheet['status'] ?>"><?= $statusLabel ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheet['id'] ?>&structure=1"
                                       class="btn btn-sm btn-outline-primary" title="Voir">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($sheet['status'] === 'active'): ?>
                                        <a href="<?= SITE_URL ?>/pages/dashboard/edit.php?id=<?= $sheet['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Clôturer cette feuille ?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="close">
                                            <input type="hidden" name="sheet_id" value="<?= $sheet['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Clôturer">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($sheet['status'] === 'closed'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="reopen">
                                            <input type="hidden" name="sheet_id" value="<?= $sheet['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Réouvrir">
                                                <i class="bi bi-unlock"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
