<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - JWT AUTHENTICATION                  ║
 * ║  Standalone JWT implementation (no external dependencies)                    ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/tenant-router.php';

define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'NileCenter_JWT_Secret_Key_2026_Change_In_Production');
define('JWT_ISSUER', $_ENV['JWT_ISSUER'] ?? 'nile-center-erp');
define('JWT_AUDIENCE', $_ENV['JWT_AUDIENCE'] ?? 'nile-center-mobile-app');
define('JWT_ACCESS_TOKEN_TTL', (int) ($_ENV['JWT_ACCESS_TOKEN_TTL'] ?? 3600));
define('JWT_REFRESH_TOKEN_TTL', (int) ($_ENV['JWT_REFRESH_TOKEN_TTL'] ?? 604800));

// ─── Base64URL ──────────────────────────────────────────────────────────────
function _jwtBase64UrlEncode(string $data): string { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); }
function _jwtBase64UrlDecode(string $data): string { return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4)); }

// ─── HMAC Signing ───────────────────────────────────────────────────────────
function _jwtSign(string $message, string $secret, string $algo = 'HS256'): string {
    return match ($algo) { 'HS256' => hash_hmac('sha256', $message, $secret, true), 'HS384' => hash_hmac('sha384', $message, $secret, true), 'HS512' => hash_hmac('sha512', $message, $secret, true), default => throw new Exception("Unsupported JWT algorithm: {$algo}") };
}

// ─── Generate Token ─────────────────────────────────────────────────────────
function jwtGenerate(array $payload, string $type = 'access', string $algo = 'HS256'): string {
    $now = time();
    $ttl = match ($type) { 'refresh' => JWT_REFRESH_TOKEN_TTL, 'reset' => 900, default => JWT_ACCESS_TOKEN_TTL };
    $claims = array_merge(['iss' => JWT_ISSUER, 'aud' => JWT_AUDIENCE, 'iat' => $now, 'nbf' => $now, 'exp' => $now + $ttl, 'jti' => bin2hex(random_bytes(16)), 'type' => $type], $payload);
    $header = _jwtBase64UrlEncode(json_encode(['alg' => $algo, 'typ' => 'JWT']));
    $body = _jwtBase64UrlEncode(json_encode($claims));
    return "{$header}.{$body}." . _jwtBase64UrlEncode(_jwtSign("{$header}.{$body}", JWT_SECRET, $algo));
}

function jwtGeneratePair(array $user, ?array $tenant = null): array {
    $tid = $tenant['id'] ?? null;
    $access = jwtGenerate(['sub' => $user['id'], 'uid' => $user['id'], 'username' => $user['username'] ?? null, 'name' => $user['full_name'] ?? $user['name'] ?? null, 'role' => $user['role'] ?? 'user', 'tenant_id' => $tid], 'access');
    $refresh = jwtGenerate(['sub' => $user['id'], 'uid' => $user['id'], 'tenant_id' => $tid, 'token_family' => bin2hex(random_bytes(8))], 'refresh');
    return ['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => JWT_ACCESS_TOKEN_TTL, 'token_type' => 'Bearer'];
}

// ─── Verify Token ───────────────────────────────────────────────────────────
function jwtVerify(string $token, ?string $expectedType = null): array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) throw new Exception('INVALID_TOKEN');
    [$headerB64, $bodyB64, $sigB64] = $parts;
    $header = json_decode(_jwtBase64UrlDecode($headerB64), true);
    if (!$header || !isset($header['alg'])) throw new Exception('INVALID_HEADER');
    if (!hash_equals(_jwtBase64UrlEncode(_jwtSign("{$headerB64}.{$bodyB64}", JWT_SECRET, $header['alg'])), $sigB64)) throw new Exception('INVALID_SIGNATURE');
    $payload = json_decode(_jwtBase64UrlDecode($bodyB64), true);
    if (!$payload) throw new Exception('INVALID_PAYLOAD');
    $now = time();
    if (isset($payload['exp']) && $payload['exp'] < $now) throw new Exception('TOKEN_EXPIRED');
    if (isset($payload['nbf']) && $payload['nbf'] > $now) throw new Exception('TOKEN_NOT_YET_VALID');
    if ($expectedType && (!isset($payload['type']) || $payload['type'] !== $expectedType)) throw new Exception('INVALID_TOKEN_TYPE');
    return $payload;
}

// ─── API Auth Helper ────────────────────────────────────────────────────────
function apiAuth(): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        http_response_code(401); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Authorization header missing']); exit;
    }
    try { return jwtVerify($matches[1], 'access'); }
    catch (Exception $e) {
        $code = str_contains($e->getMessage(), 'TOKEN_EXPIRED') ? 'TOKEN_EXPIRED' : 'UNAUTHORIZED';
        $msg = str_contains($e->getMessage(), 'TOKEN_EXPIRED') ? 'انتهت صلاحية التوكن - استخدم refresh token' : 'توكن غير صالح';
        http_response_code(401); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $code, 'message' => $msg]); exit;
    }
}

// ─── Refresh Token ──────────────────────────────────────────────────────────
function jwtRefresh(string $refreshToken): array {
    $payload = jwtVerify($refreshToken, 'refresh');
    $tenantId = $payload['tenant_id'] ?? null;
    $userId = $payload['sub'] ?? null;
    $db = $tenantId ? getTenantDB(['id' => $tenantId, 'database_name' => TENANT_DB_PREFIX . $tenantId]) : getDB();
    $stmt = $db->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$userId]); $user = $stmt->fetch();
    if (!$user || !$user['is_active']) throw new Exception('USER_NOT_FOUND');
    return jwtGeneratePair($user);
}
