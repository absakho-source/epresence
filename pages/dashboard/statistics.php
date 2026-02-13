<?php
/**
 * e-Présence - Tableau de bord statistiques
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireLogin();

$currentUser = getCurrentUser();
$userId = getCurrentUserId();
$isAdmin = ($currentUser['role'] === 'admin');
$isStructureAdmin = !empty($currentUser['is_structure_admin']) && !empty($currentUser['structure']);

// Filtres
$dateFrom = $_GET['from'] ?? date('Y-01-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');
$filterStructure = $_GET['structure'] ?? '';

// Préparer les conditions de filtre selon les droits
$whereConditions = ["s.event_date BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($isAdmin) {
    // Admin voit tout, peut filtrer par structure
    if (!empty($filterStructure)) {
        $whereConditions[] = "u.structure = ?";
        $params[] = $filterStructure;
    }
} elseif ($isStructureAdmin) {
    // Super-utilisateur voit sa catégorie
    $structureCodes = getStructureCodesInCategory($currentUser['structure']);
    $placeholders = implode(',', array_fill(0, count($structureCodes), '?'));
    $whereConditions[] = "u.structure IN ($placeholders)";
    $params = array_merge($params, $structureCodes);
} else {
    // Utilisateur standard voit uniquement ses feuilles
    $whereConditions[] = "s.user_id = ?";
    $params[] = $userId;
}

$whereClause = implode(' AND ', $whereConditions);

// Statistiques globales
$globalStatsQuery = db()->prepare("
    SELECT
        COUNT(DISTINCT s.id) as total_sheets,
        COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) as active_sheets,
        COUNT(DISTINCT CASE WHEN s.status = 'closed' THEN s.id END) as closed_sheets,
        COUNT(sig.id) as total_signatures,
        COUNT(DISTINCT sig.email) as unique_participants
    FROM sheets s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN signatures sig ON sig.sheet_id = s.id
    WHERE $whereClause
");
$globalStatsQuery->execute($params);
$globalStats = $globalStatsQuery->fetch();

// Signatures par mois (pour le graphique)
$monthlyQuery = db()->prepare("
    SELECT
        TO_CHAR(sig.signed_at, 'YYYY-MM') as month,
        COUNT(*) as count
    FROM signatures sig
    JOIN sheets s ON sig.sheet_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE $whereClause AND sig.signed_at IS NOT NULL
    GROUP BY month
    ORDER BY month
");
$monthlyQuery->execute($params);
$monthlyData = $monthlyQuery->fetchAll();

// Formater les données pour Chart.js
$monthLabels = [];
$monthCounts = [];
foreach ($monthlyData as $row) {
    $date = DateTime::createFromFormat('Y-m', $row['month']);
    $monthLabels[] = $date ? $date->format('M Y') : $row['month'];
    $monthCounts[] = (int)$row['count'];
}

// Feuilles par structure (top 10)
$structureQuery = db()->prepare("
    SELECT
        u.structure,
        COUNT(DISTINCT s.id) as sheet_count,
        COUNT(sig.id) as signature_count
    FROM sheets s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN signatures sig ON sig.sheet_id = s.id
    WHERE $whereClause AND u.structure IS NOT NULL AND u.structure != ''
    GROUP BY u.structure
    ORDER BY sheet_count DESC
    LIMIT 10
");
$structureQuery->execute($params);
$structureData = $structureQuery->fetchAll();

// Formater pour Chart.js
$structureLabels = [];
$structureCounts = [];
$structureColors = [
    'rgba(0, 112, 60, 0.8)',
    'rgba(0, 133, 74, 0.8)',
    'rgba(0, 154, 88, 0.8)',
    'rgba(40, 167, 69, 0.8)',
    'rgba(72, 180, 97, 0.8)',
    'rgba(102, 192, 123, 0.8)',
    'rgba(132, 204, 149, 0.8)',
    'rgba(162, 217, 175, 0.8)',
    'rgba(192, 229, 201, 0.8)',
    'rgba(222, 242, 227, 0.8)'
];
foreach ($structureData as $i => $row) {
    $structureLabels[] = normalizeStructureName($row['structure']);
    $structureCounts[] = (int)$row['sheet_count'];
}

// Top 10 événements par signatures
$topEventsQuery = db()->prepare("
    SELECT
        s.id,
        s.title,
        s.event_date,
        u.structure,
        COUNT(sig.id) as signature_count
    FROM sheets s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN signatures sig ON sig.sheet_id = s.id
    WHERE $whereClause
    GROUP BY s.id, s.title, s.event_date, u.structure
    ORDER BY signature_count DESC
    LIMIT 10
");
$topEventsQuery->execute($params);
$topEvents = $topEventsQuery->fetchAll();

// Liste des structures pour le filtre (admin seulement)
$allStructures = [];
if ($isAdmin) {
    $structuresQuery = db()->query("
        SELECT DISTINCT structure FROM users
        WHERE structure IS NOT NULL AND structure != ''
        ORDER BY structure
    ");
    $allStructures = $structuresQuery->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = 'Statistiques';
$extraCss = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-graph-up me-2"></i>Statistiques
    </h1>
    <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Retour
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="from" class="form-label">Date début</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= sanitize($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label for="to" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= sanitize($dateTo) ?>">
            </div>
            <?php if ($isAdmin && !empty($allStructures)): ?>
            <div class="col-md-4">
                <label for="structure" class="form-label">Structure</label>
                <select class="form-select" id="structure" name="structure">
                    <option value="">Toutes les structures</option>
                    <?php foreach ($allStructures as $struct): ?>
                        <option value="<?= sanitize($struct) ?>" <?= $filterStructure === $struct ? 'selected' : '' ?>>
                            <?= sanitize(normalizeStructureName($struct)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques globales -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-primary">
            <div class="card-body text-center">
                <div class="display-5 text-primary fw-bold"><?= $globalStats['total_sheets'] ?></div>
                <div class="text-muted">Feuilles créées</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-success">
            <div class="card-body text-center">
                <div class="display-5 text-success fw-bold"><?= $globalStats['total_signatures'] ?></div>
                <div class="text-muted">Signatures totales</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-info">
            <div class="card-body text-center">
                <div class="display-5 text-info fw-bold"><?= $globalStats['unique_participants'] ?></div>
                <div class="text-muted">Participants uniques</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-warning">
            <div class="card-body text-center">
                <?php
                $avgSignatures = $globalStats['total_sheets'] > 0
                    ? round($globalStats['total_signatures'] / $globalStats['total_sheets'], 1)
                    : 0;
                ?>
                <div class="display-5 text-warning fw-bold"><?= $avgSignatures ?></div>
                <div class="text-muted">Moyenne par feuille</div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Signatures par mois</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Feuilles par structure</h5>
            </div>
            <div class="card-body">
                <canvas id="structureChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top 10 événements -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Top 10 événements</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($topEvents)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox display-4"></i>
                <p class="mt-2">Aucun événement pour cette période</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Événement</th>
                            <th>Date</th>
                            <th>Structure</th>
                            <th class="text-end">Signatures</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topEvents as $i => $event): ?>
                        <tr>
                            <td>
                                <?php if ($i < 3): ?>
                                    <span class="badge bg-<?= $i === 0 ? 'warning' : ($i === 1 ? 'secondary' : 'danger') ?>">
                                        <?= $i + 1 ?>
                                    </span>
                                <?php else: ?>
                                    <?= $i + 1 ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $event['id'] ?>">
                                    <?= sanitize($event['title']) ?>
                                </a>
                            </td>
                            <td><?= formatDateFr($event['event_date']) ?></td>
                            <td><small class="text-muted"><?= sanitize(normalizeStructureName($event['structure'] ?? '')) ?></small></td>
                            <td class="text-end">
                                <span class="badge bg-primary"><?= $event['signature_count'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique mensuel
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: [{
                label: 'Signatures',
                data: <?= json_encode($monthCounts) ?>,
                borderColor: 'rgb(0, 112, 60)',
                backgroundColor: 'rgba(0, 112, 60, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });

    // Graphique par structure
    const structureCtx = document.getElementById('structureChart').getContext('2d');
    new Chart(structureCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($structureLabels) ?>,
            datasets: [{
                data: <?= json_encode($structureCounts) ?>,
                backgroundColor: <?= json_encode(array_slice($structureColors, 0, count($structureLabels))) ?>
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: { size: 10 }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
