<?php
/**
 * e-Présence - Modifier une feuille d'émargement
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);

// Récupérer la feuille
$stmt = db()->prepare("SELECT * FROM sheets WHERE id = ? AND user_id = ?");
$stmt->execute([$sheetId, getCurrentUserId()]);
$sheet = $stmt->fetch();

if (!$sheet) {
    setFlash('error', 'Feuille non trouvée.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

if ($sheet['status'] !== 'active') {
    setFlash('error', 'Impossible de modifier une feuille clôturée ou archivée.');
    redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);
}

$errors = [];
$formData = [
    'title' => $sheet['title'],
    'description' => $sheet['description'],
    'event_date' => $sheet['event_date'],
    'event_time' => $sheet['event_time'],
    'location' => $sheet['location']
];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = "Session expirée. Veuillez réessayer.";
    } else {
        $formData = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'event_date' => $_POST['event_date'] ?? '',
            'event_time' => $_POST['event_time'] ?? '',
            'location' => trim($_POST['location'] ?? '')
        ];

        if (empty($formData['title'])) {
            $errors[] = "Le titre est obligatoire.";
        }

        if (empty($formData['event_date'])) {
            $errors[] = "La date est obligatoire.";
        }

        if (empty($errors)) {
            try {
                $stmt = db()->prepare("
                    UPDATE sheets SET
                        title = ?,
                        description = ?,
                        event_date = ?,
                        event_time = ?,
                        location = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $formData['title'],
                    $formData['description'] ?: null,
                    $formData['event_date'],
                    $formData['event_time'] ?: null,
                    $formData['location'] ?: null,
                    $sheetId
                ]);

                setFlash('success', 'Feuille modifiée avec succès.');
                redirect(SITE_URL . '/pages/dashboard/view.php?id=' . $sheetId);

            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la modification. Veuillez réessayer.";
            }
        }
    }

    regenerateCsrfToken();
}

$pageTitle = 'Modifier - ' . $sheet['title'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheetId ?>" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0">Modifier la feuille</h1>
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
                               value="<?= sanitize($formData['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="3"><?= sanitize($formData['description']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="event_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="event_date" name="event_date"
                                   value="<?= sanitize($formData['event_date']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="event_time" class="form-label">Heure</label>
                            <input type="time" class="form-control" id="event_time" name="event_time"
                                   value="<?= sanitize($formData['event_time']) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="location" class="form-label">Lieu</label>
                        <input type="text" class="form-control" id="location" name="location"
                               value="<?= sanitize($formData['location']) ?>">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-circle me-2"></i>Enregistrer
                        </button>
                        <a href="<?= SITE_URL ?>/pages/dashboard/view.php?id=<?= $sheetId ?>" class="btn btn-outline-secondary btn-lg">
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
