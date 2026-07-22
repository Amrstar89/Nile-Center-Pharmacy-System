<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - API GATEWAY                       ║
 * ║                                                                              ║
 * ║  RESTful API for Mobile Apps & Integrations                                  ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/_bootstrap.php';

// ─── API Documentation ──────────────────────────────────────────────────────

$apiVersion = 'v1';
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

echo json_encode([
    'success' => true,
    'message' => 'Nile Center ERP API',
    'version' => $apiVersion,
    'status' => 'operational',
    'timestamp' => date('c'),
    'documentation' => [
        'overview' => 'RESTful API for pharmacy management - supports mobile apps, POS devices, and third-party integrations',
        'authentication' => [
            'type' => 'JWT Bearer Token',
            'header' => 'Authorization: Bearer <access_token>',
            'alt_header' => 'X-API-Key: <api_key>',
            'token_lifetime' => JWT_ACCESS_TOKEN_TTL . ' seconds',
        ],
        'tenant_resolution' => [
            'methods' => [
                'custom_domain' => 'pharmacy.com → auto-resolves tenant',
                'subdomain' => 'pharmacy.nilecenter.com → resolves via slug',
                'api_key' => 'X-API-Key header → resolves tenant',
                'tenant_id' => 'X-Tenant-ID header → resolves tenant',
            ],
        ],
    ],
    'endpoints' => [
        'Authentication' => [
            ['method' => 'POST', 'path' => '/api/tenant/auth/login', 'auth' => 'No', 'description' => 'Login with username/password, returns JWT tokens'],
            ['method' => 'POST', 'path' => '/api/tenant/auth/refresh', 'auth' => 'No', 'description' => 'Refresh access token using refresh token'],
            ['method' => 'GET', 'path' => '/api/tenant/auth/profile', 'auth' => 'Bearer', 'description' => 'Get current user profile'],
            ['method' => 'POST', 'path' => '/api/tenant/auth/logout', 'auth' => 'Bearer', 'description' => 'Logout and revoke token'],
        ],
        'Products' => [
            ['method' => 'GET', 'path' => '/api/tenant/products/search?q=keyword&store_id=1', 'auth' => 'Bearer/API-Key', 'description' => 'Search products with filters'],
        ],
        'Customers' => [
            ['method' => 'GET', 'path' => '/api/tenant/customers/list?q=search&class_id=', 'auth' => 'Bearer', 'description' => 'List customers with search and filters'],
        ],
        'Sales' => [
            ['method' => 'POST', 'path' => '/api/tenant/sales/create', 'auth' => 'Bearer', 'description' => 'Create sales invoice'],
            ['method' => 'GET', 'path' => '/api/tenant/sales/list?from_date=&to_date=', 'auth' => 'Bearer', 'description' => 'List sales invoices'],
        ],
        'Inventory' => [
            ['method' => 'GET', 'path' => '/api/tenant/inventory/stock?store_id=1&low_stock=1', 'auth' => 'Bearer', 'description' => 'Check stock levels and alerts'],
        ],
        'Reports' => [
            ['method' => 'GET', 'path' => '/api/tenant/reports/dashboard?from_date=&to_date=', 'auth' => 'Bearer', 'description' => 'Dashboard KPIs, trends, top products/customers'],
        ],
    ],
    'response_format' => [
        'success' => [
            'success' => true,
            'message' => 'Operation description',
            'data' => '{...}',
        ],
        'error' => [
            'success' => false,
            'error' => 'ERROR_CODE',
            'message' => 'Human-readable error description',
        ],
    ],
    'error_codes' => [
        'UNAUTHORIZED' => 'Authentication required or invalid credentials',
        'TOKEN_EXPIRED' => 'JWT token has expired - use refresh token',
        'TOKEN_REVOKED' => 'Token has been revoked - login again',
        'TENANT_NOT_FOUND' => 'No tenant found for the request',
        'TENANT_EXPIRED' => 'Tenant subscription has expired',
        'FEATURE_NOT_AVAILABLE' => 'Feature not included in current plan',
        'MISSING_FIELDS' => 'Required fields missing from request',
        'INVALID_CREDENTIALS' => 'Wrong username or password',
        'INSUFFICIENT_STOCK' => 'Not enough stock for the requested quantity',
        'CUSTOMER_NOT_FOUND' => 'Customer does not exist or is inactive',
        'PRODUCT_NOT_FOUND' => 'Product does not exist or is inactive',
        'BRANCH_NOT_FOUND' => 'Branch does not exist',
        'STORE_NOT_FOUND' => 'Store does not exist',
        'INVALID_ITEMS' => 'Invoice items array is empty or invalid',
    ],
    'contact' => [
        'support' => 'support@nilecenter.com',
        'documentation_url' => $baseUrl . '/api/docs',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
