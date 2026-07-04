<?php
/**
 * Nile Center - Pharmacy ERP System
 * Core Configuration File
 */

// Database Configuration
// ======================
// IMPORTANT: Update these settings after installation!
define('DB_HOST', 'localhost');
define('DB_NAME', 'nile_center');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP password is empty
define('DB_CHARSET', 'utf8mb4');

// Application Settings
// ====================
define('APP_NAME', 'نايل سنتر - نظام إدارة الصيدلية');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://26.201.9.238:8080/nile-center-system');  // Update with your server IP
define('TIMEZONE', 'Africa/Cairo');
define('SESSION_LIFETIME', 7200);  // 2 hours

// Security Settings
// ==================
define('HASH_COST', 10);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);  // 15 minutes

// Pagination
// ============
define('ITEMS_PER_PAGE', 25);

// Error Reporting (set to 0 in production)
// =========================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set Timezone
date_default_timezone_set(TIMEZONE);

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    session_regenerate_id(true);
}

// Database Connection
// ===================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Helper Functions
// =================

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (!is_array($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'],
        'username' => $_SESSION['username']
    ];
}

function logActivity($action, $table = null, $recordId = null, $oldValue = null, $newValue = null) {
    $db = getDB();
    $user = getCurrentUser();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, user_name, action, table_name, record_id, old_value, new_value, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'] ?? null,
        $user['name'] ?? 'Guest',
        $action,
        $table,
        $recordId,
        $oldValue ? json_encode($oldValue) : null,
        $newValue ? json_encode($newValue) : null,
        $ip
    ]);
}

function generateOrderNumber() {
    $db = getDB();
    $year = date('Y');
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE YEAR(created_at) = $year");
    $count = $stmt->fetch()['count'] + 1;
    return $year . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function showAlert($message, $type = 'success') {
    $icons = [
        'success' => 'check-circle',
        'error' => 'x-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle'
    ];
    $icon = $icons[$type] ?? 'info-circle';
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
        . '<i class="bi bi-' . $icon . ' me-2"></i> ' . $message
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
        . '</div>';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'منذ لحظات';
    if ($diff < 3600) return 'منذ ' . floor($diff / 60) . ' دقيقة';
    if ($diff < 86400) return 'منذ ' . floor($diff / 3600) . ' ساعة';
    if ($diff < 604800) return 'منذ ' . floor($diff / 86400) . ' يوم';
    return date('Y-m-d', $time);
}

// Arabic Date
define('AR_MONTHS', [
    'يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو',
    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
]);

function arabicDate($date) {
    $d = new DateTime($date);
    $day = $d->format('d');
    $month = AR_MONTHS[(int)$d->format('m') - 1];
    $year = $d->format('Y');
    return "$day $month $year";
}
