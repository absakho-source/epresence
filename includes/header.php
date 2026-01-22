<?php
/**
 * e-Présence DGPPE - En-tête commun
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Variables par défaut
$pageTitle = $pageTitle ?? SITE_NAME;
$pageDescription = $pageDescription ?? SITE_DESCRIPTION;
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= sanitize($pageDescription) ?>">
    <title><?= sanitize($pageTitle) ?> | <?= SITE_NAME ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">

    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body class="<?= sanitize($bodyClass) ?>">
    <!-- Navbar Principal -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dgppe">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= SITE_URL ?>">
                <div class="brand-logo-wrapper me-2">
                    <img src="<?= SITE_URL ?>/assets/img/<?= LOGO_DGPPE ?>" alt="Logo DGPPE" height="40">
                </div>
                <div class="brand-text">
                    <span class="d-block fw-bold brand-title"><?= SITE_NAME ?></span>
                    <small class="d-none d-sm-block brand-subtitle"><?= ORG_NAME ?></small>
                </div>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto ms-lg-4">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/pages/dashboard/index.php">
                                <i class="bi bi-speedometer2 me-1"></i>Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/pages/dashboard/create.php">
                                <i class="bi bi-plus-circle me-1"></i>Nouvelle feuille
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link text-warning" href="<?= SITE_URL ?>/pages/admin/index.php">
                                <i class="bi bi-shield-lock me-1"></i>Administration
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav align-items-center">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>
                                <span class="d-none d-md-inline"><?= sanitize($_SESSION['user_name'] ?? 'Utilisateur') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>/pages/dashboard/index.php"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</a></li>
                                <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item text-warning" href="<?= SITE_URL ?>/pages/admin/index.php"><i class="bi bi-shield-lock me-2"></i>Administration</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>/pages/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-light btn-sm" href="<?= SITE_URL ?>/pages/auth/login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Connexion
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <main class="py-4">
        <div class="container">
            <?= displayFlash() ?>
