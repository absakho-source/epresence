<?php
/**
 * e-Présence - Mot de passe oublié
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/email.php';

// Rediriger si déjà connecté
redirectIfLoggedIn();

$error = '';
$success = '';
$email = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = "Session expirée. Veuillez réessayer.";
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = "Veuillez entrer votre adresse email.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Adresse email invalide.";
        } else {
            // Vérifier si l'utilisateur existe
            $stmt = db()->prepare("SELECT id, first_name, last_name, email, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Vérifier que le compte est actif
                if ($user['status'] !== 'active') {
                    $error = "Ce compte n'est pas actif. Contactez l'administrateur.";
                } else {
                    // Générer un token unique
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Supprimer les anciens tokens pour cet utilisateur
                    $deleteStmt = db()->prepare("DELETE FROM password_resets WHERE user_id = ?");
                    $deleteStmt->execute([$user['id']]);

                    // Enregistrer le nouveau token
                    $insertStmt = db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $insertStmt->execute([$user['id'], $token, $expiresAt]);

                    // Envoyer l'email
                    $resetLink = SITE_URL . '/pages/auth/reset-password.php?token=' . $token;
                    $emailSent = sendPasswordResetEmail($user, $resetLink);

                    if ($emailSent) {
                        $success = "Un email de réinitialisation a été envoyé à votre adresse. Vérifiez votre boîte de réception (et vos spams).";
                        $email = ''; // Vider le champ
                    } else {
                        $error = "Erreur lors de l'envoi de l'email. Veuillez réessayer plus tard.";
                    }
                }
            } else {
                // Ne pas révéler si l'email existe ou non (sécurité)
                $success = "Si cette adresse email est associée à un compte, vous recevrez un email de réinitialisation.";
                $email = '';
            }
        }
    }

    // Régénérer le token CSRF
    regenerateCsrfToken();
}

$pageTitle = 'Mot de passe oublié';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="bi bi-key me-2"></i>Mot de passe oublié</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= sanitize($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i><?= sanitize($success) ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-4">
                        Entrez l'adresse email associée à votre compte. Vous recevrez un lien pour créer un nouveau mot de passe.
                    </p>

                    <form method="POST" action="" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-4">
                            <label for="email" class="form-label">Adresse email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= sanitize($email) ?>" required autofocus
                                   placeholder="votre@email.com">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-envelope me-2"></i>Envoyer le lien
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
