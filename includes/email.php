<?php
/**
 * e-Presence - Fonctions d'envoi d'emails
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Envoyer un email (utilise mail() natif ou SMTP si configuré)
 */
function sendEmail($to, $subject, $htmlBody, $textBody = null) {
    // Si pas de corps texte, générer depuis HTML
    if ($textBody === null) {
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
    }

    // Headers
    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>';
    $headers[] = 'Reply-To: ' . MAIL_FROM;
    $headers[] = 'X-Mailer: e-Presence/1.0';

    // Utiliser la fonction mail() native de PHP
    $success = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));

    // Log en debug
    if (DEBUG_MODE) {
        $logMessage = date('Y-m-d H:i:s') . " - Email to: $to - Subject: $subject - Status: " . ($success ? 'OK' : 'FAILED') . "\n";
        @file_put_contents(BASE_PATH . '/logs/emails.log', $logMessage, FILE_APPEND);
    }

    return $success;
}

/**
 * Template de base pour les emails
 */
function getEmailTemplate($title, $content, $footerText = null) {
    if ($footerText === null) {
        $footerText = 'Cet email a été envoyé automatiquement par ' . SITE_NAME . '. Merci de ne pas y répondre.';
    }

    return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #0d6efd;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0d6efd;
            margin: 0;
            font-size: 24px;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .content {
            padding: 20px 0;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin: 15px 0;
        }
        .info-box strong {
            color: #0d6efd;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
        }
        .success-box {
            background: #d1e7dd;
            border-left: 4px solid #198754;
            padding: 15px;
            margin: 15px 0;
        }
        .danger-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #0d6efd;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 10px 0;
        }
        .btn:hover {
            background: #0b5ed7;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .footer a {
            color: #0d6efd;
        }
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table.details td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        table.details td:first-child {
            font-weight: bold;
            color: #666;
            width: 35%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . SITE_NAME . '</h1>
            <div class="subtitle">' . ORG_FULL_NAME . '</div>
        </div>
        <div class="content">
            ' . $content . '
        </div>
        <div class="footer">
            <p>' . htmlspecialchars($footerText) . '</p>
            <p><a href="' . SITE_URL . '">' . SITE_URL . '</a></p>
        </div>
    </div>
</body>
</html>';
}

/**
 * Notifier l'admin d'une nouvelle inscription en attente
 */
function notifyAdminNewRegistration($user) {
    $structureName = '';
    if (!empty($user['structure'])) {
        require_once __DIR__ . '/../config/structures.php';
        $structureName = getStructureName($user['structure']);
    }

    $content = '
        <h2>Nouvelle demande d\'inscription</h2>
        <p>Un nouvel utilisateur souhaite s\'inscrire sur la plateforme <strong>' . SITE_NAME . '</strong>.</p>

        <div class="warning-box">
            <strong>Action requise</strong><br>
            Cette inscription nécessite votre validation avant que l\'utilisateur puisse accéder à la plateforme.
        </div>

        <table class="details">
            <tr>
                <td>Nom complet</td>
                <td><strong>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</strong></td>
            </tr>
            <tr>
                <td>Email</td>
                <td><a href="mailto:' . htmlspecialchars($user['email']) . '">' . htmlspecialchars($user['email']) . '</a></td>
            </tr>
            <tr>
                <td>Structure</td>
                <td>' . ($structureName ? htmlspecialchars($structureName) : '<em>Non renseignée</em>') . '</td>
            </tr>
            <tr>
                <td>Date d\'inscription</td>
                <td>' . date('d/m/Y à H:i') . '</td>
            </tr>
        </table>

        <p style="text-align: center;">
            <a href="' . SITE_URL . '/pages/admin/index.php" class="btn">
                Gérer les inscriptions
            </a>
        </p>
    ';

    $subject = '[' . SITE_NAME . '] Nouvelle inscription en attente - ' . $user['first_name'] . ' ' . $user['last_name'];

    return sendEmail(MAIL_ADMIN, $subject, getEmailTemplate($subject, $content));
}

/**
 * Notifier l'utilisateur que son inscription est en attente
 */
function notifyUserRegistrationPending($user) {
    $content = '
        <h2>Inscription enregistrée</h2>
        <p>Bonjour <strong>' . htmlspecialchars($user['first_name']) . '</strong>,</p>

        <p>Votre demande d\'inscription sur <strong>' . SITE_NAME . '</strong> a bien été enregistrée.</p>

        <div class="info-box">
            <strong>En attente de validation</strong><br>
            Votre compte est actuellement en attente de validation par un administrateur.
            Vous recevrez un email dès que votre compte sera activé.
        </div>

        <table class="details">
            <tr>
                <td>Email</td>
                <td>' . htmlspecialchars($user['email']) . '</td>
            </tr>
            <tr>
                <td>Nom</td>
                <td>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</td>
            </tr>
        </table>

        <p>Merci de votre patience.</p>
    ';

    $subject = '[' . SITE_NAME . '] Inscription en attente de validation';

    return sendEmail($user['email'], $subject, getEmailTemplate($subject, $content));
}

/**
 * Notifier l'utilisateur que son compte a été approuvé
 */
function notifyUserAccountApproved($user) {
    $content = '
        <h2>Compte activé !</h2>
        <p>Bonjour <strong>' . htmlspecialchars($user['first_name']) . '</strong>,</p>

        <div class="success-box">
            <strong>Bonne nouvelle !</strong><br>
            Votre compte sur <strong>' . SITE_NAME . '</strong> a été validé par un administrateur.
        </div>

        <p>Vous pouvez dès maintenant vous connecter et utiliser toutes les fonctionnalités de la plateforme :</p>
        <ul>
            <li>Créer des feuilles d\'émargement</li>
            <li>Générer des QR codes pour vos réunions</li>
            <li>Suivre les signatures en temps réel</li>
            <li>Exporter vos données en PDF, Excel ou JSON</li>
        </ul>

        <p style="text-align: center;">
            <a href="' . SITE_URL . '/pages/auth/login.php" class="btn">
                Se connecter
            </a>
        </p>

        <p>Bienvenue sur ' . SITE_NAME . ' !</p>
    ';

    $subject = '[' . SITE_NAME . '] Votre compte a été activé';

    return sendEmail($user['email'], $subject, getEmailTemplate($subject, $content));
}

/**
 * Notifier l'utilisateur que son compte a été rejeté
 */
function notifyUserAccountRejected($user, $reason = null) {
    $reasonText = $reason ? '<p><strong>Motif :</strong> ' . htmlspecialchars($reason) . '</p>' : '';

    $content = '
        <h2>Demande d\'inscription refusée</h2>
        <p>Bonjour <strong>' . htmlspecialchars($user['first_name']) . '</strong>,</p>

        <div class="danger-box">
            <strong>Inscription non validée</strong><br>
            Nous sommes au regret de vous informer que votre demande d\'inscription sur <strong>' . SITE_NAME . '</strong> n\'a pas été approuvée.
        </div>

        ' . $reasonText . '

        <p>Si vous pensez qu\'il s\'agit d\'une erreur, veuillez contacter l\'administration à l\'adresse : <a href="mailto:' . MAIL_ADMIN . '">' . MAIL_ADMIN . '</a></p>
    ';

    $subject = '[' . SITE_NAME . '] Demande d\'inscription non approuvée';

    return sendEmail($user['email'], $subject, getEmailTemplate($subject, $content));
}

/**
 * Notifier l'utilisateur que son compte a été suspendu
 */
function notifyUserAccountSuspended($user, $reason = null) {
    $reasonText = $reason ? '<p><strong>Motif :</strong> ' . htmlspecialchars($reason) . '</p>' : '';

    $content = '
        <h2>Compte suspendu</h2>
        <p>Bonjour <strong>' . htmlspecialchars($user['first_name']) . '</strong>,</p>

        <div class="danger-box">
            <strong>Compte désactivé</strong><br>
            Votre compte sur <strong>' . SITE_NAME . '</strong> a été suspendu par un administrateur.
        </div>

        ' . $reasonText . '

        <p>Vous ne pouvez plus accéder à la plateforme jusqu\'à la réactivation de votre compte.</p>
        <p>Pour plus d\'informations, contactez l\'administration à : <a href="mailto:' . MAIL_ADMIN . '">' . MAIL_ADMIN . '</a></p>
    ';

    $subject = '[' . SITE_NAME . '] Votre compte a été suspendu';

    return sendEmail($user['email'], $subject, getEmailTemplate($subject, $content));
}

/**
 * Notifier l'utilisateur que son compte a été réactivé
 */
function notifyUserAccountReactivated($user) {
    $content = '
        <h2>Compte réactivé</h2>
        <p>Bonjour <strong>' . htmlspecialchars($user['first_name']) . '</strong>,</p>

        <div class="success-box">
            <strong>Bonne nouvelle !</strong><br>
            Votre compte sur <strong>' . SITE_NAME . '</strong> a été réactivé.
        </div>

        <p>Vous pouvez à nouveau vous connecter et utiliser la plateforme.</p>

        <p style="text-align: center;">
            <a href="' . SITE_URL . '/pages/auth/login.php" class="btn">
                Se connecter
            </a>
        </p>
    ';

    $subject = '[' . SITE_NAME . '] Votre compte a été réactivé';

    return sendEmail($user['email'], $subject, getEmailTemplate($subject, $content));
}

/**
 * Envoyer l'email de réinitialisation de mot de passe
 */
function sendPasswordResetEmail($user, $resetLink) {
    $content = '
        <h2>Réinitialisation de mot de passe</h2>
        <p>Bonjour <strong>' . htmlspecialchars($user['first_name']) . '</strong>,</p>

        <p>Vous avez demandé à réinitialiser votre mot de passe sur <strong>' . SITE_NAME . '</strong>.</p>

        <div class="info-box">
            <strong>Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</strong>
        </div>

        <p style="text-align: center;">
            <a href="' . htmlspecialchars($resetLink) . '" class="btn">
                Réinitialiser mon mot de passe
            </a>
        </p>

        <div class="warning-box">
            <strong>Important :</strong><br>
            Ce lien est valable pendant <strong>1 heure</strong> uniquement.<br>
            Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email.
        </div>

        <p style="font-size: 12px; color: #666;">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
            <a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a>
        </p>
    ';

    $subject = '[' . SITE_NAME . '] Réinitialisation de votre mot de passe';

    return sendEmail($user['email'], $subject, getEmailTemplate($subject, $content));
}

/**
 * Notifier l'utilisateur que son mot de passe a été modifié
 */
function notifyPasswordChanged($user, $byAdmin = false) {
    $content = '
        <h2>Mot de passe modifié</h2>
        <p>Bonjour <strong>' . htmlspecialchars($user['first_name']) . '</strong>,</p>

        <div class="info-box">
            ' . ($byAdmin
                ? '<strong>Un administrateur</strong> a réinitialisé votre mot de passe.'
                : 'Votre mot de passe sur <strong>' . SITE_NAME . '</strong> a été modifié avec succès.'
            ) . '
        </div>

        <table class="details">
            <tr>
                <td>Date</td>
                <td>' . date('d/m/Y à H:i') . '</td>
            </tr>
            <tr>
                <td>Compte</td>
                <td>' . htmlspecialchars($user['email']) . '</td>
            </tr>
        </table>

        <div class="warning-box">
            <strong>Ce n\'était pas vous ?</strong><br>
            Contactez immédiatement l\'administrateur à : <a href="mailto:' . MAIL_ADMIN . '">' . MAIL_ADMIN . '</a>
        </div>

        <p style="text-align: center;">
            <a href="' . SITE_URL . '/pages/auth/login.php" class="btn">
                Se connecter
            </a>
        </p>
    ';

    $subject = '[' . SITE_NAME . '] Votre mot de passe a été modifié';

    return sendEmail($user['email'], $subject, getEmailTemplate($subject, $content));
}
