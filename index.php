<?php
/**
 * e-Présence DGPPE - Page d'accueil
 */

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Accueil';
$pageDescription = SITE_DESCRIPTION;
$bodyClass = 'home-page';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section text-center">
    <div class="container">
        <!-- Logo -->
        <div class="hero-logos">
            <img src="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>" alt="Logo MEPC">
        </div>

        <h1 class="h2 fw-bold mb-2">
            e-Présence <small class="fs-5 opacity-75">MEPC</small>
        </h1>
        <p class="mb-1">
            Système d'émargement électronique par QR code
        </p>
        <p class="mb-3 opacity-75 small">
            <?= MINISTRY_NAME ?>
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/pages/dashboard/index.php" class="btn btn-light">
                    <i class="bi bi-speedometer2 me-2"></i>Tableau de bord
                </a>
                <a href="<?= SITE_URL ?>/pages/dashboard/create.php" class="btn btn-outline-light">
                    <i class="bi bi-plus-circle me-2"></i>Nouvelle feuille
                </a>
            <?php else: ?>
                <a href="<?= SITE_URL ?>/pages/auth/login.php" class="btn btn-light">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-2">
    <div class="container">
        <h2 class="text-center mb-2 h4">Comment ça marche ?</h2>
        <div class="row g-2">
            <div class="col-md-4">
                <div class="card h-100 feature-card">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-file-earmark-plus"></i>
                        </div>
                        <h4>1. Créez votre feuille</h4>
                        <p class="text-muted">
                            Renseignez les informations de votre réunion : titre, date, lieu.
                            Un QR code unique est automatiquement généré.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 feature-card">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                        <h4>2. Partagez le QR code</h4>
                        <p class="text-muted">
                            Affichez le QR code sur écran, imprimez-le ou partagez le lien.
                            Les participants scannent avec leur téléphone.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 feature-card">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-vector-pen"></i>
                        </div>
                        <h4>3. Signez électroniquement</h4>
                        <p class="text-muted">
                            Chaque participant remplit ses informations et signe avec son doigt
                            sur mobile ou sa souris sur PC.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2 mt-0">
            <div class="col-md-4">
                <div class="card h-100 feature-card">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-eye"></i>
                        </div>
                        <h4>4. Suivez en temps réel</h4>
                        <p class="text-muted">
                            Visualisez les signatures au fur et à mesure qu'elles arrivent
                            depuis votre tableau de bord.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 feature-card">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </div>
                        <h4>5. Exportez vos documents</h4>
                        <p class="text-muted">
                            Téléchargez votre feuille d'émargement en PDF (format A4 paysage),
                            Excel ou JSON selon vos besoins.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 feature-card">
                    <div class="card-body">
                        <div class="feature-icon">
                            <i class="bi bi-archive"></i>
                        </div>
                        <h4>6. Archivez facilement</h4>
                        <p class="text-muted">
                            Conservez un historique de toutes vos feuilles d'émargement,
                            accessibles à tout moment.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="bg-light py-2">
    <div class="container text-center">
        <?php if (!isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/pages/auth/login.php" class="btn btn-primary">
                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
            </a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/pages/dashboard/create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Créer une feuille d'émargement
            </a>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
