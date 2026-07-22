<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/auth/profile - MATCHES REAL DATABASE SCHEMA              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

$userPayload = apiAuth();
$userId = $userPayload['sub'] ?? null;

try {
    $db = getTenantDB();

    $stmt = $db->prepare("SELECT id, username, full_name, role, branch_code, phone, is_active, last_login FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        apiError('USER_NOT_FOUND', 'المستخدم غير موجود أو غير نشط', 404);
    }

    $tenant = getCurrentTenant();

    $permissions = match ($user['role']) {
        'admin' => ['*'],
        'pharmacist' => ['sales.create','sales.view','sales.edit','inventory.view','products.view','customers.view','customers.edit','reports.view'],
        'purchaser' => ['purchases.create','purchases.view','suppliers.view','products.view','inventory.view'],
        'cashier' => ['sales.create','sales.view','pos.access','customers.view','products.view'],
        default => ['sales.view','products.view'],
    };

    apiSuccess([
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['full_name'],
            'username' => $user['username'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'branch_code' => $user['branch_code'],
            'last_login' => $user['last_login'],
        ],
        'tenant' => $tenant ? [
            'id' => (int) $tenant['id'],
            'name' => $tenant['business_name_ar'],
            'slug' => $tenant['slug'],
            'features' => json_decode($tenant['features_json'] ?? '{}', true),
        ] : null,
        'permissions' => $permissions,
    ]);

} catch (Exception $e) {
    error_log("[API Profile] Error: " . $e->getMessage());
    apiError('PROFILE_ERROR', 'حدث خطأ أثناء جلب الملف الشخصي', 500);
}
