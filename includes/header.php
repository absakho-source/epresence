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

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>">
    <link rel="shortcut icon" href="<?= SITE_URL ?>/favicon.ico">

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
<body class="<?= sanitize($bodyClass) ?>" data-site-url="<?= SITE_URL ?>">
    <!-- En-tête officiel -->
    <header class="official-header bg-white border-bottom">
        <div class="container py-2">
            <div class="row align-items-center">
                <div class="col-auto">
                    <a href="<?= SITE_URL ?>" class="d-flex align-items-center text-decoration-none">
                        <img src="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>" alt="Logo MEPC" height="60" class="me-3">
                    </a>
                </div>
                <div class="col">
                    <div class="official-titles">
                        <div class="ministry-name text-muted small"><?= MINISTRY_NAME ?></div>
                        <div class="platform-name text-primary fw-bold">Plateforme d'Émargement Électronique (e-Présence)</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navbar Principal -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dgppe">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center d-lg-none" href="<?= SITE_URL ?>">
                <span class="fw-bold"><?= SITE_NAME ?></span>
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
                        <li class="nav-item">
                            <a class="nav-link" href="<?= SITE_URL ?>/pages/dashboard/statistics.php">
                                <i class="bi bi-graph-up me-1"></i>Statistiques
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
                        <!-- Barre de recherche globale -->
                        <li class="nav-item me-3 d-none d-lg-block">
                            <div class="position-relative" id="globalSearchWrapper">
                                <input type="search" class="form-control form-control-sm bg-white bg-opacity-10 border-0 text-white"
                                       id="globalSearchInput" placeholder="Rechercher..." style="width: 200px;"
                                       autocomplete="off">
                                <div id="searchResults" class="dropdown-menu shadow" style="width: 350px; max-height: 400px; overflow-y: auto;"></div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>
                                <span class="d-none d-md-inline"><?= sanitize($_SESSION['user_name'] ?? 'Utilisateur') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>/pages/dashboard/index.php"><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</a></li>
                                <li><a class="dropdown-item" href="<?= SITE_URL ?>/pages/dashboard/profile.php"><i class="bi bi-person me-2"></i>Mon profil</a></li>
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
