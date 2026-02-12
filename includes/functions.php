<?php
/**
 * e-Présence - Fonctions utilitaires
 */

/**
 * Nettoyer et sécuriser une chaîne de caractères
 */
function sanitize($input) {
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Générer un code unique pour les feuilles d'émargement
 */
function generateUniqueCode($length = 12) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[mt_rand(0, strlen($characters) - 1)];
    }
    return $code;
}

/**
 * Générer un token CSRF
 */
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(openssl_random_pseudo_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Vérifier le token CSRF
 */
function verifyCsrfToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }

    // Vérifier l'expiration
    if (isset($_SESSION['csrf_token_time'])) {
        if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_LIFETIME) {
            unset($_SESSION[CSRF_TOKEN_NAME], $_SESSION['csrf_token_time']);
            return false;
        }
    }

    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Régénérer le token CSRF
 */
function regenerateCsrfToken() {
    unset($_SESSION[CSRF_TOKEN_NAME], $_SESSION['csrf_token_time']);
    return generateCsrfToken();
}

/**
 * Afficher un champ CSRF caché
 */
function csrfField() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCsrfToken() . '">';
}

/**
 * Rediriger vers une URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Définir un message flash
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Obtenir et supprimer le message flash
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Afficher le message flash avec Bootstrap
 */
function displayFlash() {
    $flash = getFlash();
    if ($flash) {
        $alertClasses = array(
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        );
        $alertClass = isset($alertClasses[$flash['type']]) ? $alertClasses[$flash['type']] : 'alert-secondary';
        return sprintf(
            '<div class="alert %s alert-dismissible fade show" role="alert">%s<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>',
            $alertClass,
            sanitize($flash['message'])
        );
    }
    return '';
}

/**
 * Formater une date en français
 */
function formatDateFr($date, $withTime = false) {
    $timestamp = strtotime($date);
    if ($withTime) {
        return date('d/m/Y à H:i', $timestamp);
    }
    return date('d/m/Y', $timestamp);
}

/**
 * Formater une heure
 */
function formatTime($time) {
    return date('H:i', strtotime($time));
}

/**
 * Obtenir l'adresse IP du client
 */
function getClientIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    return $ip;
}

/**
 * Obtenir le User Agent
 */
function getUserAgent() {
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
}

/**
 * Domaines email autorisés pour l'inscription
 */
define('ALLOWED_EMAIL_DOMAINS', ['economie.gouv.sn', 'fongip.sn', 'ansd.sn']);
// Rétrocompatibilité
define('ALLOWED_EMAIL_DOMAIN', 'economie.gouv.sn');

/**
 * Valider une adresse email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Vérifier si l'email appartient à un domaine autorisé
 */
function isAllowedEmailDomain($email) {
    if (!isValidEmail($email)) {
        return false;
    }
    $parts = explode('@', strtolower($email));
    if (count($parts) !== 2) {
        return false;
    }
    return in_array($parts[1], ALLOWED_EMAIL_DOMAINS);
}

/**
 * Obtenir la liste des domaines autorisés formatée
 */
function getAllowedDomainsFormatted() {
    $domains = ALLOWED_EMAIL_DOMAINS;
    if (count($domains) === 1) {
        return '@' . $domains[0];
    }
    $last = array_pop($domains);
    return '@' . implode(', @', $domains) . ' ou @' . $last;
}

/**
 * Obtenir le message d'erreur pour domaine non autorisé
 */
function getEmailDomainError() {
    return "Seules les adresses email " . getAllowedDomainsFormatted() . " sont autorisées.";
}

/**
 * Valider un numéro de téléphone (format français)
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[\s\.\-]/', '', $phone);
    return preg_match('/^(?:\+33|0)[1-9](?:[0-9]{8})$/', $phone);
}

/**
 * Formater un numéro de téléphone
 */
function formatPhone($phone) {
    if ($phone === null || $phone === '') {
        return '';
    }
    $phone = preg_replace('/[\s\.\-]/', '', $phone);
    if (strlen($phone) === 10) {
        return implode(' ', str_split($phone, 2));
    }
    return $phone;
}

/**
 * Générer l'URL complète pour une feuille d'émargement
 */
function getSheetUrl($uniqueCode) {
    return SITE_URL . '/pages/sign/index.php?code=' . urlencode($uniqueCode);
}

/**
 * Vérifier si la requête est AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Répondre en JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
