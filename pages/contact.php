<?php
/**
 * e-Présence - Page de contact
 */

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Contact';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0"><i class="bi bi-envelope me-2"></i>Contact</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-4">
                    Pour toute question, demande d'assistance ou signalement de problème concernant la plateforme <strong>e-Présence</strong>, contactez-nous :
                </p>

                <div class="d-flex align-items-start mb-3">
                    <i class="bi bi-envelope-fill text-primary me-3 fs-5 mt-1"></i>
                    <div>
                        <strong>Email</strong><br>
                        <a href="mailto:<?= MAIL_ADMIN ?>"><?= MAIL_ADMIN ?></a>
                    </div>
                </div>

                <hr>

                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Besoin d'un compte ?</strong> Si vous n'avez pas encore de compte, vous pouvez vous
                    <a href="<?= SITE_URL ?>/pages/auth/register.php">inscrire ici</a>.
                    Votre demande sera examinée par un administrateur.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
