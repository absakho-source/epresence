<?php
/**
 * e-Présence - Confirmation de signature
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$code = $_GET['code'] ?? '';

// Récupérer la feuille pour afficher le titre
$sheet = null;
if (!empty($code)) {
    $stmt = db()->prepare("SELECT * FROM sheets WHERE unique_code = ?");
    $stmt->execute([$code]);
    $sheet = $stmt->fetch();
}

$pageTitle = 'Signature confirmée';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> | <?= SITE_NAME ?></title>
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/img/<?= LOGO_DGPPE ?>">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/img/<?= LOGO_DGPPE ?>">
    <link rel="shortcut icon" href="<?= SITE_URL ?>/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .confirm-card {
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }
        .success-icon i {
            font-size: 60px;
            color: white;
        }
        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        .checkmark {
            animation: checkAnim 0.8s ease-out 0.3s forwards;
            opacity: 0;
        }
        @keyframes checkAnim {
            0% {
                opacity: 0;
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="card confirm-card">
        <div class="card-body text-center p-5">
            <div class="success-icon">
                <i class="bi bi-check-lg checkmark"></i>
            </div>

            <h2 class="text-success mb-3">Merci !</h2>
            <h4 class="mb-3">Votre signature a été enregistrée</h4>

            <?php if ($sheet): ?>
                <div class="alert alert-light mb-4">
                    <strong><?= sanitize($sheet['title']) ?></strong><br>
                    <small class="text-muted">
                        <?= formatDateFr($sheet['event_date']) ?>
                        <?php if ($sheet['event_time']): ?>
                            à <?= formatTime($sheet['event_time']) ?>
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>

            <p class="text-muted mb-4">
                Votre présence a bien été confirmée.<br>
                Vous pouvez fermer cette page.
            </p>

            <div class="d-grid gap-2">
                <?php if ($sheet && $sheet['status'] === 'active'): ?>
                    <a href="<?= SITE_URL ?>/pages/sign/index.php?code=<?= urlencode($code) ?>" class="btn btn-outline-primary">
                        <i class="bi bi-person-plus me-2"></i>Ajouter une autre signature
                    </a>
                <?php endif; ?>
                <a href="<?= SITE_URL ?>" class="btn btn-link text-muted">
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</body>
</html>
