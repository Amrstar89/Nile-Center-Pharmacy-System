<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - JWT AUTHENTICATION                  ║
 * ║                                                                              ║
 * ║  Standalone JWT implementation (no external dependencies)                    ║
 * ║  Supports: HS256, HS384, HS512                                               ║
 * ║  Features: Access tokens + Refresh tokens + Token blacklist                 ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/tenant-router.php';

// ─── Configuration ──────────────────────────────────────────────────────────

// JWT Secret - CHANGE THIS IN PRODUCTION! Use a strong random string.
// Generate with: openssl rand -base64 64
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'NileCenter_JWT_Secret_Key_2026_Change_In_Production_Use_OpenSSL_Rand_Base64_64');
define('JWT_ISSUER', $_ENV['JWT_ISSUER'] ?? 'nile-center-erp');
define('JWT_AUDIENCE', $_ENV['JWT_AUDIENCE'] ?? 'nile-center-mobile-app');

// Token lifetimes (in seconds)
define('JWT_ACCESS_TOKEN_TTL', (int) ($_ENV['JWT_ACCESS_TOKEN_TTL'] ?? 3600));        // 1 hour
define('JWT_REFRESH_TOKEN_TTL', (int) ($_ENV['JWT_REFRESH_TOKEN_TTL'] ?? 604800));     // 7 days
define('JWT_PASSWORD_RESET_TTL', (int) ($_ENV['JWT_PASSWORD_RESET_TTL'] ?? 900));      // 15 minutes

// ─── Token Blacklist (for logout) ───────────────────────────────────────────
// In production, use Redis or database table. Here we use a simple file-based cache.
$GLOBALS['jwt_blacklist'] = [];
$GLOBALS['jwt_blacklist_file'] = __DIR__ . '/../cache/jwt_blacklist.json';

function _jwtLoadBlacklist(): void {
    $file = $GLOBALS['jwt_blacklist_file'];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        $GLOBALS['jwt_blacklist'] = is_array($data) ? $data : [];
    }
}

function _jwtSaveBlacklist(): void {
    $file = $GLOBALS['jwt_blacklist_file'];
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($file, json_encode($GLOBALS['jwt_blacklist']));
}

function _jwtCleanExpiredBlacklist(): void {
    $now = time();
    $GLOBALS['jwt_blacklist'] = array_filter(
        $GLOBALS['jwt_blacklist'],
        fn($item) => ($item['expires'] ?? 0) > $now
    );
    _jwtSaveBlacklist();
}

// ─── Base64URL Encoding (JWT standard) ─────────────────────────────────────

function _jwtBase64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _jwtBase64UrlDecode(string $data): string {
    $padding = 4 - (strlen($data) % 4);
    if ($padding !== 4) {
        $data .= str_repeat('=', $padding);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

// ─── HMAC Signing ───────────────────────────────────────────────────────────

function _jwtSign(string $message, string $secret, string $algo = 'HS256'): string {
    switch ($algo) {
        case 'HS256': return hash_hmac('sha256', $message, $secret, true);
        case 'HS384': return hash_hmac('sha384', $message, $secret, true);
        case 'HS512': return hash_hmac('sha512', $message, $secret, true);
        default: throw new Exception("Unsupported JWT algorithm: {$algo}");
    }
}

// ─── JWT Token Generation ───────────────────────────────────────────────────

/**
 * Generate a JWT token
 * 
 * @param array $payload Custom claims
 * @param string $type 'access' | 'refresh' | 'reset'
 * @param string $algo Algorithm: HS256 | HS384 | HS512
 * @return string The JWT token
 */
function jwtGenerate(array $payload, string $type = 'access', string $algo = 'HS256'): string {
    $now = time();
    
    // Token-specific TTL
    $ttl = match ($type) {
        'refresh' => JWT_REFRESH_TOKEN_TTL,
        'reset' => JWT_PASSWORD_RESET_TTL,
        default => JWT_ACCESS_TOKEN_TTL,
    };
    
    // Standard claims
    $claims = [
        'iss' => JWT_ISSUER,           // Issuer
        'aud' => JWT_AUDIENCE,          // Audience
        'iat' => $now,                  // Issued at
        'nbf' => $now,                  // Not before
        'exp' => $now + $ttl,           // Expiration
        'jti' => bin2hex(random_bytes(16)), // JWT ID (unique)
        'type' => $type,                // Token type
    ];
    
    // Merge with custom payload
    $claims = array_merge($claims, $payload);
    
    // Build token parts
    $header = _jwtBase64UrlEncode(json_encode(['alg' => $algo, 'typ' => 'JWT']));
    $body = _jwtBase64UrlEncode(json_encode($claims));
    
    // Sign
    $signature = _jwtBase64UrlEncode(_jwtSign("{$header}.{$body}", JWT_SECRET, $algo));
    
    return "{$header}.{$body}.{$signature}";
}

/**
 * Generate access + refresh token pair for a user
 * 
 * @param array $user User data (id, username, full_name, role, etc.)
 * @param array|null $tenant Tenant data (optional)
 * @return array ['access_token', 'refresh_token', 'expires_in', 'token_type']
 */
function jwtGeneratePair(array $user, ?array $tenant = null): array {
    $tenantId = $tenant['id'] ?? null;
    
    $accessPayload = [
        'sub' => $user['id'],           // Subject (user ID)
        'uid' => $user['id'],           // User ID
        'username' => $user['username'] ?? null,
        'name' => $user['full_name'] ?? $user['name'] ?? null,
        'role' => $user['role'] ?? 'user',
        'tenant_id' => $tenantId,
    ];
    
    $refreshPayload = [
        'sub' => $user['id'],
        'uid' => $user['id'],
        'tenant_id' => $tenantId,
        'token_family' => bin2hex(random_bytes(8)), // For refresh token rotation
    ];
    
    $accessToken = jwtGenerate($accessPayload, 'access');
    $refreshToken = jwtGenerate($refreshPayload, 'refresh');
    
    return [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'expires_in' => JWT_ACCESS_TOKEN_TTL,
        'token_type' => 'Bearer',
    ];
}

// ─── JWT Token Verification ─────────────────────────────────────────────────

/**
 * Verify and decode a JWT token
 * 
 * @param string $token The JWT token
 * @param string|null $expectedType 'access' | 'refresh' | 'reset' | null (any)
 * @return array Decoded payload
 * @throws Exception If token is invalid, expired, or blacklisted
 */
function jwtVerify(string $token, ?string $expectedType = null): array {
    // Load and clean blacklist
    _jwtLoadBlacklist();
    _jwtCleanExpiredBlacklist();
    
    // Check blacklist
    $tokenHash = hash('sha256', $token);
    if (isset($GLOBALS['jwt_blacklist'][$tokenHash])) {
        throw new Exception('TOKEN_REVOKED: Token has been revoked');
    }
    
    // Split token
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('INVALID_TOKEN: Token format is invalid');
    }
    
    [$headerB64, $bodyB64, $signatureB64] = $parts;
    
    // Decode header
    $header = json_decode(_jwtBase64UrlDecode($headerB64), true);
    if (!$header || !isset($header['alg'])) {
        throw new Exception('INVALID_HEADER: Cannot decode token header');
    }
    
    // Verify signature
    $algo = $header['alg'];
    $expectedSignature = _jwtBase64UrlEncode(_jwtSign("{$headerB64}.{$bodyB64}", JWT_SECRET, $algo));
    
    if (!hash_equals($expectedSignature, $signatureB64)) {
        throw new Exception('INVALID_SIGNATURE: Token signature verification failed');
    }
    
    // Decode payload
    $payload = json_decode(_jwtBase64UrlDecode($bodyB64), true);
    if (!$payload) {
        throw new Exception('INVALID_PAYLOAD: Cannot decode token payload');
    }
    
    // Check expiration
    $now = time();
    if (isset($payload['exp']) && $payload['exp'] < $now) {
        throw new Exception('TOKEN_EXPIRED: Token has expired');
    }
    
    // Check not before
    if (isset($payload['nbf']) && $payload['nbf'] > $now) {
        throw new Exception('TOKEN_NOT_YET_VALID: Token not yet valid');
    }
    
    // Check issuer
    if (isset($payload['iss']) && $payload['iss'] !== JWT_ISSUER) {
        throw new Exception('INVALID_ISSUER: Token issuer mismatch');
    }
    
    // Check token type
    if ($expectedType !== null) {
        if (!isset($payload['type']) || $payload['type'] !== $expectedType) {
            throw new Exception('INVALID_TOKEN_TYPE: Expected ' . $expectedType);
        }
    }
    
    return $payload;
}

/**
 * Quick verify - returns payload or null (no exceptions)
 * 
 * @param string $token
 * @return array|null
 */
function jwtVerifySafe(string $token): ?array {
    try {
        return jwtVerify($token);
    } catch (Exception $e) {
        return null;
    }
}

// ─── Token Blacklist (Logout) ───────────────────────────────────────────────

/**
 * Revoke a token (add to blacklist)
 * 
 * @param string $token
 * @return bool
 */
function jwtRevoke(string $token): bool {
    try {
        $payload = jwtVerify($token);
        $expiry = $payload['exp'] ?? (time() + JWT_ACCESS_TOKEN_TTL);
        
        _jwtLoadBlacklist();
        $GLOBALS['jwt_blacklist'][hash('sha256', $token)] = [
            'jti' => $payload['jti'] ?? null,
            'expires' => $expiry,
            'revoked_at' => time(),
        ];
        _jwtSaveBlacklist();
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Revoke all tokens for a user (by revoking their family)
 * Use case: password change, account lock, logout all devices
 * 
 * @param int $userId
 * @return bool
 */
function jwtRevokeAllForUser(int $userId): bool {
    // In production with Redis: add user ID to a "revoked_users" set with timestamp
    // Here we use a simple marker file
    $file = __DIR__ . '/../cache/jwt_revoked_users.json';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $revoked = [];
    if (file_exists($file)) {
        $revoked = json_decode(file_get_contents($file), true) ?: [];
    }
    
    $revoked[(string) $userId] = time();
    file_put_contents($file, json_encode($revoked));
    
    return true;
}

/**
 * Check if user's tokens are all revoked
 * 
 * @param int $userId
 * @param int $tokenIssuedAt
 * @return bool True if tokens for this user are revoked
 */
function jwtIsUserRevoked(int $userId, int $tokenIssuedAt): bool {
    $file = __DIR__ . '/../cache/jwt_revoked_users.json';
    if (!file_exists($file)) {
        return false;
    }
    
    $revoked = json_decode(file_get_contents($file), true) ?: [];
    $revokedAt = $revoked[(string) $userId] ?? 0;
    
    return $revokedAt > 0 && $tokenIssuedAt < $revokedAt;
}

// ─── API Authentication Helper ──────────────────────────────────────────────

/**
 * Authenticate API request using Bearer token
 * Returns user info array or sends 401 response and exits
 * 
 * @return array User payload from JWT
 */
function apiAuth(): array {
    // Get Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    
    if (empty($authHeader)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Authorization header missing']);
        exit;
    }
    
    // Extract Bearer token
    if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'UNAUTHORIZED', 'message' => 'Invalid authorization format. Use: Bearer <token>']);
        exit;
    }
    
    $token = $matches[1];
    
    try {
        $payload = jwtVerify($token, 'access');
        
        // Check if user was revoked after token issuance
        if (isset($payload['sub']) && jwtIsUserRevoked($payload['sub'], $payload['iat'] ?? 0)) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'TOKEN_REVOKED', 'message' => 'Token has been revoked - please login again']);
            exit;
        }
        
        return $payload;
    } catch (Exception $e) {
        $errorCode = 'UNAUTHORIZED';
        $message = 'Invalid or expired token';
        
        if (strpos($e->getMessage(), 'TOKEN_EXPIRED') !== false) {
            $errorCode = 'TOKEN_EXPIRED';
            $message = 'Access token has expired - please refresh';
        } elseif (strpos($e->getMessage(), 'TOKEN_REVOKED') !== false) {
            $errorCode = 'TOKEN_REVOKED';
            $message = 'Token has been revoked';
        }
        
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $errorCode, 'message' => $message]);
        exit;
    }
}

/**
 * Check if user has required role(s)
 * 
 * @param array $userPayload From jwtVerify/apiAuth
 * @param array|string $requiredRoles
 * @return bool
 */
function jwtHasRole(array $userPayload, array|string $requiredRoles): bool {
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    $userRole = $userPayload['role'] ?? 'user';
    return in_array($userRole, $requiredRoles);
}

/**
 * Require specific role - sends 403 if not matching
 * 
 * @param array|string $requiredRoles
 */
function jwtRequireRole(array|string $requiredRoles): void {
    $user = apiAuth();
    if (!jwtHasRole($user, $requiredRoles)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false, 
            'error' => 'FORBIDDEN', 
            'message' => 'Insufficient permissions for this action'
        ]);
        exit;
    }
}

// ─── Refresh Token Flow ─────────────────────────────────────────────────────

/**
 * Refresh access token using refresh token
 * 
 * @param string $refreshToken
 * @return array New token pair
 * @throws Exception
 */
function jwtRefresh(string $refreshToken): array {
    try {
        $payload = jwtVerify($refreshToken, 'refresh');
        
        // Get user from database
        $tenantId = $payload['tenant_id'] ?? null;
        $userId = $payload['sub'] ?? null;
        
        if (!$userId) {
            throw new Exception('INVALID_REFRESH_TOKEN: Missing user ID');
        }
        
        // Connect to tenant DB
        $db = $tenantId ? getTenantDB(['id' => $tenantId, 'database_name' => TENANT_DB_PREFIX . $tenantId]) : getDB();
        
        $stmt = $db->prepare("SELECT id, username, full_name, role, is_active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['is_active']) {
            throw new Exception('USER_NOT_FOUND: User not found or inactive');
        }
        
        // Revoke old refresh token (rotation)
        jwtRevoke($refreshToken);
        
        // Generate new pair
        return jwtGeneratePair($user);
        
    } catch (Exception $e) {
        throw new Exception('REFRESH_FAILED: ' . $e->getMessage());
    }
}

// ─── Password Reset Token ───────────────────────────────────────────────────

/**
 * Generate password reset token
 * 
 * @param int $userId
 * @param string $email
 * @return string Reset token
 */
function jwtGeneratePasswordReset(int $userId, string $email): string {
    return jwtGenerate([
        'sub' => $userId,
        'email' => $email,
        'purpose' => 'password_reset',
    ], 'reset');
}

/**
 * Verify password reset token
 * 
 * @param string $token
 * @return array User info from token
 * @throws Exception
 */
function jwtVerifyPasswordReset(string $token): array {
    $payload = jwtVerify($token, 'reset');
    
    if (($payload['purpose'] ?? '') !== 'password_reset') {
        throw new Exception('INVALID_PURPOSE: Token not intended for password reset');
    }
    
    return [
        'user_id' => $payload['sub'],
        'email' => $payload['email'],
    ];
}
