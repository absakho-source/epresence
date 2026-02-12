<?php
/**
 * e-Présence - Réinitialisation du mot de passe
 */

require_once __DIR__ . '/../../includes/auth.php';

// Rediriger si déjà connecté
redirectIfLoggedIn();

$error = '';
$success = '';
$validToken = false;
$token = $_GET['token'] ?? '';

// Vérifier le token
if (empty($token)) {
    $error = "Lien de réinitialisation invalide.";
} else {
    // Vérifier si le token existe et n'est pas expiré
    $stmt = db()->prepare("
        SELECT pr.*, u.email, u.first_name, u.last_name
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();

    if (!$resetRequest) {
        $error = "Ce lien de réinitialisation est invalide ou a expiré. Veuillez faire une nouvelle demande.";
    } else {
        $validToken = true;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    // Vérifier le token CSRF
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = "Session expirée. Veuillez réessayer.";
    } else {
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Validations
        if (empty($password)) {
            $error = "Veuillez entrer un mot de passe.";
        } elseif (strlen($password) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères.";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = "Le mot de passe doit contenir au moins une majuscule.";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = "Le mot de passe doit contenir au moins une minuscule.";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = "Le mot de passe doit contenir au moins un chiffre.";
        } elseif ($password !== $passwordConfirm) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            // Mettre à jour le mot de passe
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $updateStmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $resetRequest['user_id']]);

            // Marquer le token comme utilisé
            $markUsedStmt = db()->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $markUsedStmt->execute([$resetRequest['id']]);

            $success = true;
        }
    }

    // Régénérer le token CSRF
    regenerateCsrfToken();
}

$pageTitle = 'Nouveau mot de passe';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="bi bi-lock-fill me-2"></i>Nouveau mot de passe</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($success === true): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h5 class="text-success mb-3">Mot de passe modifié !</h5>
                        <p class="text-muted">Votre mot de passe a été réinitialisé avec succès.</p>
                        <a href="<?= SITE_URL ?>/pages/auth/login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                        </a>
                    </div>
                <?php elseif (!empty($error) && !$validToken): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                        </div>
                        <h5 class="text-danger mb-3">Lien invalide</h5>
                        <p class="text-muted"><?= sanitize($error) ?></p>
                        <a href="<?= SITE_URL ?>/pages/auth/forgot-password.php" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat me-2"></i>Nouvelle demande
                        </a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= sanitize($error) ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted mb-3">
                        Bonjour <strong><?= sanitize($resetRequest['first_name']) ?></strong>,<br>
                        créez votre nouveau mot de passe.
                    </p>

                    <form method="POST" action="" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label for="password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" required autofocus>
                            <div class="form-text">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    Min. 8 caractères avec majuscule, minuscule et chiffre
                                </small>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Enregistrer
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center py-3">
                <a href="<?= SITE_URL ?>/pages/auth/login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i>Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
