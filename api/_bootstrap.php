<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - API BOOTSTRAP                     ║
 * ║                                                                              ║
 * ║  Common initialization for all API endpoints                                 ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

// Error handling - always return JSON
set_error_handler(function ($severity, $message, $file, $line) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'INTERNAL_ERROR',
        'message' => 'An internal error occurred',
        'debug' => [
            'message' => $message,
            'file' => basename($file),
            'line' => $line
        ]
    ]);
    exit;
});

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'INTERNAL_ERROR',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Tenant-ID, Accept-Language");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Always return JSON
header('Content-Type: application/json; charset=utf-8');

// Load core
require_once __DIR__ . '/../core/tenant-router.php';
require_once __DIR__ . '/../core/jwt-auth.php';

// ─── Helper Functions ───────────────────────────────────────────────────────

/**
 * Send success response
 */
function apiSuccess(array $data = [], string $message = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge(
        ['success' => true, 'message' => $message],
        $data
    ));
    exit;
}

/**
 * Send error response
 */
function apiError(string $error, string $message = '', int $code = 400, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => false,
        'error' => $error,
        'message' => $message
    ], $extra));
    exit;
}

/**
 * Get JSON request body
 */
function apiInput(): array {
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return [];
    }
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

/**
 * Get request parameters (merged GET + POST + JSON body)
 */
function apiParams(): array {
    $json = apiInput();
    return array_merge($_GET, $_POST, $json);
}

/**
 * Validate required fields
 */
function apiRequire(array $params, array $required): void {
    $missing = [];
    foreach ($required as $field) {
        if (!isset($params[$field]) || $params[$field] === '' || $params[$field] === null) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        apiError('MISSING_FIELDS', 'Required fields: ' . implode(', ', $missing), 400, [
            'missing_fields' => $missing
        ]);
    }
}

/**
 * Pagination helper
 */
function apiPagination(int $page = 1, int $perPage = 25): array {
    $page = max(1, (int) ($_GET['page'] ?? $page));
    $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? $perPage)));
    $offset = ($page - 1) * $perPage;
    return [$page, $perPage, $offset];
}

/**
 * Build pagination metadata
 */
function apiPaginationMeta(int $page, int $perPage, int $total): array {
    $totalPages = (int) ceil($total / $perPage);
    return [
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1,
    ];
}

/**
 * Sanitize string input
 */
function apiSanitize(?string $value): ?string {
    if ($value === null) return null;
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if tenant has required feature
 */
function apiRequireFeature(string $feature): void {
    $tenant = getCurrentTenant();
    if (!$tenant) {
        apiError('TENANT_NOT_FOUND', 'Tenant not resolved', 404);
    }
    
    $resolution = new TenantResolution();
    $resolution->tenant = $tenant;
    
    if (!$resolution->hasFeature($feature)) {
        apiError('FEATURE_NOT_AVAILABLE', 
            'هذه الميزة غير متوفرة في خطتك الحالية - يرجى الترقية',
            403,
            ['required_feature' => $feature]
        );
    }
}
