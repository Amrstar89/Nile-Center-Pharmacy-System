<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - API BOOTSTRAP                     ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

set_error_handler(function ($severity, $message, $file, $line) {
    http_response_code(500); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'INTERNAL_ERROR', 'message' => $message, 'file' => basename($file), 'line' => $line]); exit;
});

set_exception_handler(function (Throwable $e) {
    http_response_code(500); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]); exit;
});

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Tenant-ID, Accept-Language");
header("Access-Control-Allow-Credentials: true");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../core/tenant-router.php';
require_once __DIR__ . '/../core/jwt-auth.php';

function apiSuccess(array $data = [], string $message = '', int $code = 200): void { http_response_code($code); echo json_encode(array_merge(['success' => true, 'message' => $message], $data)); exit; }
function apiError(string $error, string $message = '', int $code = 400, array $extra = []): void { http_response_code($code); echo json_encode(array_merge(['success' => false, 'error' => $error, 'message' => $message], $extra)); exit; }
function apiInput(): array { $input = file_get_contents('php://input'); if (empty($input)) return []; $data = json_decode($input, true); return is_array($data) ? $data : []; }
function apiParams(): array { return array_merge($_GET, $_POST, apiInput()); }
function apiRequire(array $params, array $required): void { $missing = []; foreach ($required as $f) { if (empty($params[$f]) && ($params[$f] ?? null) !== '0' && ($params[$f] ?? null) !== 0) $missing[] = $f; } if ($missing) apiError('MISSING_FIELDS', 'Required: ' . implode(', ', $missing), 400, ['missing' => $missing]); }
function apiPagination(int $page = 1, int $perPage = 25): array { $p = max(1, (int) ($_GET['page'] ?? $page)); $pp = min(100, max(1, (int) ($_GET['per_page'] ?? $perPage))); return [$p, $pp, ($p - 1) * $pp]; }
function apiPaginationMeta(int $page, int $perPage, int $total): array { $tp = (int) ceil($total / $perPage); return ['current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $tp, 'has_next' => $page < $tp, 'has_prev' => $page > 1]; }
function apiSanitize(?string $v): ?string { return $v === null ? null : htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8'); }
