<?php
/**
 * e-Présence - Fonctions d'authentification
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

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

    $stmt = db()->prepare("SELECT id, email, first_name, last_name, structure, role, created_at FROM users WHERE id = ?");
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
function registerUser($email, $password, $firstName, $lastName, $structure = null) {
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

    // Insertion
    try {
        $stmt = db()->prepare("
            INSERT INTO users (email, password, first_name, last_name, structure)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            strtolower(trim($email)),
            $hashedPassword,
            trim($firstName),
            trim($lastName),
            $structure ? trim($structure) : null
        ]);

        return ['success' => true, 'user_id' => db()->lastInsertId()];
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

    // Vérifier si le compte est suspendu
    if (isset($user['status']) && $user['status'] === 'suspended') {
        return ['success' => false, 'error' => "Votre compte a été suspendu. Contactez l'administrateur."];
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
    $stmt = db()->prepare("SELECT id, email, first_name, last_name, structure, role, status, created_at FROM users ORDER BY created_at DESC");
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
function toggleUserStatus($userId) {
    // Ne pas permettre de se suspendre soi-même
    if ($userId == getCurrentUserId()) {
        return false;
    }
    $stmt = db()->prepare("UPDATE users SET status = IF(status = 'active', 'suspended', 'active') WHERE id = ?");
    return $stmt->execute(array($userId));
}

/**
 * Obtenir un utilisateur par ID
 */
function getUserById($userId) {
    $stmt = db()->prepare("SELECT id, email, first_name, last_name, structure, role, status, created_at FROM users WHERE id = ?");
    $stmt->execute(array($userId));
    return $stmt->fetch();
}

/**
 * Mettre à jour un utilisateur (admin)
 */
function updateUser($userId, $data) {
    $allowedFields = array('first_name', 'last_name', 'structure', 'role', 'status');
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
