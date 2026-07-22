<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/auth/refresh                                              ║
 * ║                                                                             ║
 *  Body: { "refresh_token": "..." }                                              ║
 *  Response: { "access_token", "refresh_token", "expires_in", "token_type" }     ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
}

$params = apiParams();
apiRequire($params, ['refresh_token']);

$refreshToken = $params['refresh_token'];

try {
    $newTokens = jwtRefresh($refreshToken);
    apiSuccess([
        'access_token' => $newTokens['access_token'],
        'refresh_token' => $newTokens['refresh_token'],
        'expires_in' => $newTokens['expires_in'],
        'token_type' => $newTokens['token_type'],
    ], 'Token refreshed successfully');
} catch (Exception $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'TOKEN_EXPIRED') !== false) {
        apiError('REFRESH_TOKEN_EXPIRED', 'انتهت صلاحية جلسة التسجيل - يرجى تسجيل الدخول مرة أخرى', 401);
    }
    apiError('REFRESH_FAILED', 'فشل تجديد التوكن - يرجى تسجيل الدخول مرة أخرى', 401);
}
