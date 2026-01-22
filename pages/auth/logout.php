<?php
/**
 * e-Présence - Déconnexion
 */

require_once __DIR__ . '/../../includes/auth.php';

logoutUser();

setFlash('success', 'Vous avez été déconnecté avec succès.');
redirect(SITE_URL . '/pages/auth/login.php');
