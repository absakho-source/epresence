<?php
/**
 * e-Présence - Administration
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireAdmin();

// Charger les structures du Ministère (groupées par catégorie)
$mepcStructuresGrouped = getMEPCStructuresGrouped();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME])) {
        setFlash('error', 'Token de sécurité invalide.');
        redirect(SITE_URL . '/pages/admin/index.php');
    }

    $action = $_POST['action'];
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($action === 'approve_user' && $userId > 0) {
        if (approveUser($userId)) {
            setFlash('success', 'Utilisateur approuvé avec succès. Un email de confirmation lui a été envoyé.');
        } else {
            setFlash('error', 'Impossible d\'approuver cet utilisateur.');
        }
    } elseif ($action === 'reject_user' && $userId > 0) {
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
        if (rejectUser($userId, $reason)) {
            setFlash('success', 'Demande d\'inscription rejetée. L\'utilisateur a été notifié par email.');
        } else {
            setFlash('error', 'Impossible de rejeter cette demande.');
        }
    } elseif ($action === 'change_role' && $userId > 0) {
        $newRole = $_POST['role'] === 'admin' ? 'admin' : 'user';
        if (updateUserRole($userId, $newRole)) {
            setFlash('success', 'Rôle mis à jour avec succès.');
        } else {
            setFlash('error', 'Erreur lors de la mise à jour du rôle.');
        }
    } elseif ($action === 'toggle_status' && $userId > 0) {
        if (toggleUserStatus($userId)) {
            setFlash('success', 'Statut de l\'utilisateur mis à jour. Un email de notification a été envoyé.');
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
            'function_title' => trim($_POST['function_title']),
            'structure' => trim($_POST['structure']),
            'is_structure_admin' => isset($_POST['is_structure_admin']),
            'role' => in_array($_POST['role'], array('user', 'admin')) ? $_POST['role'] : 'user',
            'status' => in_array($_POST['status'], array('pending', 'active', 'suspended')) ? $_POST['status'] : 'active',
        );
        if (updateUser($userId, $data)) {
            setFlash('success', 'Utilisateur mis à jour avec succès.');
        } else {
            setFlash('error', 'Erreur lors de la mise à jour.');
        }
    } elseif ($action === 'toggle_structure_admin' && $userId > 0) {
        // Toggle super-utilisateur structure
        $user = getUserById($userId);
        if ($user) {
            $newValue = empty($user['is_structure_admin']);
            $stmt = db()->prepare("UPDATE users SET is_structure_admin = ? WHERE id = ?");
            if ($stmt->execute([$newValue, $userId])) {
                setFlash('success', $newValue ? 'Utilisateur défini comme super-utilisateur de sa structure.' : 'Droits de super-utilisateur retirés.');
            } else {
                setFlash('error', 'Erreur lors de la mise à jour.');
            }
        }
    }

    redirect(SITE_URL . '/pages/admin/index.php');
}

// Statistiques globales
$globalStats = db()->query("
    SELECT
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
        (SELECT COUNT(*) FROM users WHERE is_structure_admin = true) as super_users,
        (SELECT COUNT(*) FROM users WHERE status = 'suspended') as suspended_users,
        (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
        (SELECT COUNT(*) FROM sheets) as total_sheets,
        (SELECT COUNT(*) FROM signatures) as total_signatures
")->fetch();

// Liste des utilisateurs en attente de validation
$pendingUsers = getPendingUsers();

// Liste des utilisateurs (actifs et suspendus uniquement)
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
    <a href="<?= SITE_URL ?>/pages/admin/migrate.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-tools me-1"></i>Maintenance
    </a>
</div>

<!-- Alerte inscriptions en attente -->
<?php if ($globalStats['pending_users'] > 0): ?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
    <div>
        <strong><?= $globalStats['pending_users'] ?> inscription<?= $globalStats['pending_users'] > 1 ? 's' : '' ?> en attente de validation.</strong>
        <a href="#pending" class="alert-link ms-2" onclick="document.getElementById('pending-tab').click()">Voir les demandes</a>
    </div>
</div>
<?php endif; ?>

<!-- Statistiques globales -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 border-primary">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-primary"><?= $globalStats['total_users'] ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 border-info">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-info"><?= $globalStats['pending_users'] ?></div>
                <div class="stat-label">En attente</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 border-danger">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-danger"><?= $globalStats['total_admins'] ?></div>
                <div class="stat-label">Admins</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100" style="border-color: #fd7e14;">
            <div class="card-body dashboard-stat">
                <div class="stat-number" style="color: #fd7e14;"><?= $globalStats['super_users'] ?></div>
                <div class="stat-label">Super utilisateurs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 border-success">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-success"><?= $globalStats['total_sheets'] ?></div>
                <div class="stat-label">Feuilles</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 border-secondary">
            <div class="card-body dashboard-stat">
                <div class="stat-number text-secondary"><?= $globalStats['total_signatures'] ?></div>
                <div class="stat-label">Signatures</div>
            </div>
        </div>
    </div>
</div>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $globalStats['pending_users'] > 0 ? '' : 'active' ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
            <i class="bi bi-people me-2"></i>Utilisateurs (<?= $globalStats['total_users'] ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $globalStats['pending_users'] > 0 ? 'active' : '' ?>" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
            <i class="bi bi-hourglass-split me-2"></i>En attente
            <?php if ($globalStats['pending_users'] > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $globalStats['pending_users'] ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="sheets-tab" data-bs-toggle="tab" data-bs-target="#sheets" type="button" role="tab">
            <i class="bi bi-file-earmark-text me-2"></i>Feuilles (<?= $globalStats['total_sheets'] ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="adminTabsContent">
    <!-- Onglet Inscriptions en attente -->
    <div class="tab-pane fade <?= $globalStats['pending_users'] > 0 ? 'show active' : '' ?>" id="pending" role="tabpanel">
        <div class="card">
            <div class="card-header bg-warning bg-opacity-25">
                <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Inscriptions en attente de validation</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingUsers)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-3 mb-0">Aucune inscription en attente.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Structure</th>
                                    <th>Fonction</th>
                                    <th>Date demande</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingUsers as $pUser): ?>
                                    <tr>
                                        <td><strong><?= sanitize($pUser['first_name'] . ' ' . $pUser['last_name']) ?></strong></td>
                                        <td>
                                            <a href="mailto:<?= sanitize($pUser['email']) ?>" class="text-decoration-none">
                                                <?= sanitize($pUser['email']) ?>
                                            </a>
                                        </td>
                                        <td><small><?= $pUser['structure'] ? sanitize(getStructureName($pUser['structure'])) : '<em class="text-muted">Non renseignée</em>' ?></small></td>
                                        <td><small><?= !empty($pUser['function_title']) ? sanitize($pUser['function_title']) : '<em class="text-muted">-</em>' ?></small></td>
                                        <td><small><?= formatDateFr($pUser['created_at'], true) ?></small></td>
                                        <td class="text-end">
                                            <!-- Bouton Approuver -->
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="approve_user">
                                                <input type="hidden" name="user_id" value="<?= $pUser['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success"
                                                        onclick="return confirm('Approuver cette inscription ?')"
                                                        title="Approuver">
                                                    <i class="bi bi-check-lg me-1"></i>Approuver
                                                </button>
                                            </form>
                                            <!-- Bouton Rejeter -->
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rejectModal"
                                                    data-user-id="<?= $pUser['id'] ?>"
                                                    data-user-name="<?= sanitize($pUser['first_name'] . ' ' . $pUser['last_name']) ?>"
                                                    title="Rejeter">
                                                <i class="bi bi-x-lg me-1"></i>Rejeter
                                            </button>
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

    <!-- Onglet Utilisateurs -->
    <div class="tab-pane fade <?= $globalStats['pending_users'] > 0 ? '' : 'show active' ?>" id="users" role="tabpanel">
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
                                        <?php if (!empty($user['function_title'])): ?>
                                            <br><small class="text-muted"><?= sanitize($user['function_title']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= sanitize($user['email']) ?></small></td>
                                    <td>
                                        <?php
                                            $userCategory = $user['structure'] ? getStructureCategory($user['structure']) : null;
                                            $isDGUser = ($userCategory === 'Direction générale');
                                        ?>
                                        <small><?= $user['structure'] ? sanitize(getStructureName($user['structure'])) : '-' ?></small>
                                        <?php if (!empty($user['is_structure_admin']) && $user['structure']): ?>
                                            <br><span class="badge <?= $isDGUser ? 'bg-danger' : 'bg-warning text-dark' ?>" title="<?= $isDGUser ? 'Voit TOUTES les feuilles de la DGPPE' : 'Voit les feuilles de: ' . sanitize($userCategory) ?>">
                                                <i class="bi bi-star-fill me-1"></i><?= $isDGUser ? 'DG' : 'Super utilisateur' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
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
                                                    data-function-title="<?= sanitize($user['function_title'] ?? '') ?>"
                                                    data-structure="<?= sanitize($user['structure']) ?>"
                                                    data-is-structure-admin="<?= !empty($user['is_structure_admin']) ? '1' : '0' ?>"
                                                    data-role="<?= $user['role'] ?>"
                                                    data-status="<?= isset($user['status']) ? $user['status'] : 'active' ?>"
                                                    title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if (!empty($user['structure'])): ?>
                                            <!-- Bouton Super-utilisateur -->
                                            <form method="POST" class="d-inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="toggle_structure_admin">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm <?= !empty($user['is_structure_admin']) ? 'btn-warning' : 'btn-outline-warning' ?>"
                                                        onclick="return confirm('<?= !empty($user['is_structure_admin']) ? 'Retirer les droits de super-utilisateur' : 'Définir comme super-utilisateur de sa structure' ?> ?')"
                                                        title="<?= !empty($user['is_structure_admin']) ? 'Retirer super-utilisateur' : 'Définir super-utilisateur' ?>">
                                                    <i class="bi bi-star<?= !empty($user['is_structure_admin']) ? '-fill' : '' ?>"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
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
                        <label for="editFunctionTitle" class="form-label">Fonction / Poste</label>
                        <input type="text" class="form-control" id="editFunctionTitle" name="function_title"
                               placeholder="Ex: Chef de service, Analyste...">
                    </div>
                    <div class="mb-3">
                        <label for="editStructure" class="form-label">Structure</label>
                        <select class="form-select" id="editStructure" name="structure">
                            <option value="">-- Aucune --</option>
                            <?php foreach ($mepcStructuresGrouped as $category => $structures): ?>
                                <optgroup label="<?= sanitize($category) ?>">
                                    <?php foreach ($structures as $structureName): ?>
                                        <option value="<?= sanitize($structureName) ?>"><?= sanitize($structureName) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editIsStructureAdmin" name="is_structure_admin">
                            <label class="form-check-label" for="editIsStructureAdmin">
                                <i class="bi bi-star-fill text-warning me-1"></i>Super-utilisateur de structure
                            </label>
                            <div class="form-text">Peut voir les feuilles de sa catégorie de structure. <strong>Services propres</strong> ou <strong>Direction générale</strong> = accès à toutes les feuilles de sa catégorie.</div>
                        </div>
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
                                <option value="pending">En attente</option>
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

<!-- Modal Rejet Inscription -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reject_user">
                <input type="hidden" name="user_id" id="rejectUserId">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="bi bi-x-circle me-2"></i>Rejeter l'inscription
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p>Vous allez rejeter l'inscription de <strong id="rejectUserName"></strong>.</p>
                    <p class="text-muted">L'utilisateur sera notifié par email et sa demande sera supprimée.</p>

                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Motif du rejet (optionnel)</label>
                        <textarea class="form-control" id="rejectReason" name="reason" rows="3"
                                  placeholder="Ex: Adresse email non reconnue, doublon de compte..."></textarea>
                        <div class="form-text">Ce motif sera inclus dans l'email envoyé à l'utilisateur.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i>Rejeter l'inscription
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
    document.getElementById('editFunctionTitle').value = button.getAttribute('data-function-title') || '';
    document.getElementById('editStructure').value = button.getAttribute('data-structure') || '';
    document.getElementById('editIsStructureAdmin').checked = button.getAttribute('data-is-structure-admin') === '1';
    document.getElementById('editRole').value = button.getAttribute('data-role');
    document.getElementById('editStatus').value = button.getAttribute('data-status');
});

// Remplir le modal de rejet
document.getElementById('rejectModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('rejectUserId').value = button.getAttribute('data-user-id');
    document.getElementById('rejectUserName').textContent = button.getAttribute('data-user-name');
    document.getElementById('rejectReason').value = '';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
