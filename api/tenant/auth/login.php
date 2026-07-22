<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/auth/login                                                ║
 * ║                                                                             ║
 *  Body: { "username": "...", "password": "...", "device_name": "..." }          ║
 *  Response: { "access_token", "refresh_token", "expires_in", "token_type",      ║
 *              "user": { "id", "name", "username", "role" }, "tenant" }           ║
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
$deviceName = apiSanitize($params['device_name'] ?? 'Unknown Device');

// ─── Resolve Tenant ─────────────────────────────────────────────────────────

$tenantResolution = resolveTenant();
if (!$tenantResolution->isResolved()) {
    apiError('TENANT_NOT_FOUND', 
        'لم يتم العثور على المستأجر - يرجى التحقق من الدومين أو مفتاح API',
        404
    );
}

if ($tenantResolution->isExpired()) {
    apiError('TENANT_EXPIRED', 
        'الاشتراك منتهي - يرجى تجديد الاشتراك',
        403,
        ['subscription_ends_at' => $tenantResolution->tenant['subscription_ends_at']]
    );
}

$tenant = $tenantResolution->tenant;
$tenantId = $tenant['id'];

// ─── Authenticate User ──────────────────────────────────────────────────────

try {
    $db = getTenantDB($tenant);
    
    // Find user
    $stmt = $db->prepare("
        SELECT id, username, full_name, role, password, is_active, 
               branch_id, store_id, last_login
        FROM users 
        WHERE username = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        apiError('INVALID_CREDENTIALS', 'اسم المستخدم أو كلمة المرور غير صحيحة', 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Log failed attempt
        apiError('INVALID_CREDENTIALS', 'اسم المستخدم أو كلمة المرور غير صحيحة', 401);
    }
    
    // ─── Generate Tokens ──────────────────────────────────────────────────
    
    $userData = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'name' => $user['full_name'],
        'role' => $user['role'],
        'branch_id' => $user['branch_id'],
        'store_id' => $user['store_id'],
    ];
    
    $tokens = jwtGeneratePair($userData, $tenant);
    
    // ─── Update User ──────────────────────────────────────────────────────
    
    $stmt = $db->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // ─── Log Activity ─────────────────────────────────────────────────────
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, table_name, record_id, ip_address)
            VALUES (?, ?, 'api_login', 'users', ?, ?)
        ");
        $stmt->execute([$user['id'], $user['full_name'], $user['id'], $ip]);
    } catch (Exception $e) {
        // Non-critical - don't fail login if logging fails
    }
    
    // ─── Response ─────────────────────────────────────────────────────────
    
    apiSuccess([
        'access_token' => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'],
        'expires_in' => $tokens['expires_in'],
        'token_type' => $tokens['token_type'],
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['full_name'],
            'username' => $user['username'],
            'role' => $user['role'],
            'branch_id' => (int) $user['branch_id'],
            'store_id' => $user['store_id'] ? (int) $user['store_id'] : null,
        ],
        'tenant' => [
            'id' => (int) $tenant['id'],
            'name' => $tenant['business_name_ar'],
            'name_en' => $tenant['business_name_en'],
            'slug' => $tenant['slug'],
            'status' => $tenant['status'],
            'is_trial' => $tenantResolution->isTrial(),
            'trial_ends_at' => $tenant['trial_ends_at'],
            'subscription_ends_at' => $tenant['subscription_ends_at'],
            'features' => json_decode($tenant['features_json'] ?? '{}', true),
        ],
    ], 'Login successful');
    
} catch (Exception $e) {
    error_log("[API Login] Error: " . $e->getMessage());
    apiError('LOGIN_ERROR', 'حدث خطأ أثناء تسجيل الدخول - يرجى المحاولة مرة أخرى', 500);
}
