<?php
// Cheranalloor Faces — Authentication Helper

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Log in a user by saving their ID in the session.
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

/**
 * Log out the current user.
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Retrieve the current logged-in user details.
 */
function getCurrentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Enforce that the user is logged in. Redirect to login if not.
 */
function requireLogin() {
    $user = getCurrentUser();
    if (!$user) {
        $_SESSION['flash_message'] = "Please log in to access this page.";
        $_SESSION['flash_type'] = "info";
        header("Location: " . BASE_URI . "login");
        exit;
    }
    return $user;
}

/**
 * Enforce that the user is an administrator. Redirect to home if not.
 */
function requireAdmin() {
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        $_SESSION['flash_message'] = "Access denied. Administrator privileges required.";
        $_SESSION['flash_type'] = "error";
        header("Location: " . BASE_URI);
        exit;
    }
    return $user;
}


/**
 * Get flash message and clear it from the session.
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $msg = [
            'text' => $_SESSION['flash_message'],
            'type' => isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info'
        ];
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return $msg;
    }
    return null;
}

/**
 * Set a flash message.
 */
function setFlashMessage($text, $type = 'info') {
    $_SESSION['flash_message'] = $text;
    $_SESSION['flash_type'] = $type;
}
?>
