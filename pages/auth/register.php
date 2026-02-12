<?php
/**
 * e-Présence - Page d'inscription
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';

// Rediriger si déjà connecté
redirectIfLoggedIn();

// Charger les structures du Ministère (groupées par catégorie)
$mepcStructuresGrouped = getMEPCStructuresGrouped();

$errors = array();
$email = '';
$firstName = '';
$lastName = '';
$functionTitle = '';
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
        $functionTitle = trim($_POST['function_title'] ?? '');
        $structure = trim($_POST['structure']);

        // Vérifier la confirmation du mot de passe
        if ($password !== $passwordConfirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if (empty($errors)) {
            $result = registerUser($email, $password, $firstName, $lastName, $structure, $functionTitle);

            if ($result['success']) {
                setFlash('info', 'Inscription enregistrée ! Votre compte est en attente de validation par un administrateur. Vous recevrez un email une fois votre compte activé.');
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
        <div class="mb-3">
            <a href="<?= SITE_URL ?>/pages/auth/login.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Retour à la connexion
            </a>
        </div>
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Créer un compte</h4>
            </div>
            <div class="card-body p-4">
                <!-- Information sur l'inscription -->
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Inscription réservée</strong><br>
                    Seules les adresses email professionnelles sont acceptées.
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
                        <label for="email" class="form-label">Adresse email professionnelle <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= sanitize($email) ?>"
                               placeholder="prenom.nom@economie.gouv.sn"
                               required>
                        <div class="form-text" id="emailHelp">
                            <i class="bi bi-info-circle me-1"></i>
                            Utilisez votre adresse email professionnelle.
                        </div>
                        <div class="invalid-feedback">
                            Domaines acceptés : <?= getAllowedDomainsFormatted() ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="structure" class="form-label">Structure / Direction <span class="text-danger">*</span></label>
                        <select class="form-select" id="structure" name="structure" required>
                            <option value="">-- Sélectionnez votre structure --</option>
                            <?php foreach ($mepcStructuresGrouped as $category => $structures): ?>
                                <optgroup label="<?= sanitize($category) ?>">
                                    <?php foreach ($structures as $structureName): ?>
                                        <option value="<?= sanitize($structureName) ?>" <?= $structure === $structureName ? 'selected' : '' ?>>
                                            <?= sanitize($structureName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="function_title" class="form-label">Fonction / Poste</label>
                        <input type="text" class="form-control" id="function_title" name="function_title"
                               value="<?= sanitize($functionTitle) ?>"
                               placeholder="Ex: Chef de bureau, Analyste, Coordonnateur...">
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
// Validation en temps réel du domaine email
document.getElementById('email').addEventListener('input', function() {
    var email = this.value.trim().toLowerCase();
    var allowedDomains = <?= json_encode(ALLOWED_EMAIL_DOMAINS) ?>;
    var isValid = false;
    var helpText = document.getElementById('emailHelp');

    if (email && email.includes('@')) {
        var emailDomain = email.split('@')[1];
        isValid = allowedDomains.includes(emailDomain);
    }

    if (email && !isValid) {
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
        if (helpText) helpText.style.display = 'none';
    } else if (email && isValid) {
        this.classList.add('is-valid');
        this.classList.remove('is-invalid');
        if (helpText) helpText.style.display = 'block';
    } else {
        this.classList.remove('is-valid', 'is-invalid');
        if (helpText) helpText.style.display = 'block';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
