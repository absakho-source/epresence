<?php
/**
 * e-Présence - Créer une feuille d'émargement
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'event_date' => date('Y-m-d'),
    'event_time' => '',
    'end_time' => '',
    'auto_close' => false,
    'location' => ''
];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = "Session expirée. Veuillez réessayer.";
    } else {
        // Récupérer les données
        $formData = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'event_time' => $_POST['event_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'auto_close' => isset($_POST['auto_close']),
            'location' => trim($_POST['location'] ?? '')
        ];

        // Validation
        if (empty($formData['title'])) {
            $errors[] = "Le titre est obligatoire.";
        }

        if (empty($formData['event_date'])) {
            $errors[] = "La date est obligatoire.";
        }

        if (empty($errors)) {
            // Générer un code unique
            $uniqueCode = generateUniqueCode();

            // Vérifier l'unicité du code
            $checkStmt = db()->prepare("SELECT id FROM sheets WHERE unique_code = ?");
            $checkStmt->execute([$uniqueCode]);
            while ($checkStmt->fetch()) {
                $uniqueCode = generateUniqueCode();
                $checkStmt->execute([$uniqueCode]);
            }

            try {
                $stmt = db()->prepare("
                    INSERT INTO sheets (user_id, title, description, event_date, event_time, end_time, auto_close, location, unique_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    getCurrentUserId(),
                    $formData['title'],
                    $formData['description'] ?: null,
                    $formData['event_date'],
                    $formData['event_time'] ?: null,
                    $formData['end_time'] ?: null,
                    $formData['auto_close'] ? true : false,
                    $formData['location'] ?: null,
                    $uniqueCode
                ]);

                $sheetId = db()->lastInsertId();
                setFlash('success', 'Feuille d\'émargement créée avec succès !');
                redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);

            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la création. Veuillez réessayer.";
                if (DEBUG_MODE) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }

    regenerateCsrfToken();
}

$pageTitle = 'Nouvelle feuille';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0">Créer une feuille d'émargement</h1>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= sanitize($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label for="title" class="form-label">Titre de la réunion <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title"
                               value="<?= sanitize($formData['title']) ?>" required
                               placeholder="Ex: Réunion de service, Comité de pilotage...">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="3" placeholder="Description optionnelle de la réunion..."><?= sanitize($formData['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="event_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event_date" name="event_date"
                                   value="<?= sanitize($formData['event_date']) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="event_time" class="form-label">Heure de début</label>
                            <input type="time" class="form-control" id="event_time" name="event_time"
                                   value="<?= sanitize($formData['event_time']) ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="end_time" class="form-label">Heure de fin</label>
                            <input type="time" class="form-control" id="end_time" name="end_time"
                                   value="<?= sanitize($formData['end_time']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_close" name="auto_close"
                                   <?= $formData['auto_close'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_close">
                                Clôturer automatiquement à l'heure de fin
                            </label>
                            <div class="form-text">Si coché, les signatures ne seront plus acceptées après l'heure de fin.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="location" class="form-label">Lieu</label>
                        <input type="text" class="form-control" id="location" name="location"
                               value="<?= sanitize($formData['location']) ?>"
                               placeholder="Ex: Salle de réunion A, Bâtiment principal...">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Créer la feuille
                        </button>
                        <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-outline-secondary btn-lg">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
