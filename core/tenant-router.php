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

// Master database credentials (store in environment variables in production)
define('MASTER_DB_HOST', $_ENV['MASTER_DB_HOST'] ?? 'localhost');
define('MASTER_DB_NAME', $_ENV['MASTER_DB_NAME'] ?? 'nile_center_master');
define('MASTER_DB_USER', $_ENV['MASTER_DB_USER'] ?? 'root');
define('MASTER_DB_PASS', $_ENV['MASTER_DB_PASS'] ?? '');
define('MASTER_DB_CHARSET', 'utf8mb4');

// Platform domain (for subdomain detection)
define('PLATFORM_DOMAIN', $_ENV['PLATFORM_DOMAIN'] ?? 'nilecenter.com');

// Tenant database prefix
define('TENANT_DB_PREFIX', 'nile_tenant_');

// ─── Master DB Connection ───────────────────────────────────────────────────

function getMasterDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . MASTER_DB_HOST . ";dbname=" . MASTER_DB_NAME . ";charset=" . MASTER_DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, MASTER_DB_USER, MASTER_DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("[TenantRouter] Master DB connection failed: " . $e->getMessage());
            throw new Exception("Master database connection failed");
        }
    }
    return $pdo;
}

// ─── Tenant Resolution ──────────────────────────────────────────────────────

/**
 * Tenant resolution result
 */
class TenantResolution {
    public ?array $tenant = null;
    public ?string $error = null;
    public string $resolvedBy = 'none'; // domain, subdomain, subfolder, api_key, session, fallback
    
    public function isResolved(): bool {
        return $this->tenant !== null && $this->error === null;
    }
    
    public function getDatabaseName(): ?string {
        return $this->tenant['database_name'] ?? null;
    }
    
    public function getTenantId(): ?int {
        return $this->tenant['id'] ?? null;
    }
    
    public function isActive(): bool {
        return ($this->tenant['status'] ?? '') === 'active' || ($this->tenant['status'] ?? '') === 'trial';
    }
    
    public function isTrial(): bool {
        return ($this->tenant['status'] ?? '') === 'trial';
    }
    
    public function isExpired(): bool {
        $status = $this->tenant['status'] ?? '';
        if (in_array($status, ['expired', 'suspended', 'cancelled'])) {
            return true;
        }
        // Check trial expiry
        if ($status === 'trial' && !empty($this->tenant['trial_ends_at'])) {
            return strtotime($this->tenant['trial_ends_at']) < time();
        }
        // Check subscription expiry
        if ($status === 'active' && !empty($this->tenant['subscription_ends_at'])) {
            return strtotime($this->tenant['subscription_ends_at']) < time();
        }
        return false;
    }
    
    public function getFeature(string $feature): bool {
        $features = json_decode($this->tenant['features_json'] ?? '{}', true);
        return $features[$feature] ?? false;
    }
    
    public function hasFeature(string $feature): bool {
        return $this->getFeature($feature);
    }
}

/**
 * Resolve tenant from HTTP request context
 * 
 * @return TenantResolution
 */
function resolveTenant(): TenantResolution {
    $result = new TenantResolution();
    $masterDb = getMasterDB();
    
    // 1. Try API Key (for API/mobile requests - highest priority)
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    if ($apiKey) {
        $stmt = $masterDb->prepare("
            SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en,
                   s.status as sub_status, s.ends_at as subscription_ends_at
            FROM tenants t
            JOIN plans p ON t.plan_id = p.id
            LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active')
            WHERE t.api_key = ? AND t.status IN ('active', 'trial')
            LIMIT 1
        ");
        $stmt->execute([$apiKey]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $result->tenant = $tenant;
            $result->resolvedBy = 'api_key';
            return $result;
        }
    }
    
    // 2. Try Tenant ID Header
    $tenantId = $_SERVER['HTTP_X_TENANT_ID'] ?? $_GET['tenant_id'] ?? null;
    if ($tenantId) {
        $stmt = $masterDb->prepare("
            SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en,
                   s.status as sub_status, s.ends_at as subscription_ends_at
            FROM tenants t
            JOIN plans p ON t.plan_id = p.id
            LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active')
            WHERE t.id = ? OR t.tenant_code = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $tenantId]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $result->tenant = $tenant;
            $result->resolvedBy = 'tenant_id';
            return $result;
        }
    }
    
    // 3. Try Custom Domain
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host) {
        $stmt = $masterDb->prepare("
            SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en,
                   s.status as sub_status, s.ends_at as subscription_ends_at
            FROM tenants t
            JOIN plans p ON t.plan_id = p.id
            LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active')
            WHERE t.custom_domain = ? AND t.status IN ('active', 'trial')
            LIMIT 1
        ");
        $stmt->execute([$host]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $result->tenant = $tenant;
            $result->resolvedBy = 'domain';
            return $result;
        }
    }
    
    // 4. Try Subdomain (pharmacy.nilecenter.com)
    if ($host && strpos($host, PLATFORM_DOMAIN) !== false) {
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $slug = $parts[0]; // First part is the slug
            $stmt = $masterDb->prepare("
                SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en,
                       s.status as sub_status, s.ends_at as subscription_ends_at
                FROM tenants t
                JOIN plans p ON t.plan_id = p.id
                LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active')
                WHERE t.slug = ? AND t.status IN ('active', 'trial')
                LIMIT 1
            ");
            $stmt->execute([$slug]);
            $tenant = $stmt->fetch();
            if ($tenant) {
                $result->tenant = $tenant;
                $result->resolvedBy = 'subdomain';
                return $result;
            }
        }
    }
    
    // 5. Try Subfolder (/tenant/pharmacy/)
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('/\/tenant\/([a-zA-Z0-9_-]+)/', $uri, $matches)) {
        $slug = $matches[1];
        $stmt = $masterDb->prepare("
            SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en,
                   s.status as sub_status, s.ends_at as subscription_ends_at
            FROM tenants t
            JOIN plans p ON t.plan_id = p.id
            LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active')
            WHERE (t.slug = ? OR t.tenant_code = ?) AND t.status IN ('active', 'trial')
            LIMIT 1
        ");
        $stmt->execute([$slug, $slug]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $result->tenant = $tenant;
            $result->resolvedBy = 'subfolder';
            return $result;
        }
    }
    
    // 6. Try Session (for web users already logged in)
    if (isset($_SESSION['tenant_id'])) {
        $stmt = $masterDb->prepare("
            SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en,
                   s.status as sub_status, s.ends_at as subscription_ends_at
            FROM tenants t
            JOIN plans p ON t.plan_id = p.id
            LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active')
            WHERE t.id = ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['tenant_id']]);
        $tenant = $stmt->fetch();
        if ($tenant) {
            $result->tenant = $tenant;
            $result->resolvedBy = 'session';
            return $result;
        }
    }
    
    // 7. Single-tenant fallback (backward compatibility)
    // If no tenant resolved, check if we're running in single-tenant mode
    $stmt = $masterDb->query("
        SELECT t.*, p.features_json, p.plan_name_ar, p.plan_name_en,
               s.status as sub_status, s.ends_at as subscription_ends_at
        FROM tenants t
        JOIN plans p ON t.plan_id = p.id
        LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active')
        WHERE t.status IN ('active', 'trial')
        ORDER BY t.id ASC
        LIMIT 1
    ");
    $tenant = $stmt->fetch();
    if ($tenant) {
        $result->tenant = $tenant;
        $result->resolvedBy = 'fallback_single';
        return $result;
    }
    
    $result->error = 'No tenant found for this request';
    return $result;
}

/**
 * Get tenant database connection
 * 
 * @param array|null $tenant Tenant data array (or null to auto-resolve)
 * @return PDO
 */
function getTenantDB(?array $tenant = null): PDO {
    static $connections = [];
    
    if ($tenant === null) {
        $resolution = resolveTenant();
        if (!$resolution->isResolved()) {
            throw new Exception("Cannot connect: " . $resolution->error);
        }
        $tenant = $resolution->tenant;
    }
    
    $dbName = $tenant['database_name'] ?? (TENANT_DB_PREFIX . ($tenant['id'] ?? '0'));
    
    if (!isset($connections[$dbName])) {
        try {
            $host = $tenant['database_host'] ?? MASTER_DB_HOST;
            $dsn = "mysql:host={$host};dbname={$dbName};charset=" . MASTER_DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $connections[$dbName] = new PDO($dsn, MASTER_DB_USER, MASTER_DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("[TenantRouter] Tenant DB connection failed for {$dbName}: " . $e->getMessage());
            throw new Exception("Tenant database connection failed");
        }
    }
    
    return $connections[$dbName];
}

/**
 * Create a new tenant database (for provisioning)
 * 
 * @param int $tenantId The tenant ID
 * @return bool Success
 */
function createTenantDatabase(int $tenantId): bool {
    try {
        $masterDb = getMasterDB();
        $dbName = TENANT_DB_PREFIX . $tenantId;
        
        // Create database
        $masterDb->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Update tenant record with database name
        $stmt = $masterDb->prepare("UPDATE tenants SET database_name = ? WHERE id = ?");
        $stmt->execute([$dbName, $tenantId]);
        
        // Run tenant template schema
        $templateFile = __DIR__ . '/../database/tenant-template.sql';
        if (file_exists($templateFile)) {
            $sql = file_get_contents($templateFile);
            // Replace database references
            $sql = str_replace('`nile_center`', "`{$dbName}`", $sql);
            
            $tenantDb = getTenantDB(['id' => $tenantId, 'database_name' => $dbName, 'database_host' => MASTER_DB_HOST]);
            $tenantDb->exec($sql);
        }
        
        error_log("[TenantRouter] Created tenant database: {$dbName}");
        return true;
    } catch (Exception $e) {
        error_log("[TenantRouter] Failed to create tenant DB: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a tenant database exists
 * 
 * @param string $dbName
 * @return bool
 */
function tenantDatabaseExists(string $dbName): bool {
    try {
        $masterDb = getMasterDB();
        $stmt = $masterDb->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$dbName]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Middleware: Require active tenant
 * Use this at the top of API endpoints to ensure tenant is resolved and active
 * 
 * @return array Tenant data
 */
function requireTenant(): array {
    $resolution = resolveTenant();
    
    if (!$resolution->isResolved()) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'TENANT_NOT_FOUND',
            'message' => 'لم يتم العثور على المستأجر',
            'message_en' => 'Tenant not found for this request'
        ]);
        exit;
    }
    
    if ($resolution->isExpired()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'TENANT_EXPIRED',
            'message' => 'الاشتراك منتهي - يرجى تجديد الاشتراك',
            'message_en' => 'Subscription expired - please renew'
        ]);
        exit;
    }
    
    // Store in session for web requests
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['tenant_id'] = $resolution->getTenantId();
    
    return $resolution->tenant;
}

/**
 * Get current tenant info (for use in controllers after requireTenant)
 * 
 * @return array|null
 */
function getCurrentTenant(): ?array {
    $resolution = resolveTenant();
    return $resolution->isResolved() ? $resolution->tenant : null;
}

/**
 * Helper: Check if request is API request
 * 
 * @return bool
 */
function isApiRequest(): bool {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return 
        strpos($contentType, 'application/json') !== false ||
        strpos($accept, 'application/json') !== false ||
        isset($_SERVER['HTTP_X_API_KEY']) ||
        isset($_SERVER['HTTP_X_TENANT_ID']) ||
        strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}
