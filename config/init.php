<?php
/**
 * Initialisation de la session avant config.php
 * Ce fichier est chargé en premier pour configurer les sessions
 */

// Configurer le chemin des sessions avant tout
$sessionDir = dirname(__DIR__) . '/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
}

// Vérifier si le répertoire est accessible en écriture
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    ini_set('session.save_path', $sessionDir);
}

// Charger la configuration principale
require_once __DIR__ . '/config.php';
