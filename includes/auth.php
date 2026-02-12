<?php
/**
 * e-Présence - Fonctions d'authentification
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/migrations.php';

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Obtenir l'ID de l'utilisateur connecté
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Obtenir les informations de l'utilisateur connecté
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $stmt = db()->prepare("SELECT id, email, first_name, last_name, function_title, structure, is_structure_admin, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute(array(getCurrentUserId()));
    $result = $stmt->fetch();
    return $result ? $result : null;
}

/**
 * Vérifier si l'utilisateur connecté est admin
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Obtenir le rôle de l'utilisateur connecté
 */
function getCurrentUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'user';
}

/**
 * Inscrire un nouvel utilisateur
 */
function registerUser($email, $password, $firstName, $lastName, $structure = null, $functionTitle = null) {
    // Validation
    $errors = [];

    if (empty($email) || !isValidEmail($email)) {
        $errors[] = "L'adresse email n'est pas valide.";
    } elseif (!isAllowedEmailDomain($email)) {
        $errors[] = getEmailDomainError();
    }

    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }

    if (empty($firstName)) {
        $errors[] = "Le prénom est obligatoire.";
    }

    if (empty($lastName)) {
        $errors[] = "Le nom est obligatoire.";
    }

    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Cette adresse email est déjà utilisée.";
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Hachage du mot de passe
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insertion avec statut 'pending' (en attente de validation admin)
    try {
        $stmt = db()->prepare("
            INSERT INTO users (email, password, first_name, last_name, function_title, structure, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            strtolower(trim($email)),
            $hashedPassword,
            trim($firstName),
            trim($lastName),
            $functionTitle ? trim($functionTitle) : null,
            $structure ? trim($structure) : null
        ]);

        $userId = db()->lastInsertId();

        // Préparer les données utilisateur pour les emails
        $userData = [
            'id' => $userId,
            'email' => strtolower(trim($email)),
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'function_title' => $functionTitle ? trim($functionTitle) : null,
            'structure' => $structure ? trim($structure) : null
        ];

        // Envoyer email de notification à l'admin
        notifyAdminNewRegistration($userData);

        // Envoyer email de confirmation à l'utilisateur
        notifyUserRegistrationPending($userData);

        return ['success' => true, 'user_id' => $userId, 'pending' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ["Erreur lors de l'inscription. Veuillez réessayer."]];
    }
}

/**
 * Connecter un utilisateur
 */
function loginUser($email, $password) {
    // Validation
    if (empty($email) || empty($password)) {
        return ['success' => false, 'error' => "Veuillez remplir tous les champs."];
    }

    // Rechercher l'utilisateur
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => "Email ou mot de passe incorrect."];
    }

    // Vérifier le statut du compte
    if (isset($user['status'])) {
        if ($user['status'] === 'pending') {
            return ['success' => false, 'error' => "Votre compte est en attente de validation par un administrateur. Vous recevrez un email une fois votre compte activé."];
        }
        if ($user['status'] === 'suspended') {
            return ['success' => false, 'error' => "Votre compte a été suspendu. Contactez l'administrateur à " . MAIL_ADMIN];
        }
    }

    // Créer la session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = isset($user['role']) ? $user['role'] : 'user';

    // Régénérer l'ID de session pour éviter le fixation de session
    session_regenerate_id(true);

    return ['success' => true, 'user' => $user];
}

/**
 * Déconnecter l'utilisateur
 */
function logoutUser() {
    // Détruire toutes les variables de session
    $_SESSION = [];

    // Détruire le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Détruire la session
    session_destroy();
}

/**
 * Exiger une connexion (redirection si non connecté)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Vous devez être connecté pour accéder à cette page.');
        redirect(SITE_URL . '/pages/auth/login.php');
    }
}

/**
 * Rediriger si déjà connecté
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        redirect(SITE_URL . '/pages/dashboard/index.php');
    }
}

/**
 * Exiger le rôle admin (redirection si non admin)
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'Accès réservé aux administrateurs.');
        redirect(SITE_URL . '/pages/dashboard/index.php');
    }
}

/**
 * Obtenir tous les utilisateurs (admin seulement)
 */
function getAllUsers() {
    $stmt = db()->prepare("SELECT id, email, first_name, last_name, function_title, structure, is_structure_admin, role, status, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Mettre à jour le rôle d'un utilisateur
 */
function updateUserRole($userId, $role) {
    if (!in_array($role, array('user', 'admin'))) {
        return false;
    }
    $stmt = db()->prepare("UPDATE users SET role = ? WHERE id = ?");
    return $stmt->execute(array($role, $userId));
}

/**
 * Supprimer un utilisateur
 */
function deleteUser($userId) {
    // Ne pas permettre de supprimer son propre compte
    if ($userId == getCurrentUserId()) {
        return false;
    }
    $stmt = db()->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute(array($userId));
}

/**
 * Suspendre ou activer un utilisateur
 */
function toggleUserStatus($userId, $sendEmail = true) {
    // Ne pas permettre de se suspendre soi-même
    if ($userId == getCurrentUserId()) {
        return false;
    }

    // Récupérer l'utilisateur pour connaître son statut actuel
    $user = getUserById($userId);
    if (!$user) {
        return false;
    }

    // Ne pas permettre de toggler un compte pending (utiliser approveUser ou rejectUser)
    if ($user['status'] === 'pending') {
        return false;
    }

    $newStatus = ($user['status'] === 'active') ? 'suspended' : 'active';

    // PostgreSQL utilise CASE WHEN au lieu de IF
    $stmt = db()->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'suspended' ELSE 'active' END WHERE id = ?");
    $result = $stmt->execute(array($userId));

    // Envoyer email de notification
    if ($result && $sendEmail) {
        $user['status'] = $newStatus; // Mettre à jour pour l'email
        if ($newStatus === 'suspended') {
            notifyUserAccountSuspended($user);
        } else {
            notifyUserAccountReactivated($user);
        }
    }

    return $result;
}

/**
 * Obtenir les utilisateurs en attente de validation
 */
function getPendingUsers() {
    $stmt = db()->prepare("SELECT id, email, first_name, last_name, function_title, structure, is_structure_admin, role, status, created_at FROM users WHERE status = 'pending' ORDER BY created_at ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Compter les utilisateurs en attente
 */
function countPendingUsers() {
    $stmt = db()->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? intval($result['count']) : 0;
}

/**
 * Approuver un utilisateur en attente
 */
function approveUser($userId) {
    // Récupérer l'utilisateur
    $user = getUserById($userId);
    if (!$user || $user['status'] !== 'pending') {
        return false;
    }

    $adminId = getCurrentUserId();
    $stmt = db()->prepare("UPDATE users SET status = 'active', approved_at = CURRENT_TIMESTAMP, approved_by = ? WHERE id = ? AND status = 'pending'");
    $result = $stmt->execute(array($adminId, $userId));

    // Envoyer email de notification
    if ($result) {
        notifyUserAccountApproved($user);
    }

    return $result;
}

/**
 * Rejeter un utilisateur en attente (suppression)
 */
function rejectUser($userId, $reason = null) {
    // Récupérer l'utilisateur pour l'email
    $user = getUserById($userId);
    if (!$user || $user['status'] !== 'pending') {
        return false;
    }

    // Envoyer email de notification AVANT suppression
    notifyUserAccountRejected($user, $reason);

    // Supprimer l'utilisateur
    $stmt = db()->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'");
    return $stmt->execute(array($userId));
}

/**
 * Obtenir un utilisateur par ID
 */
function getUserById($userId) {
    $stmt = db()->prepare("SELECT id, email, first_name, last_name, function_title, structure, is_structure_admin, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute(array($userId));
    return $stmt->fetch();
}

/**
 * Mettre à jour un utilisateur (admin)
 */
function updateUser($userId, $data) {
    $allowedFields = array('first_name', 'last_name', 'function_title', 'structure', 'is_structure_admin', 'role', 'status');
    $updates = array();
    $params = array();

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (empty($updates)) {
        return false;
    }

    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}
