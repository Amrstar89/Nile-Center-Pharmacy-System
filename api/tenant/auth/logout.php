<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/auth/logout                                               ║
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

// Note: In production, add token to blacklist table
apiSuccess([], $allDevices ? 'تم تسجيل الخروج من جميع الأجهزة' : 'تم تسجيل الخروج بنجاح');
