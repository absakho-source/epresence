<?php
/**
 * e-Présence - Configuration générale (PRODUCTION)
 * Last update: 2026-01-20 12:15
 */

// Mode debug (désactiver en production)
define('DEBUG_MODE', getenv('DEBUG_MODE') === 'false' ? false : true);

// Configuration du site
define('SITE_NAME', 'e-Présence');
// Détecte automatiquement l'URL sur Render ou utilise la valeur par défaut
$renderUrl = getenv('RENDER_EXTERNAL_URL');
$siteUrl = getenv('SITE_URL') ?: ($renderUrl ? $renderUrl : 'https://epresence.onrender.com');
define('SITE_URL', rtrim($siteUrl, '/'));
define('SITE_DESCRIPTION', 'Système d\'émargement électronique du Ministère de l\'Économie, du Plan et de la Coopération');

// Configuration Ministère
define('ORG_NAME', 'MEPC');
define('ORG_FULL_NAME', 'Ministère de l\'Économie, du Plan et de la Coopération');
define('MINISTRY_NAME', 'Ministère de l\'Économie, du Plan et de la Coopération');
define('LOGO_DGPPE', 'logo-dgppe.png'); // Conservé pour rétrocompatibilité
define('LOGO_MEPC', 'logo-mepc.png');

// Configuration des sessions
define('SESSION_NAME', 'epresence_session');
define('SESSION_LIFETIME', 3600 * 24); // 24 heures

// Configuration des chemins
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PAGES_PATH', BASE_PATH . '/pages');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Configuration des uploads
define('MAX_SIGNATURE_SIZE', 500000); // 500KB max pour les signatures
define('MAX_DOCUMENT_SIZE', 10 * 1024 * 1024); // 10 MB max pour les documents

// Chemin des uploads - utilise le disque persistant Render si disponible
$persistentDisk = '/var/data/uploads';
$persistentDocuments = $persistentDisk . '/documents';
$localUploads = BASE_PATH . '/uploads';
$localDocuments = $localUploads . '/documents';

// Tester si le disque persistant est réellement utilisable
$usePersistentDisk = false;
if (is_dir($persistentDisk) && is_writable($persistentDisk)) {
    // Essayer de créer le dossier documents
    if (!is_dir($persistentDocuments)) {
        $usePersistentDisk = @mkdir($persistentDocuments, 0755, true);
    } else {
        $usePersistentDisk = is_writable($persistentDocuments);
    }
}

if ($usePersistentDisk) {
    define('UPLOADS_PATH', $persistentDisk);
    define('DOCUMENTS_PATH', $persistentDocuments);
} else {
    define('UPLOADS_PATH', $localUploads);
    define('DOCUMENTS_PATH', $localDocuments);
    // Créer le dossier local si nécessaire
    if (!is_dir(DOCUMENTS_PATH)) {
        @mkdir(DOCUMENTS_PATH, 0755, true);
    }
}

// Configuration CSRF
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LIFETIME', 7200); // 2 heures (plus de temps pour les mobiles)

// Timezone
date_default_timezone_set('Africa/Dakar');

// Configuration Email
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@economie.gouv.sn');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'e-Présence MEPC');
define('MAIL_ADMIN', getenv('MAIL_ADMIN') ?: 'epresence@economie.gouv.sn');
define('MAIL_SMTP_HOST', getenv('MAIL_SMTP_HOST') ?: '');
define('MAIL_SMTP_PORT', getenv('MAIL_SMTP_PORT') ?: 587);
define('MAIL_SMTP_USER', getenv('MAIL_SMTP_USER') ?: '');
define('MAIL_SMTP_PASS', getenv('MAIL_SMTP_PASS') ?: '');
define('MAIL_SMTP_SECURE', getenv('MAIL_SMTP_SECURE') ?: 'tls');

// Gestion des erreurs selon le mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    // Sur Render, utiliser le disque persistant pour les sessions
    if (getenv('RENDER') && is_dir('/var/data/uploads')) {
        $sessionPath = '/var/data/uploads/sessions';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0700, true);
        }
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }
    }

    // Configuration des cookies de session pour mobile
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax' // Important pour les mobiles - 'Lax' au lieu de 'Strict'
    ]);

    session_name(SESSION_NAME);
    session_start();
}
