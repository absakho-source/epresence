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

// Vérifier si c'est un super-utilisateur de la Direction générale (voit TOUT)
$isDGSuperAdmin = false;
$structureCodes = array();
if ($isStructureAdmin) {
    $userCategory = getStructureCategory($currentUser['structure']);
    $isDGSuperAdmin = ($userCategory === 'Direction générale');
    if (!$isDGSuperAdmin) {
        $structureCodes = getStructureCodesInCategory($currentUser['structure']);
    }
}

// Récupérer les statistiques
if ($isStructureAdmin && $isDGSuperAdmin) {
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

    // Stats pour TOUTE la DGPPE
    $structureStatsQuery = db()->query("
        SELECT
            COUNT(*) as structure_sheets,
            (SELECT COUNT(*) FROM signatures) as structure_signatures
        FROM sheets
    ");
    $structureStats = $structureStatsQuery->fetch();

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

} elseif ($isStructureAdmin && count($structureCodes) > 0) {
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

<?php if ($isStructureAdmin && $structureStats): ?>
<!-- Statistiques de la structure (super-utilisateur) -->
<div class="alert <?= $isDGSuperAdmin ? 'alert-danger' : 'alert-warning' ?> mb-4">
    <div class="d-flex align-items-center">
        <i class="bi bi-star-fill me-2"></i>
        <strong>Mode Super-utilisateur<?= $isDGSuperAdmin ? ' - Direction générale' : '' ?></strong>
        <span class="ms-2">
            <?php if ($isDGSuperAdmin): ?>
                - Vous voyez <strong>TOUTES</strong> les feuilles de la DGPPE
            <?php else: ?>
                - Vous voyez toutes les feuilles de: <?= sanitize(getStructureCategory($currentUser['structure'])) ?>
            <?php endif; ?>
        </span>
    </div>
</div>
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100 <?= $isDGSuperAdmin ? 'border-danger' : 'border-warning' ?>">
            <div class="card-body dashboard-stat">
                <div class="stat-number <?= $isDGSuperAdmin ? 'text-danger' : 'text-warning' ?>"><?= $structureStats['structure_sheets'] ?></div>
                <div class="stat-label"><?= $isDGSuperAdmin ? 'Toutes les feuilles DGPPE' : 'Feuilles de la structure' ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 <?= $isDGSuperAdmin ? 'border-danger' : 'border-warning' ?>">
            <div class="card-body dashboard-stat">
                <div class="stat-number <?= $isDGSuperAdmin ? 'text-danger' : 'text-warning' ?>"><?= $structureStats['structure_signatures'] ?></div>
                <div class="stat-label"><?= $isDGSuperAdmin ? 'Toutes les signatures' : 'Signatures structure' ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des feuilles -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-list-ul me-2"></i>
            <?php if ($isDGSuperAdmin): ?>
                Toutes les feuilles DGPPE
            <?php elseif ($isStructureAdmin): ?>
                Feuilles de la structure
            <?php else: ?>
                Mes feuilles d'émargement
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($sheets)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <h3>Aucune feuille pour le moment</h3>
                <p>Créez votre première feuille d'émargement pour commencer.</p>
                <a href="<?= SITE_URL ?>/pages/dashboard/create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Créer une feuille
                </a>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($sheets as $sheet): ?>
                    <div class="list-group-item sheet-item status-<?= $sheet['status'] ?> <?= empty($sheet['is_owner']) ? 'border-start border-warning border-3' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <h6 class="mb-0 me-2"><?= sanitize($sheet['title']) ?></h6>
                                    <?php
                                        $statusLabels = array('active' => 'Active', 'closed' => 'Clôturée', 'archived' => 'Archivée');
                                        $statusLabel = isset($statusLabels[$sheet['status']]) ? $statusLabels[$sheet['status']] : $sheet['status'];
                                    ?>
                                    <span class="badge badge-<?= $sheet['status'] ?>"><?= $statusLabel ?></span>
                                    <?php if (empty($sheet['is_owner'])): ?>
                                        <span class="badge bg-warning text-dark ms-1" title="Créée par un membre de votre structure">
                                            <i class="bi bi-people me-1"></i>Structure
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($sheet['is_owner']) && isset($sheet['creator_first_name'])): ?>
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-person me-1"></i>
                                        Créée par <?= sanitize($sheet['creator_first_name'] . ' ' . $sheet['creator_last_name']) ?>
                                        <?php if (!empty($sheet['creator_structure'])): ?>
                                            (<?= sanitize(getStructureName($sheet['creator_structure'])) ?>)
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
                                <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheet['id'] ?><?= empty($sheet['is_owner']) ? '&structure=1' : '' ?>"
                                   class="btn btn-sm btn-outline-primary" title="Voir">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($sheet['status'] === 'active' && !empty($sheet['is_owner'])): ?>
                                    <a href="<?= SITE_URL ?>/pages/dashboard/edit.php?id=<?= $sheet['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
