<?php
/**
 * e-Présence - Page de profil utilisateur
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireLogin();

$user = getCurrentUser();
$mepcStructuresGrouped = getMEPCStructuresGrouped();

$errors = [];
$success = false;
$activeTab = 'info';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = "Session expirée. Veuillez réessayer.";
    } else {
        $action = $_POST['action'] ?? '';

        // Mise à jour des informations personnelles
        if ($action === 'update_info') {
            $activeTab = 'info';
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $functionTitle = trim($_POST['function_title'] ?? '');

            if (empty($firstName)) {
                $errors[] = "Le prénom est obligatoire.";
            }
            if (empty($lastName)) {
                $errors[] = "Le nom est obligatoire.";
            }

            if (empty($errors)) {
                try {
                    // Les administrateurs peuvent modifier leur propre structure
                    if (isAdmin()) {
                        $structure = trim($_POST['structure'] ?? '');
                        $stmt = db()->prepare("UPDATE users SET first_name = ?, last_name = ?, function_title = ?, structure = ? WHERE id = ?");
                        $stmt->execute([$firstName, $lastName, $functionTitle ?: null, $structure ?: null, $user['id']]);
                    } else {
                        // Les utilisateurs ne peuvent pas modifier leur structure
                        $stmt = db()->prepare("UPDATE users SET first_name = ?, last_name = ?, function_title = ? WHERE id = ?");
                        $stmt->execute([$firstName, $lastName, $functionTitle ?: null, $user['id']]);
                    }

                    // Mettre à jour la session
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;

                    setFlash('success', 'Informations mises à jour avec succès.');
                    redirect(SITE_URL . '/pages/dashboard/profile.php');
                } catch (PDOException $e) {
                    $errors[] = "Erreur lors de la mise à jour.";
                }
            }
        }

        // Changement de mot de passe
        if ($action === 'change_password') {
            $activeTab = 'password';
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Vérifier le mot de passe actuel
            $stmt = db()->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();

            if (!password_verify($currentPassword, $userData['password'])) {
                $errors[] = "Le mot de passe actuel est incorrect.";
            }

            if (strlen($newPassword) < 8) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
            } elseif (!preg_match('/[A-Z]/', $newPassword)) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins une majuscule.";
            } elseif (!preg_match('/[a-z]/', $newPassword)) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins une minuscule.";
            } elseif (!preg_match('/[0-9]/', $newPassword)) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins un chiffre.";
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = "Les nouveaux mots de passe ne correspondent pas.";
            }

            if (empty($errors)) {
                try {
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $user['id']]);

                    setFlash('success', 'Mot de passe modifié avec succès.');
                    redirect(SITE_URL . '/pages/dashboard/profile.php?tab=password');
                } catch (PDOException $e) {
                    $errors[] = "Erreur lors du changement de mot de passe.";
                }
            }
        }
    }

    regenerateCsrfToken();
}

// Recharger les données utilisateur
$user = getCurrentUser();

// Tab from URL
if (isset($_GET['tab']) && in_array($_GET['tab'], ['info', 'password'])) {
    $activeTab = $_GET['tab'];
}

$pageTitle = 'Mon profil';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0"><i class="bi bi-person-circle me-2"></i>Mon profil</h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Carte d'identité -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                        </div>
                    </div>
                    <div class="col">
                        <h4 class="mb-1"><?= sanitize($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                        <?php if (!empty($user['function_title'])): ?>
                            <p class="text-muted mb-1">
                                <i class="bi bi-briefcase me-1"></i><?= sanitize($user['function_title']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-muted mb-1">
                            <i class="bi bi-envelope me-1"></i><?= sanitize($user['email']) ?>
                        </p>
                        <p class="mb-0">
                            <?php if ($user['role'] === 'admin'): ?>
                                <span class="badge bg-danger"><i class="bi bi-shield-lock me-1"></i>Administrateur</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-person me-1"></i>Utilisateur</span>
                            <?php endif; ?>
                            <?php if (!empty($user['is_structure_admin'])): ?>
                                <span class="badge bg-warning text-dark ms-1"><i class="bi bi-star me-1"></i>Super-utilisateur</span>
                            <?php endif; ?>
                            <?php if ($user['structure']): ?>
                                <span class="badge bg-info ms-1"><?= sanitize(getStructureName($user['structure'])) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-auto text-end d-none d-md-block">
                        <small class="text-muted">
                            <i class="bi bi-calendar-check me-1"></i>
                            Membre depuis le <?= formatDateFr($user['created_at']) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglets -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'info' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-info" type="button">
                    <i class="bi bi-person me-2"></i>Informations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-password" type="button">
                    <i class="bi bi-key me-2"></i>Mot de passe
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Onglet Informations -->
            <div class="tab-pane fade <?= $activeTab === 'info' ? 'show active' : '' ?>" id="tab-info" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Modifier mes informations</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_info">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name"
                                           value="<?= sanitize($user['first_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name"
                                           value="<?= sanitize($user['last_name']) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse email</label>
                                <input type="email" class="form-control" id="email" value="<?= sanitize($user['email']) ?>" disabled>
                                <div class="form-text">L'adresse email ne peut pas être modifiée car elle sert d'identifiant de connexion.</div>
                            </div>

                            <div class="mb-3">
                                <label for="function_title" class="form-label">Fonction / Poste</label>
                                <input type="text" class="form-control" id="function_title" name="function_title"
                                       value="<?= sanitize($user['function_title'] ?? '') ?>"
                                       placeholder="Ex: Chef de service, Analyste, Coordonnateur...">
                            </div>

                            <div class="mb-4">
                                <label for="structure" class="form-label">Structure / Direction</label>
                                <?php if (isAdmin()): ?>
                                    <select class="form-select" id="structure" name="structure">
                                        <option value="">Sélectionner une structure...</option>
                                        <?php foreach ($mepcStructuresGrouped as $category => $structures): ?>
                                            <optgroup label="<?= sanitize($category) ?>">
                                                <?php foreach ($structures as $structureName): ?>
                                                    <option value="<?= sanitize($structureName) ?>"
                                                        <?= $user['structure'] === $structureName ? 'selected' : '' ?>>
                                                        <?= sanitize($structureName) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">En tant qu'administrateur, vous pouvez modifier votre structure.</div>
                                <?php else: ?>
                                    <input type="text" class="form-control" id="structure"
                                           value="<?= $user['structure'] ? sanitize($user['structure']) : 'Non définie' ?>" disabled>
                                    <div class="form-text">Seul un administrateur peut modifier votre structure.</div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Onglet Mot de passe -->
            <div class="tab-pane fade <?= $activeTab === 'password' ? 'show active' : '' ?>" id="tab-password" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-key me-2"></i>Changer mon mot de passe</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                                <div class="form-text">
                                    <small><i class="bi bi-info-circle me-1"></i>Min. 8 caractères avec majuscule, minuscule et chiffre</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                            </div>

                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-key me-2"></i>Modifier le mot de passe
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
