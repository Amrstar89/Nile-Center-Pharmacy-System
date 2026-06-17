<?php
/**
 * Authentication & Authorization Functions
 */

require_once __DIR__ . '/config.php';

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(APP_URL . '/index.php');
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        $_SESSION['error'] = 'غير مصرح لك بالوصول لهذه الصفحة';
        redirect(APP_URL . '/modules/customer-requests/index.php');
    }
}

function requireRole($roles) {
    requireAuth();
    if (!hasRole($roles)) {
        $_SESSION['error'] = 'غير مصرح لك بالوصول لهذه الصفحة';
        redirect(APP_URL . '/modules/customer-requests/index.php');
    }
}

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();

        // Update last login
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
           ->execute([$user['id']]);

        logActivity('login', 'users', $user['id']);
        return true;
    }
    return false;
}

function logout() {
    if (isLoggedIn()) {
        logActivity('logout', 'users', $_SESSION['user_id']);
    }
    session_destroy();
    redirect(APP_URL . '/index.php');
}

function checkSession() {
    if (isLoggedIn()) {
        $inactive = time() - ($_SESSION['login_time'] ?? 0);
        if ($inactive > SESSION_LIFETIME) {
            logout();
        }
        $_SESSION['login_time'] = time();
    }
}

// Auto-check session on every page load
checkSession();
