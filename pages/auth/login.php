<?php
/**
 * e-Présence - Page de connexion
 */

require_once __DIR__ . '/../../includes/auth.php';

// Rediriger si déjà connecté
redirectIfLoggedIn();

$error = '';
$email = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = "Session expirée. Veuillez réessayer.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = loginUser($email, $password);

        if ($result['success']) {
            setFlash('success', 'Connexion réussie. Bienvenue !');
            redirect(SITE_URL . '/pages/dashboard/index.php');
        } else {
            $error = $result['error'];
        }
    }

    // Régénérer le token CSRF
    regenerateCsrfToken();
}

$pageTitle = 'Connexion';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Connexion</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= sanitize($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (DEBUG_MODE): ?>
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Mode test :</strong> Cliquez sur "Remplir" pour utiliser les identifiants de test.
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= sanitize($email) ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="mb-4 text-end">
                        <a href="<?= SITE_URL ?>/pages/auth/forgot-password.php" class="small text-decoration-none">
                            <i class="bi bi-key me-1"></i>Mot de passe oublié ?
                        </a>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                        </button>
                        <?php if (DEBUG_MODE): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="fillTestCredentials()">
                                <i class="bi bi-lightning me-1"></i>Remplir (test)
                            </button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (DEBUG_MODE): ?>
                <script>
                function fillTestCredentials() {
                    document.getElementById('email').value = 'admin@economie.gouv.sn';
                    document.getElementById('password').value = 'Admin@2025';
                }
                </script>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center py-3">
                <span class="text-muted">Pas encore de compte ?</span>
                <a href="<?= SITE_URL ?>/pages/auth/register.php" class="btn btn-outline-primary btn-sm ms-2">
                    <i class="bi bi-person-plus me-1"></i>S'inscrire
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
