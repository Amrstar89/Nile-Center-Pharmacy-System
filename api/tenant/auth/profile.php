<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/auth/profile                                               ║
 * ║                                                                             ║
 *  Headers: Authorization: Bearer <access_token>                                ║
 *  Response: { "user": { "id", "name", "username", "role", "branch_id" },        ║
 *              "tenant": { "id", "name", "features" }, "permissions": [...] }    ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

// Authenticate
$userPayload = apiAuth();
$userId = $userPayload['sub'] ?? $userPayload['uid'] ?? null;
$tenantId = $userPayload['tenant_id'] ?? null;

try {
    // Get fresh user data from DB
    $db = getTenantDB();
    
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.full_name, u.email, u.phone, u.role,
               u.branch_id, u.store_id, u.avatar, u.is_active,
               b.branch_name as branch_name,
               s.store_name as store_name
        FROM users u
        LEFT JOIN branches b ON u.branch_id = b.id
        LEFT JOIN stores s ON u.store_id = s.id
        WHERE u.id = ? AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        apiError('USER_NOT_FOUND', 'المستخدم غير موجود أو غير نشط', 404);
    }
    
    // Get tenant info
    $tenant = getCurrentTenant();
    
    // Get permissions based on role
    $permissions = [];
    $role = $user['role'] ?? 'user';
    if ($role === 'admin') {
        $permissions = ['*']; // All permissions
    } elseif ($role === 'cashier') {
        $permissions = ['sales.create', 'sales.view', 'customers.view', 'products.view', 'inventory.view'];
    } else {
        $permissions = ['sales.view', 'customers.view', 'products.view'];
    }
    
    apiSuccess([
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'avatar' => $user['avatar'],
            'branch_id' => $user['branch_id'] ? (int) $user['branch_id'] : null,
            'branch_name' => $user['branch_name'],
            'store_id' => $user['store_id'] ? (int) $user['store_id'] : null,
            'store_name' => $user['store_name'],
        ],
        'tenant' => $tenant ? [
            'id' => (int) $tenant['id'],
            'name' => $tenant['business_name_ar'],
            'name_en' => $tenant['business_name_en'],
            'slug' => $tenant['slug'],
            'features' => json_decode($tenant['features_json'] ?? '{}', true),
        ] : null,
        'permissions' => $permissions,
    ]);
    
} catch (Exception $e) {
    error_log("[API Profile] Error: " . $e->getMessage());
    apiError('PROFILE_ERROR', 'حدث خطأ أثناء جلب الملف الشخصي', 500);
}
