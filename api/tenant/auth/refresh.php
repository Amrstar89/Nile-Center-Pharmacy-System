<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/auth/refresh                                              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
}

$params = apiParams();
apiRequire($params, ['refresh_token']);

try {
    $newTokens = jwtRefresh($params['refresh_token']);
    apiSuccess([
        'access_token' => $newTokens['access_token'],
        'refresh_token' => $newTokens['refresh_token'],
        'expires_in' => $newTokens['expires_in'],
        'token_type' => $newTokens['token_type'],
    ], 'Token refreshed successfully');
} catch (Exception $e) {
    $code = str_contains($e->getMessage(), 'TOKEN_EXPIRED') ? 'REFRESH_TOKEN_EXPIRED' : 'REFRESH_FAILED';
    $msg = str_contains($e->getMessage(), 'TOKEN_EXPIRED') ? 'انتهت صلاحية الجلسة - يرجى تسجيل الدخول مرة أخرى' : 'فشل تجديد التوكن';
    apiError($code, $msg, 401);
}
