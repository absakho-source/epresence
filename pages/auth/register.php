<?php
/**
 * e-Présence - Page d'inscription
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';

// Rediriger si déjà connecté
redirectIfLoggedIn();

// Charger les structures
$structuresGrouped = getStructuresGrouped();

$errors = array();
$email = '';
$firstName = '';
$lastName = '';
$structure = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME])) {
        $errors[] = "Session expirée. Veuillez réessayer.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $passwordConfirm = $_POST['password_confirm'];
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $structure = trim($_POST['structure']);

        // Vérifier la confirmation du mot de passe
        if ($password !== $passwordConfirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (empty($errors)) {
            $result = registerUser($email, $password, $firstName, $lastName, $structure);

            if ($result['success']) {
                setFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
                redirect(SITE_URL . '/pages/auth/login.php');
            } else {
                $errors = $result['errors'];
            }
        }
    }

    // Régénérer le token CSRF
    regenerateCsrfToken();
}

$pageTitle = 'Inscription';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Créer un compte</h4>
            </div>
            <div class="card-body p-4">
                <!-- Information sur le domaine autorisé -->
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Inscription réservée</strong><br>
                    Seules les adresses email <strong>@<?= ALLOWED_EMAIL_DOMAIN ?></strong> sont autorisées.
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

                <form method="POST" action="" novalidate>
                    <?= csrfField() ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                   value="<?= sanitize($firstName) ?>" required autofocus>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                   value="<?= sanitize($lastName) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse email <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= sanitize($email) ?>"
                                   placeholder="prenom.nom"
                                   required>
                            <span class="input-group-text">@<?= ALLOWED_EMAIL_DOMAIN ?></span>
                        </div>
                        <div class="form-text">Utilisez votre adresse email professionnelle.</div>
                    </div>

                    <div class="mb-3">
                        <label for="structure" class="form-label">Structure / Direction <span class="text-danger">*</span></label>
                        <select class="form-select" id="structure" name="structure" required>
                            <option value="">-- Sélectionnez votre structure --</option>
                            <?php foreach ($structuresGrouped as $category => $structures): ?>
                                <optgroup label="<?= sanitize($category) ?>">
                                    <?php foreach ($structures as $code => $name): ?>
                                        <option value="<?= sanitize($code) ?>" <?= $structure === $code ? 'selected' : '' ?>>
                                            <?= sanitize($name) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password"
                               minlength="8" required>
                        <div class="form-text">Minimum 8 caractères.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirm" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               minlength="8" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-plus me-2"></i>S'inscrire
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <span class="text-muted">Déjà un compte ?</span>
                <a href="<?= SITE_URL ?>/pages/auth/login.php" class="ms-1">Se connecter</a>
            </div>
        </div>
    </div>
</div>

<script>
// Ajouter automatiquement le domaine si l'utilisateur tape juste le préfixe
document.getElementById('email').addEventListener('blur', function() {
    var email = this.value.trim();
    // Si l'email ne contient pas @, ajouter le domaine
    if (email && email.indexOf('@') === -1) {
        this.value = email + '@<?= ALLOWED_EMAIL_DOMAIN ?>';
    }
    // Si l'email a un autre domaine, le remplacer
    else if (email && email.indexOf('@') !== -1) {
        var parts = email.split('@');
        if (parts[1] !== '<?= ALLOWED_EMAIL_DOMAIN ?>') {
            this.value = parts[0] + '@<?= ALLOWED_EMAIL_DOMAIN ?>';
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
