<?php
// Script temporaire de réinitialisation du mot de passe admin
require_once __DIR__ . '/includes/auth.php';

$newPassword = 'admin';
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$email = 'admin@economie.gouv.sn';
$stmt = db()->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->execute([$hashedPassword, $email]);

echo "OK - Mot de passe admin réinitialisé.";
