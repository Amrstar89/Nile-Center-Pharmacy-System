<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/auth/logout                                               ║
 * ║                                                                             ║
 *  Headers: Authorization: Bearer <access_token>                                ║
 *  Body: { "all_devices": false }                                               ║
 *  Response: { "success": true, "message": "Logged out successfully" }          ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
}

$userPayload = apiAuth();
$userId = $userPayload['sub'] ?? null;
$params = apiParams();
$allDevices = !empty($params['all_devices']);

// Revoke current access token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    jwtRevoke($matches[1]);
}

// If logout from all devices, revoke all tokens for this user
if ($allDevices && $userId) {
    jwtRevokeAllForUser($userId);
}

// Log activity
try {
    $db = getTenantDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, user_name, action, table_name, record_id, ip_address, new_value)
        VALUES (?, ?, 'api_logout', 'users', ?, ?, ?)
    ");
    $stmt->execute([
        $userId, 
        $userPayload['name'] ?? 'User', 
        $userId, 
        $ip,
        json_encode(['all_devices' => $allDevices])
    ]);
} catch (Exception $e) {
    // Non-critical
}

apiSuccess([], $allDevices ? 'تم تسجيل الخروج من جميع الأجهزة' : 'تم تسجيل الخروج بنجاح');
