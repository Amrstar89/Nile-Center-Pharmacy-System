<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/auth/login - MATCHES REAL DATABASE SCHEMA                ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
}

$params = apiParams();
apiRequire($params, ['username', 'password']);

$username = trim($params['username']);
$password = $params['password'];

$tenantResolution = resolveTenant();
if (!$tenantResolution->isResolved()) {
    apiError('TENANT_NOT_FOUND', 'لم يتم العثور على المستأجر', 404);
}
if ($tenantResolution->isExpired()) {
    apiError('TENANT_EXPIRED', 'الاشتراك منتهي - يرجى تجديد الاشتراك', 403);
}
$tenant = $tenantResolution->tenant;

try {
    $db = getTenantDB($tenant);

    $stmt = $db->prepare("SELECT id, username, password, full_name, role, branch_code, phone, is_active, last_login FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        apiError('INVALID_CREDENTIALS', 'اسم المستخدم أو كلمة المرور غير صحيحة', 401);
    }

    $tokens = jwtGeneratePair($user, $tenant);

    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, 'api_login', 'users', ?, ?)")->execute([$user['id'], $user['id'], $ip]);
    } catch (Exception $e) {}

    apiSuccess([
        'access_token'  => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'],
        'expires_in'    => $tokens['expires_in'],
        'token_type'    => $tokens['token_type'],
        'user' => [
            'id'          => (int) $user['id'],
            'name'        => $user['full_name'],
            'username'    => $user['username'],
            'role'        => $user['role'],
            'branch_code' => $user['branch_code'],
        ],
        'tenant' => [
            'id'        => (int) $tenant['id'],
            'name'      => $tenant['business_name_ar'],
            'slug'      => $tenant['slug'],
            'status'    => $tenant['status'],
            'is_trial'  => $tenantResolution->isTrial(),
            'features'  => json_decode($tenant['features_json'] ?? '{}', true),
        ],
    ], 'Login successful');

} catch (Exception $e) {
    error_log("[API Login] Error: " . $e->getMessage());
    apiError('LOGIN_ERROR', 'حدث خطأ أثناء تسجيل الدخول', 500);
}
