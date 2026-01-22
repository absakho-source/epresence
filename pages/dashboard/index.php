<?php
/**
 * e-Présence - Tableau de bord
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$userId = getCurrentUserId();

// Récupérer les statistiques
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

// Récupérer les feuilles récentes
$sheetsQuery = db()->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM signatures WHERE sheet_id = s.id) as signature_count
    FROM sheets s
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10
");
$sheetsQuery->execute([$userId]);
$sheets = $sheetsQuery->fetchAll();

$pageTitle = 'Tableau de bord';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Tableau de bord</h1>
    <a href="<?= SITE_URL ?>/pages/dashboard/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Nouvelle feuille
    </a>
</div>

<!-- Statistiques -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number"><?= $stats['total_sheets'] ?></div>
                <div class="stat-label">Feuilles créées</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-success"><?= $stats['active_sheets'] ?></div>
                <div class="stat-label">Feuilles actives</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-secondary"><?= $stats['closed_sheets'] ?></div>
                <div class="stat-label">Feuilles clôturées</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-info"><?= $stats['total_signatures'] ?></div>
                <div class="stat-label">Signatures totales</div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des feuilles -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Mes feuilles d'émargement</h5>
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
