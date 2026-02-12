<?php
/**
 * e-Présence - Tableau de bord
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireLogin();

$userId = getCurrentUserId();
$currentUser = getCurrentUser();
$isStructureAdmin = !empty($currentUser['is_structure_admin']) && !empty($currentUser['structure']);
$isAdmin = ($currentUser['role'] === 'admin');

// Traitement des actions rapides (clôturer/réouvrir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $sheetId = intval($_POST['sheet_id'] ?? 0);

    if ($sheetId > 0) {
        // Les admins peuvent gérer toutes les feuilles, les autres seulement les leurs
        if ($isAdmin) {
            $checkStmt = db()->prepare("SELECT id, status FROM sheets WHERE id = ?");
            $checkStmt->execute([$sheetId]);
        } else {
            $checkStmt = db()->prepare("SELECT id, status FROM sheets WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$sheetId, $userId]);
        }
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
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

// Vérifier si c'est un super-utilisateur de catégorie (Services propres ou Direction Générale)
$isCategorySuperAdmin = false;
$structureCodes = array();
$userCategory = null;
if ($isStructureAdmin) {
    $userCategory = getStructureCategory($currentUser['structure']);

    // Super-admin de catégorie : Services propres ou Direction Générale
    // Peut voir toutes les feuilles de sa catégorie
    $isCategorySuperAdmin = isCategorySuperStructure($currentUser['structure']);

    if (!$isCategorySuperAdmin) {
        $structureCodes = getStructureCodesInCategory($currentUser['structure']);
    } else {
        // Récupérer toutes les structures de la catégorie
        $structureCodes = getStructureCodesInCategory($currentUser['structure']);
    }
}

// Les admins système voient TOUTES les structures du ministère
$canSeeAll = $isAdmin;
// Les super-admins de catégorie voient toutes les feuilles de leur catégorie
$canSeeCategoryAll = $isStructureAdmin && $isCategorySuperAdmin;

// Récupérer les statistiques
if ($canSeeAll) {
    // Super-utilisateur Direction générale: voir TOUTES les feuilles

    // Stats pour ses propres feuilles
    $statsQuery = db()->prepare("
        SELECT
            COUNT(*) as total_sheets,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sheets,
            COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_sheets,
            (SELECT COUNT(*) FROM signatures s
             JOIN sheets sh ON s.sheet_id = sh.id
             WHERE sh.user_id = ?) as total_signatures
        FROM sheets
        WHERE user_id = ?
    ");
    $statsQuery->execute([$userId, $userId]);
    $stats = $statsQuery->fetch();

    // Stats pour TOUT le Ministère
    $structureStatsQuery = db()->query("
        SELECT
            COUNT(*) as structure_sheets,
            (SELECT COUNT(*) FROM signatures) as structure_signatures
        FROM sheets
    ");
    $structureStats = $structureStatsQuery->fetch();

    // Récupérer les statistiques par catégorie et structure pour l'affichage détaillé
    $statsByCategoryQuery = db()->query("
        SELECT
            u.structure,
            COUNT(s.id) as sheet_count
        FROM users u
        LEFT JOIN sheets s ON u.id = s.user_id
        WHERE u.structure IS NOT NULL AND u.structure != ''
        GROUP BY u.structure
        HAVING COUNT(s.id) > 0
        ORDER BY sheet_count DESC
    ");
    $statsByStructure = $statsByCategoryQuery->fetchAll();

    // Organiser les stats par catégorie MEPC
    $mepcStructures = getMEPCStructuresGrouped();
    $categoryStats = [];
    foreach ($mepcStructures as $category => $structures) {
        $categoryStats[$category] = [
            'total' => 0,
            'structures' => []
        ];
        foreach ($structures as $structure) {
            $categoryStats[$category]['structures'][$structure] = 0;
        }
    }

    // Remplir avec les données réelles
    foreach ($statsByStructure as $row) {
        $rawStructure = $row['structure'];
        $normalizedStructure = normalizeStructureName($rawStructure);
        $category = null;

        // Trouver la catégorie de cette structure
        foreach ($mepcStructures as $cat => $structures) {
            if (in_array($normalizedStructure, $structures) || in_array($rawStructure, $structures)) {
                $category = $cat;
                break;
            }
        }

        if ($category !== null) {
            $categoryStats[$category]['total'] += $row['sheet_count'];
            if (isset($categoryStats[$category]['structures'][$normalizedStructure])) {
                $categoryStats[$category]['structures'][$normalizedStructure] += $row['sheet_count'];
            } elseif (isset($categoryStats[$category]['structures'][$rawStructure])) {
                $categoryStats[$category]['structures'][$rawStructure] += $row['sheet_count'];
            }
        }
    }

    // Récupérer TOUTES les feuilles
    $sheetsQuery = db()->prepare("
        SELECT s.*,
               u.first_name as creator_first_name,
               u.last_name as creator_last_name,
               u.structure as creator_structure,
               (SELECT COUNT(*) FROM signatures WHERE sheet_id = s.id) as signature_count,
               CASE WHEN s.user_id = ? THEN 1 ELSE 0 END as is_owner
        FROM sheets s
        JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
        LIMIT 50
    ");
    $sheetsQuery->execute([$userId]);
    $sheets = $sheetsQuery->fetchAll();

} elseif (!$isAdmin && $isStructureAdmin && count($structureCodes) > 0) {
    // Super-utilisateur de structure: voir les feuilles de sa catégorie
    $placeholders = implode(',', array_fill(0, count($structureCodes), '?'));

    // Stats pour ses propres feuilles
    $statsQuery = db()->prepare("
        SELECT
            COUNT(*) as total_sheets,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sheets,
            COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_sheets,
            (SELECT COUNT(*) FROM signatures s
             JOIN sheets sh ON s.sheet_id = sh.id
             WHERE sh.user_id = ?) as total_signatures
        FROM sheets
        WHERE user_id = ?
    ");
    $statsQuery->execute([$userId, $userId]);
    $stats = $statsQuery->fetch();

    // Stats pour toute la structure
    $structureStatsQuery = db()->prepare("
        SELECT
            COUNT(*) as structure_sheets,
            (SELECT COUNT(*) FROM signatures s
             JOIN sheets sh ON s.sheet_id = sh.id
             JOIN users u ON sh.user_id = u.id
             WHERE u.structure IN ($placeholders)) as structure_signatures
        FROM sheets sh
        JOIN users u ON sh.user_id = u.id
        WHERE u.structure IN ($placeholders)
    ");
    $structureStatsQuery->execute(array_merge($structureCodes, $structureCodes));
    $structureStats = $structureStatsQuery->fetch();

    // Récupérer les feuilles de la structure
    $sheetsQuery = db()->prepare("
        SELECT s.*,
               u.first_name as creator_first_name,
               u.last_name as creator_last_name,
               u.structure as creator_structure,
               (SELECT COUNT(*) FROM signatures WHERE sheet_id = s.id) as signature_count,
               CASE WHEN s.user_id = ? THEN 1 ELSE 0 END as is_owner
        FROM sheets s
        JOIN users u ON s.user_id = u.id
        WHERE u.structure IN ($placeholders)
        ORDER BY s.created_at DESC
        LIMIT 20
    ");
    $sheetsQuery->execute(array_merge([$userId], $structureCodes));
    $sheets = $sheetsQuery->fetchAll();
} else {
    // Utilisateur standard: voir uniquement ses propres feuilles
    $statsQuery = db()->prepare("
        SELECT
            COUNT(*) as total_sheets,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sheets,
            COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_sheets,
            (SELECT COUNT(*) FROM signatures s
             JOIN sheets sh ON s.sheet_id = sh.id
             WHERE sh.user_id = ?) as total_signatures
        FROM sheets
        WHERE user_id = ?
    ");
    $statsQuery->execute([$userId, $userId]);
    $stats = $statsQuery->fetch();
    $structureStats = null;

    // Récupérer les feuilles récentes
    $sheetsQuery = db()->prepare("
        SELECT s.*,
               (SELECT COUNT(*) FROM signatures WHERE sheet_id = s.id) as signature_count,
               1 as is_owner
        FROM sheets s
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
        LIMIT 10
    ");
    $sheetsQuery->execute([$userId]);
    $sheets = $sheetsQuery->fetchAll();
}

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Tableau de bord</h1>
    <a href="<?= SITE_URL ?>/pages/dashboard/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Nouvelle feuille
    </a>
</div>

<!-- Statistiques personnelles -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number"><?= $stats['total_sheets'] ?></div>
                <div class="stat-label">Mes feuilles</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-success"><?= $stats['active_sheets'] ?></div>
                <div class="stat-label">Actives</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-secondary"><?= $stats['closed_sheets'] ?></div>
                <div class="stat-label">Clôturées</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-info"><?= $stats['total_signatures'] ?></div>
                <div class="stat-label">Signatures</div>
            </div>
        </div>
    </div>
</div>

<?php if (($canSeeAll || $isStructureAdmin) && $structureStats): ?>
<!-- Statistiques globales (admin ou super-utilisateur) -->
<div class="alert <?= $isAdmin ? 'alert-primary' : ($canSeeCategoryAll ? 'alert-danger' : 'alert-warning') ?> mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-<?= $isAdmin ? 'shield-lock' : 'star-fill' ?> me-2"></i>
        <strong><?= $isAdmin ? 'Mode Administrateur' : 'Mode Super-utilisateur' ?><?= $canSeeCategoryAll && !$isAdmin ? ' - ' . ($userCategory ?? 'Catégorie') : '' ?></strong>
        <span class="ms-2">
            <?php if ($isAdmin): ?>
                - Vous voyez <strong>TOUTES</strong> les feuilles du Ministère
            <?php elseif ($canSeeCategoryAll): ?>
                - Vous voyez <strong>TOUTES</strong> les feuilles de: <strong><?= sanitize($userCategory ?? 'votre catégorie') ?></strong>
            <?php else: ?>
                - Vous voyez toutes les feuilles de: <strong><?= sanitize(normalizeStructureName($currentUser['structure'])) ?></strong>
            <?php endif; ?>
        </span>
    </div>
</div>
<?php
    $cardClass = $isAdmin ? 'border-primary' : ($canSeeCategoryAll ? 'border-danger' : 'border-warning');
    $textClass = $isAdmin ? 'text-primary' : ($canSeeCategoryAll ? 'text-danger' : 'text-warning');
    $allLabel = $isAdmin ? 'Toutes les feuilles Ministère' : ($canSeeCategoryAll ? 'Toutes les feuilles ' . ($userCategory ?? 'catégorie') : 'Feuilles de la structure');
    $sigLabel = $isAdmin ? 'Toutes les signatures' : ($canSeeCategoryAll ? 'Signatures ' . ($userCategory ?? 'catégorie') : 'Signatures structure');
?>
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100 <?= $cardClass ?>">
            <div class="card-body dashboard-stat">
                <div class="stat-number <?= $textClass ?>"><?= $structureStats['structure_sheets'] ?></div>
                <div class="stat-label"><?= $allLabel ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 <?= $cardClass ?>">
            <div class="card-body dashboard-stat">
                <div class="stat-number <?= $textClass ?>"><?= $structureStats['structure_signatures'] ?></div>
                <div class="stat-label"><?= $sigLabel ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canSeeAll && !empty($categoryStats)): ?>
<!-- Statistiques détaillées par catégorie du Ministère -->
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="bi bi-diagram-3 me-2"></i>Répartition par Direction / Structure
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="categoryStatsAccordion">
            <?php $catIndex = 0; foreach ($categoryStats as $category => $data): ?>
            <?php if ($data['total'] > 0): $catIndex++; ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $catIndex > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#catStats<?= $catIndex ?>">
                        <i class="bi bi-building me-2 text-primary"></i>
                        <strong><?= sanitize($category) ?></strong>
                        <span class="badge bg-primary ms-2"><?= $data['total'] ?> feuille(s)</span>
                    </button>
                </h2>
                <div id="catStats<?= $catIndex ?>" class="accordion-collapse collapse <?= $catIndex === 1 ? 'show' : '' ?>" data-bs-parent="#categoryStatsAccordion">
                    <div class="accordion-body py-2">
                        <div class="row g-2">
                            <?php foreach ($data['structures'] as $structure => $count): ?>
                            <?php if ($count > 0): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                    <small class="text-truncate me-2" title="<?= sanitize($structure) ?>">
                                        <i class="bi bi-person-workspace me-1 text-muted"></i><?= sanitize($structure) ?>
                                    </small>
                                    <span class="badge bg-secondary"><?= $count ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canSeeAll && !empty($sheets)): ?>
<!-- Liste des feuilles groupées par structure (admin/DG) -->
<?php
    // Grouper les feuilles par structure du créateur (avec normalisation des noms)
    $sheetsByStructure = [];
    foreach ($sheets as $sheet) {
        $rawStructure = $sheet['creator_structure'] ?? '';
        // Normaliser le nom de la structure (convertir les anciens acronymes en noms complets)
        $structure = normalizeStructureName($rawStructure);
        if (empty($structure)) $structure = 'Autre';
        if (!isset($sheetsByStructure[$structure])) {
            $sheetsByStructure[$structure] = [];
        }
        $sheetsByStructure[$structure][] = $sheet;
    }

    // Trier les structures alphabétiquement (sauf "Autre" qui va à la fin)
    uksort($sheetsByStructure, function($a, $b) {
        if ($a === 'Autre') return 1;
        if ($b === 'Autre') return -1;
        return strcmp($a, $b);
    });
?>
<?php $maxVisible = 3; ?>
<div class="accordion" id="sheetsAccordion">
    <?php $accordionIndex = 0; foreach ($sheetsByStructure as $structureName => $structureSheets): $accordionIndex++; ?>
    <?php $totalSheets = count($structureSheets); $hasMore = $totalSheets > $maxVisible; ?>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button <?= $accordionIndex > 1 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $accordionIndex ?>">
                <i class="bi bi-building me-2"></i>
                <strong><?= sanitize($structureName) ?></strong>
                <span class="badge bg-primary ms-2"><?= $totalSheets ?> feuille(s)</span>
            </button>
        </h2>
        <div id="collapse<?= $accordionIndex ?>" class="accordion-collapse collapse <?= $accordionIndex === 1 ? 'show' : '' ?>" data-bs-parent="#sheetsAccordion">
            <div class="accordion-body p-0">
                <div class="list-group list-group-flush">
                    <?php $sheetIndex = 0; foreach ($structureSheets as $sheet): $sheetIndex++; if ($sheetIndex > $maxVisible) break; ?>
                        <div class="list-group-item sheet-item status-<?= $sheet['status'] ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="mb-0 me-2"><?= sanitize($sheet['title']) ?></h6>
                                        <?php
                                            $statusLabels = array('active' => 'Active', 'closed' => 'Clôturée', 'archived' => 'Archivée');
                                            $statusLabel = isset($statusLabels[$sheet['status']]) ? $statusLabels[$sheet['status']] : $sheet['status'];
                                        ?>
                                        <span class="badge badge-<?= $sheet['status'] ?>"><?= $statusLabel ?></span>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        <?= formatDateFr($sheet['event_date']) ?>
                                        <?php if ($sheet['event_time']): ?>
                                            à <?= formatTime($sheet['event_time']) ?>
                                        <?php endif; ?>
                                        <span class="ms-2">
                                            <i class="bi bi-person me-1"></i><?= sanitize($sheet['creator_first_name'] . ' ' . $sheet['creator_last_name']) ?>
                                        </span>
                                        <span class="ms-2">
                                            <i class="bi bi-vector-pen me-1"></i><?= $sheet['signature_count'] ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="btn-group">
                                    <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheet['id'] ?>&structure=1"
                                       class="btn btn-sm btn-outline-primary" title="Voir">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($sheet['status'] === 'active'): ?>
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
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($hasMore): ?>
                        <div class="list-group-item text-center py-2">
                            <a href="<?= SITE_URL ?>/pages/dashboard/structure-sheets.php?structure=<?= urlencode($structureName) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-arrow-right me-1"></i>Voir tout (<?= $totalSheets - $maxVisible ?> de plus)
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif (!$canSeeAll): ?>
<?php
    // Séparer les feuilles propres de celles de la structure
    $mySheets = [];
    $structureSheets = [];
    foreach ($sheets as $sheet) {
        if (!empty($sheet['is_owner'])) {
            $mySheets[] = $sheet;
        } else {
            $structureSheets[] = $sheet;
        }
    }
?>

<!-- Mes feuilles personnelles -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-person-circle me-2"></i>Mes feuilles d'émargement
            <span class="badge bg-primary ms-2"><?= count($mySheets) ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($mySheets)): ?>
            <div class="empty-state py-4">
                <div class="empty-state-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <h5>Aucune feuille personnelle</h5>
                <p class="mb-3">Créez votre première feuille d'émargement.</p>
                <a href="<?= SITE_URL ?>/pages/dashboard/create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-2"></i>Créer une feuille
                </a>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($mySheets as $sheet): ?>
                    <div class="list-group-item sheet-item status-<?= $sheet['status'] ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <h6 class="mb-0 me-2"><?= sanitize($sheet['title']) ?></h6>
                                    <?php
                                        $statusLabels = array('active' => 'Active', 'closed' => 'Clôturée', 'archived' => 'Archivée');
                                        $statusLabel = isset($statusLabels[$sheet['status']]) ? $statusLabels[$sheet['status']] : $sheet['status'];
                                    ?>
                                    <span class="badge badge-<?= $sheet['status'] ?>"><?= $statusLabel ?></span>
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= formatDateFr($sheet['event_date']) ?>
                                    <?php if ($sheet['event_time']): ?>
                                        à <?= formatTime($sheet['event_time']) ?>
                                    <?php endif; ?>
                                    <?php if ($sheet['location']): ?>
                                        <span class="ms-2">
                                            <i class="bi bi-geo-alt me-1"></i><?= sanitize($sheet['location']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark">
                                        <i class="bi bi-vector-pen me-1"></i><?= $sheet['signature_count'] ?> signature(s)
                                    </span>
                                </div>
                            </div>
                            <div class="btn-group">
                                <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheet['id'] ?>"
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isStructureAdmin && !empty($structureSheets)): ?>
<!-- Feuilles de la structure -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-warning bg-opacity-10">
        <h5 class="mb-0">
            <i class="bi bi-people me-2"></i>Feuilles de la structure
            <span class="badge bg-warning text-dark ms-2"><?= count($structureSheets) ?></span>
        </h5>
    </div>
    <div class="card-body">
        <div class="list-group list-group-flush">
            <?php foreach ($structureSheets as $sheet): ?>
                <div class="list-group-item sheet-item status-<?= $sheet['status'] ?> border-start border-warning border-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <h6 class="mb-0 me-2"><?= sanitize($sheet['title']) ?></h6>
                                <?php
                                    $statusLabels = array('active' => 'Active', 'closed' => 'Clôturée', 'archived' => 'Archivée');
                                    $statusLabel = isset($statusLabels[$sheet['status']]) ? $statusLabels[$sheet['status']] : $sheet['status'];
                                ?>
                                <span class="badge badge-<?= $sheet['status'] ?>"><?= $statusLabel ?></span>
                            </div>
                            <?php if (isset($sheet['creator_first_name'])): ?>
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-person me-1"></i>
                                    Créée par <?= sanitize($sheet['creator_first_name'] . ' ' . $sheet['creator_last_name']) ?>
                                    <?php if (!empty($sheet['creator_structure'])): ?>
                                        (<?= sanitize(normalizeStructureName($sheet['creator_structure'])) ?>)
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-muted small">
                                <i class="bi bi-calendar-event me-1"></i>
                                <?= formatDateFr($sheet['event_date']) ?>
                                <?php if ($sheet['event_time']): ?>
                                    à <?= formatTime($sheet['event_time']) ?>
                                <?php endif; ?>
                                <?php if ($sheet['location']): ?>
                                    <span class="ms-2">
                                        <i class="bi bi-geo-alt me-1"></i><?= sanitize($sheet['location']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-vector-pen me-1"></i><?= $sheet['signature_count'] ?> signature(s)
                                </span>
                            </div>
                        </div>
                        <div class="btn-group">
                            <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheet['id'] ?>&structure=1"
                               class="btn btn-sm btn-outline-primary" title="Voir">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php else: ?>
<!-- Aucune feuille (admin) -->
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <h3>Aucune feuille pour le moment</h3>
            <p>Aucune feuille d'émargement n'a été créée dans l'organisation.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
