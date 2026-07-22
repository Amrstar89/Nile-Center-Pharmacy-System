<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - TENANT ROUTER                       ║
 * ║                                                                              ║
 * ║  Resolves the current tenant from:                                           ║
 * ║  1. Custom domain (e.g., pharmacy.com → tenant)                              ║
 * ║  2. Subdomain (e.g., pharmacy.nilecenter.com → tenant)                       ║
 * ║  3. Subfolder (e.g., nilecenter.com/tenant/pharmacy → tenant)               ║
 * ║  4. Header/API Key (for API requests: X-Tenant-ID or X-API-Key)              ║
 * ║  5. Session (for web requests: $_SESSION['tenant_id'])                       ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

// ─── Configuration ──────────────────────────────────────────────────────────

define('MASTER_DB_HOST', $_ENV['MASTER_DB_HOST'] ?? 'localhost');
define('MASTER_DB_NAME', $_ENV['MASTER_DB_NAME'] ?? 'nile_center_master');
define('MASTER_DB_USER', $_ENV['MASTER_DB_USER'] ?? 'root');
define('MASTER_DB_PASS', $_ENV['MASTER_DB_PASS'] ?? '');
define('MASTER_DB_CHARSET', 'utf8mb4');
define('PLATFORM_DOMAIN', $_ENV['PLATFORM_DOMAIN'] ?? 'nilecenter.com');
define('TENANT_DB_PREFIX', 'nile_tenant_');

// ─── Master DB Connection ───────────────────────────────────────────────────

function getMasterDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . MASTER_DB_HOST . ";dbname=" . MASTER_DB_NAME . ";charset=" . MASTER_DB_CHARSET;
            $pdo = new PDO($dsn, MASTER_DB_USER, MASTER_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log("[TenantRouter] Master DB connection failed: " . $e->getMessage());
            throw new Exception("Master database connection failed");
        }
    }
    return $pdo;
}

// ─── Tenant Resolution ──────────────────────────────────────────────────────

class TenantResolution {
    public ?array $tenant = null;
    public ?string $error = null;
    public string $resolvedBy = 'none';
    
    public function isResolved(): bool { return $this->tenant !== null && $this->error === null; }
    public function getDatabaseName(): ?string { return $this->tenant['database_name'] ?? null; }
    public function getTenantId(): ?int { return $this->tenant['id'] ?? null; }
    public function isActive(): bool { return in_array($this->tenant['status'] ?? '', ['active', 'trial']); }
    public function isTrial(): bool { return ($this->tenant['status'] ?? '') === 'trial'; }
    public function isExpired(): bool {
        $s = $this->tenant['status'] ?? '';
        if (in_array($s, ['expired', 'suspended', 'cancelled'])) return true;
        if ($s === 'trial' && !empty($this->tenant['trial_ends_at'])) return strtotime($this->tenant['trial_ends_at']) < time();
        if ($s === 'active' && !empty($this->tenant['subscription_ends_at'])) return strtotime($this->tenant['subscription_ends_at']) < time();
        return false;
    }
    public function hasFeature(string $feature): bool {
        return json_decode($this->tenant['features_json'] ?? '{}', true)[$feature] ?? false;
    }
}

function resolveTenant(): TenantResolution {
    $result = new TenantResolution();
    try { $masterDb = getMasterDB(); } catch (Exception $e) { $result->error = 'Master DB unavailable'; return $result; }
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // 1. API Key
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    if ($apiKey) {
        $stmt = $masterDb->prepare("SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en, s.status as sub_status, s.ends_at as subscription_ends_at FROM tenants t JOIN plans p ON t.plan_id = p.id LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active') WHERE t.api_key = ? AND t.status IN ('active', 'trial') LIMIT 1");
        $stmt->execute([$apiKey]);
        if ($t = $stmt->fetch()) { $result->tenant = $t; $result->resolvedBy = 'api_key'; return $result; }
    }
    
    // 2. Tenant ID Header
    $tenantId = $_SERVER['HTTP_X_TENANT_ID'] ?? $_GET['tenant_id'] ?? null;
    if ($tenantId) {
        $stmt = $masterDb->prepare("SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en, s.status as sub_status, s.ends_at as subscription_ends_at FROM tenants t JOIN plans p ON t.plan_id = p.id LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active') WHERE t.id = ? OR t.tenant_code = ? LIMIT 1");
        $stmt->execute([$tenantId, $tenantId]);
        if ($t = $stmt->fetch()) { $result->tenant = $t; $result->resolvedBy = 'tenant_id'; return $result; }
    }
    
    // 3. Custom Domain
    if ($host) {
        $stmt = $masterDb->prepare("SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en, s.status as sub_status, s.ends_at as subscription_ends_at FROM tenants t JOIN plans p ON t.plan_id = p.id LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active') WHERE t.custom_domain = ? AND t.status IN ('active', 'trial') LIMIT 1");
        $stmt->execute([$host]);
        if ($t = $stmt->fetch()) { $result->tenant = $t; $result->resolvedBy = 'domain'; return $result; }
    }
    
    // 4. Subdomain
    if ($host && strpos($host, PLATFORM_DOMAIN) !== false) {
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $stmt = $masterDb->prepare("SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en, s.status as sub_status, s.ends_at as subscription_ends_at FROM tenants t JOIN plans p ON t.plan_id = p.id LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active') WHERE t.slug = ? AND t.status IN ('active', 'trial') LIMIT 1");
            $stmt->execute([$parts[0]]);
            if ($t = $stmt->fetch()) { $result->tenant = $t; $result->resolvedBy = 'subdomain'; return $result; }
        }
    }
    
    // 5. Subfolder
    if (preg_match('/\/tenant\/([a-zA-Z0-9_-]+)/', $uri, $matches)) {
        $stmt = $masterDb->prepare("SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en, s.status as sub_status, s.ends_at as subscription_ends_at FROM tenants t JOIN plans p ON t.plan_id = p.id LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active') WHERE (t.slug = ? OR t.tenant_code = ?) AND t.status IN ('active', 'trial') LIMIT 1");
        $stmt->execute([$matches[1], $matches[1]]);
        if ($t = $stmt->fetch()) { $result->tenant = $t; $result->resolvedBy = 'subfolder'; return $result; }
    }
    
    // 6. Session
    if (isset($_SESSION['tenant_id'])) {
        $stmt = $masterDb->prepare("SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en, s.status as sub_status, s.ends_at as subscription_ends_at FROM tenants t JOIN plans p ON t.plan_id = p.id LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active') WHERE t.id = ? LIMIT 1");
        $stmt->execute([$_SESSION['tenant_id']]);
        if ($t = $stmt->fetch()) { $result->tenant = $t; $result->resolvedBy = 'session'; return $result; }
    }
    
    // 7. Fallback - first active tenant
    $stmt = $masterDb->query("SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en, s.status as sub_status, s.ends_at as subscription_ends_at FROM tenants t JOIN plans p ON t.plan_id = p.id LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active') WHERE t.status IN ('active', 'trial') ORDER BY t.id ASC LIMIT 1");
    if ($t = $stmt->fetch()) { $result->tenant = $t; $result->resolvedBy = 'fallback_single'; return $result; }
    
    $result->error = 'No tenant found for this request';
    return $result;
}

function getTenantDB(?array $tenant = null): PDO {
    static $connections = [];
    if ($tenant === null) {
        $resolution = resolveTenant();
        if (!$resolution->isResolved()) throw new Exception("Cannot connect: " . $resolution->error);
        $tenant = $resolution->tenant;
    }
    $dbName = $tenant['database_name'] ?? (TENANT_DB_PREFIX . ($tenant['id'] ?? '0'));
    if (!isset($connections[$dbName])) {
        try {
            $dsn = "mysql:host=" . ($tenant['database_host'] ?? MASTER_DB_HOST) . ";dbname={$dbName};charset=" . MASTER_DB_CHARSET;
            $connections[$dbName] = new PDO($dsn, MASTER_DB_USER, MASTER_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("[TenantRouter] Tenant DB connection failed for {$dbName}: " . $e->getMessage());
            throw new Exception("Tenant database connection failed");
        }
    }
    return $connections[$dbName];
}

function requireTenant(): array {
    $resolution = resolveTenant();
    if (!$resolution->isResolved()) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'TENANT_NOT_FOUND', 'message' => 'لم يتم العثور على المستأجر']);
        exit;
    }
    if ($resolution->isExpired()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'TENANT_EXPIRED', 'message' => 'الاشتراك منتهي - يرجى تجديد الاشتراك']);
        exit;
    }
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['tenant_id'] = $resolution->getTenantId();
    return $resolution->tenant;
}

function getCurrentTenant(): ?array { $r = resolveTenant(); return $r->isResolved() ? $r->tenant : null; }
function isApiRequest(): bool { return isset($_SERVER['HTTP_X_API_KEY']) || isset($_SERVER['HTTP_X_TENANT_ID']) || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false; }
