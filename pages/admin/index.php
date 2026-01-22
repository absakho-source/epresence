<?php
/**
 * e-Présence - Administration
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireAdmin();

// Charger les structures
$structuresGrouped = getStructuresGrouped();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME])) {
        setFlash('error', 'Token de sécurité invalide.');
        redirect(SITE_URL . '/pages/admin/index.php');
    }

    $action = $_POST['action'];
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($action === 'change_role' && $userId > 0) {
        $newRole = $_POST['role'] === 'admin' ? 'admin' : 'user';
        if (updateUserRole($userId, $newRole)) {
            setFlash('success', 'Rôle mis à jour avec succès.');
        } else {
            setFlash('error', 'Erreur lors de la mise à jour du rôle.');
        }
    } elseif ($action === 'toggle_status' && $userId > 0) {
        if (toggleUserStatus($userId)) {
            setFlash('success', 'Statut de l\'utilisateur mis à jour.');
        } else {
            setFlash('error', 'Impossible de modifier le statut de cet utilisateur.');
        }
    } elseif ($action === 'delete_user' && $userId > 0) {
        if (deleteUser($userId)) {
            setFlash('success', 'Utilisateur supprimé avec succès.');
        } else {
            setFlash('error', 'Impossible de supprimer cet utilisateur.');
        }
    } elseif ($action === 'edit_user' && $userId > 0) {
        $data = array(
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'structure' => trim($_POST['structure']),
            'role' => in_array($_POST['role'], array('user', 'admin')) ? $_POST['role'] : 'user',
            'status' => in_array($_POST['status'], array('active', 'suspended')) ? $_POST['status'] : 'active',
        );
        if (updateUser($userId, $data)) {
            setFlash('success', 'Utilisateur mis à jour avec succès.');
        } else {
            setFlash('error', 'Erreur lors de la mise à jour.');
        }
    }

    redirect(SITE_URL . '/pages/admin/index.php');
}

// Statistiques globales
$globalStats = db()->query("
    SELECT
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
        (SELECT COUNT(*) FROM users WHERE status = 'suspended') as suspended_users,
        (SELECT COUNT(*) FROM sheets) as total_sheets,
        (SELECT COUNT(*) FROM signatures) as total_signatures
")->fetch();

// Liste des utilisateurs
$users = getAllUsers();

// Liste de toutes les feuilles avec infos utilisateur
$allSheets = db()->query("
    SELECT s.*, u.first_name, u.last_name, u.email as user_email,
           (SELECT COUNT(*) FROM signatures WHERE sheet_id = s.id) as signature_count
    FROM sheets s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 50
")->fetchAll();

$pageTitle = 'Administration';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><i class="bi bi-shield-lock me-2"></i>Administration</h1>
</div>

<!-- Statistiques globales -->
<div class="row g-4 mb-4">
    <div class="col-md-3 col-6">
        <div class="card h-100 border-primary">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-primary"><?= $globalStats['total_users'] ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-danger">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-danger"><?= $globalStats['total_admins'] ?></div>
                <div class="stat-label">Administrateurs</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-warning">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-warning"><?= $globalStats['suspended_users'] ?></div>
                <div class="stat-label">Suspendus</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card h-100 border-success">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-success"><?= $globalStats['total_sheets'] ?></div>
                <div class="stat-label">Feuilles</div>
            </div>
        </div>
    </div>
</div>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
            <i class="bi bi-people me-2"></i>Utilisateurs (<?= $globalStats['total_users'] ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="sheets-tab" data-bs-toggle="tab" data-bs-target="#sheets" type="button" role="tab">
            <i class="bi bi-file-earmark-text me-2"></i>Feuilles (<?= $globalStats['total_sheets'] ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="adminTabsContent">
    <!-- Onglet Utilisateurs -->
    <div class="tab-pane fade show active" id="users" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Gestion des utilisateurs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Structure</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Inscrit le</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php $isCurrentUser = ($user['id'] == getCurrentUserId()); ?>
                                <tr class="<?= (isset($user['status']) && $user['status'] === 'suspended') ? 'table-warning' : '' ?>">
                                    <td>
                                        <strong><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                                        <?php if ($isCurrentUser): ?>
                                            <span class="badge bg-secondary ms-1">Vous</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= sanitize($user['email']) ?></small></td>
                                    <td><small><?= $user['structure'] ? sanitize(getStructureName($user['structure'])) : '-' ?></small></td>
                                    <td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Utilisateur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!isset($user['status']) || $user['status'] === 'active'): ?>
                                            <span class="badge bg-success">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Suspendu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= formatDateFr($user['created_at']) ?></small></td>
                                    <td class="text-end">
                                        <?php if (!$isCurrentUser): ?>
                                            <!-- Bouton Éditer -->
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editUserModal"
                                                    data-user-id="<?= $user['id'] ?>"
                                                    data-first-name="<?= sanitize($user['first_name']) ?>"
                                                    data-last-name="<?= sanitize($user['last_name']) ?>"
                                                    data-structure="<?= sanitize($user['structure']) ?>"
                                                    data-role="<?= $user['role'] ?>"
                                                    data-status="<?= isset($user['status']) ? $user['status'] : 'active' ?>"
                                                    title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <!-- Bouton Suspendre/Activer -->
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                                        onclick="return confirm('<?= (!isset($user['status']) || $user['status'] === 'active') ? 'Suspendre' : 'Réactiver' ?> cet utilisateur ?')"
                                                        title="<?= (!isset($user['status']) || $user['status'] === 'active') ? 'Suspendre' : 'Réactiver' ?>">
                                                    <i class="bi bi-<?= (!isset($user['status']) || $user['status'] === 'active') ? 'pause-circle' : 'play-circle' ?>"></i>
                                                </button>
                                            </form>
                                            <!-- Bouton Supprimer -->
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Supprimer définitivement cet utilisateur et toutes ses feuilles ?')"
                                                        title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Onglet Feuilles -->
    <div class="tab-pane fade" id="sheets" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Toutes les feuilles d'émargement</h5>
            </div>
            <div class="card-body">
                <?php if (empty($allSheets)): ?>
                    <p class="text-muted text-center py-4">Aucune feuille créée pour le moment.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Titre</th>
                                    <th>Créateur</th>
                                    <th>Date</th>
                                    <th>Lieu</th>
                                    <th>Signatures</th>
                                    <th>Statut</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allSheets as $sheet): ?>
                                    <?php
                                        $statusLabels = array('active' => 'Active', 'closed' => 'Clôturée', 'archived' => 'Archivée');
                                        $statusLabel = isset($statusLabels[$sheet['status']]) ? $statusLabels[$sheet['status']] : $sheet['status'];
                                    ?>
                                    <tr>
                                        <td><strong><?= sanitize($sheet['title']) ?></strong></td>
                                        <td>
                                            <small>
                                                <?= sanitize($sheet['first_name'] . ' ' . $sheet['last_name']) ?>
                                                <br><span class="text-muted"><?= sanitize($sheet['user_email']) ?></span>
                                            </small>
                                        </td>
                                        <td><?= formatDateFr($sheet['event_date']) ?></td>
                                        <td><?= $sheet['location'] ? sanitize($sheet['location']) : '-' ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $sheet['signature_count'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $sheet['status'] ?>"><?= $statusLabel ?></span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheet['id'] ?>"
                                               class="btn btn-sm btn-outline-primary" title="Voir">
                                                <i class="bi bi-eye"></i>
                                            </a>
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

<!-- Modal Édition Utilisateur -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="bi bi-pencil me-2"></i>Modifier l'utilisateur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editFirstName" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editLastName" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="editLastName" name="last_name" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editStructure" class="form-label">Structure</label>
                        <select class="form-select" id="editStructure" name="structure">
                            <option value="">-- Aucune --</option>
                            <?php foreach ($structuresGrouped as $category => $structures): ?>
                                <optgroup label="<?= sanitize($category) ?>">
                                    <?php foreach ($structures as $code => $name): ?>
                                        <option value="<?= sanitize($code) ?>"><?= sanitize($name) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editRole" class="form-label">Rôle</label>
                            <select class="form-select" id="editRole" name="role">
                                <option value="user">Utilisateur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editStatus" class="form-label">Statut</label>
                            <select class="form-select" id="editStatus" name="status">
                                <option value="active">Actif</option>
                                <option value="suspended">Suspendu</option>
                            </select>
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

<script>
// Remplir le modal avec les données de l'utilisateur
document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('editUserId').value = button.getAttribute('data-user-id');
    document.getElementById('editFirstName').value = button.getAttribute('data-first-name');
    document.getElementById('editLastName').value = button.getAttribute('data-last-name');
    document.getElementById('editStructure').value = button.getAttribute('data-structure') || '';
    document.getElementById('editRole').value = button.getAttribute('data-role');
    document.getElementById('editStatus').value = button.getAttribute('data-status');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
