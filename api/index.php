<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - API GATEWAY                       ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../core/tenant-router.php';
require_once __DIR__ . '/../core/jwt-auth.php';

header('Content-Type: application/json; charset=utf-8');

$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

echo json_encode([
    'success' => true,
    'message' => 'Nile Center ERP API v1',
    'status' => 'operational',
    'timestamp' => date('c'),
    'documentation' => 'API Documentation available at API_DOCUMENTATION.md',
    'endpoints' => [
        'Authentication' => [
            ['method' => 'POST', 'path' => '/api/tenant/auth/login', 'auth' => 'No', 'description' => 'Login with username/password'],
            ['method' => 'POST', 'path' => '/api/tenant/auth/refresh', 'auth' => 'No', 'description' => 'Refresh access token'],
            ['method' => 'GET', 'path' => '/api/tenant/auth/profile', 'auth' => 'Bearer', 'description' => 'Get user profile'],
            ['method' => 'POST', 'path' => '/api/tenant/auth/logout', 'auth' => 'Bearer', 'description' => 'Logout'],
        ],
        'Products' => [
            ['method' => 'GET', 'path' => '/api/tenant/products/search?q=keyword&store_id=1', 'auth' => 'Bearer', 'description' => 'Search products'],
        ],
        'Customers' => [
            ['method' => 'GET', 'path' => '/api/tenant/customers/list?q=search&class_id=', 'auth' => 'Bearer', 'description' => 'List customers'],
        ],
        'Sales' => [
            ['method' => 'POST', 'path' => '/api/tenant/sales/create', 'auth' => 'Bearer', 'description' => 'Create sale invoice'],
            ['method' => 'GET', 'path' => '/api/tenant/sales/list', 'auth' => 'Bearer', 'description' => 'List invoices'],
        ],
        'Inventory' => [
            ['method' => 'GET', 'path' => '/api/tenant/inventory/stock?store_id=1&low_stock=1', 'auth' => 'Bearer', 'description' => 'Check stock levels'],
        ],
        'Reports' => [
            ['method' => 'GET', 'path' => '/api/tenant/reports/dashboard', 'auth' => 'Bearer', 'description' => 'Dashboard KPIs'],
        ],
    ],
    'error_codes' => [
        'UNAUTHORIZED' => 'Invalid or missing token',
        'TOKEN_EXPIRED' => 'Token expired - use refresh',
        'TENANT_NOT_FOUND' => 'No tenant resolved',
        'TENANT_EXPIRED' => 'Subscription expired',
        'MISSING_FIELDS' => 'Required fields missing',
        'INVALID_CREDENTIALS' => 'Wrong username/password',
        'INSUFFICIENT_STOCK' => 'Not enough inventory',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
