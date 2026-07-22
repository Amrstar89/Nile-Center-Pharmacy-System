<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/products/search                                            ║
 * ║                                                                             ║
 *  Headers: Authorization: Bearer <token> OR X-API-Key                          ║
 *  Query: ?q=keyword&branch_id=1&store_id=1&category_id=&page=1&per_page=20    ║
 *  Response: { "products": [...], "pagination": {...} }                         ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

// Resolve tenant (API key or token)
$tenant = requireTenant();

$params = apiParams();
$search = apiSanitize($params['q'] ?? '');
$barcode = apiSanitize($params['barcode'] ?? '');
$branchId = !empty($params['branch_id']) ? (int) $params['branch_id'] : null;
$storeId = !empty($params['store_id']) ? (int) $params['store_id'] : null;
$categoryId = !empty($params['category_id']) ? (int) $params['category_id'] : null;
$companyId = !empty($params['company_id']) ? (int) $params['company_id'] : null;
$isActive = isset($params['is_active']) ? (int) $params['is_active'] : 1;
$hasStock = isset($params['has_stock']) ? (int) $params['has_stock'] : null;
$orderBy = in_array($params['order_by'] ?? '', ['name', 'code', 'price', 'created_at']) ? $params['order_by'] : 'name';
$orderDir = ($params['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);
    
    // Build query
    $where = ["p.is_active = ?"];
    $bindParams = [$isActive];
    
    if ($search) {
        $where[] = "(p.product_name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ? OR p.scientific_name LIKE ?)";
        $like = "%{$search}%";
        array_push($bindParams, $like, $like, $like, $like);
    }
    
    if ($barcode) {
        $where[] = "(p.barcode = ? OR p.barcode2 = ?)";
        array_push($bindParams, $barcode, $barcode);
    }
    
    if ($categoryId) {
        $where[] = "p.category_id = ?";
        $bindParams[] = $categoryId;
    }
    
    if ($companyId) {
        $where[] = "p.company_id = ?";
        $bindParams[] = $companyId;
    }
    
    $whereSql = implode(' AND ', $where);
    
    // Count query
    $countSql = "SELECT COUNT(*) FROM products p WHERE {$whereSql}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($bindParams);
    $total = (int) $stmt->fetchColumn();
    
    // Main query with stock info
    $stockJoin = '';
    $stockSelect = '0 as stock_quantity';
    
    if ($storeId) {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id AND ii.store_id = ?";
        $stockSelect = 'COALESCE(SUM(ii.quantity), 0) as stock_quantity';
        array_unshift($bindParams, $storeId);
    } elseif ($branchId) {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id AND ii.branch_id = ?";
        $stockSelect = 'COALESCE(SUM(ii.quantity), 0) as stock_quantity';
        array_unshift($bindParams, $branchId);
    }
    
    $sql = "SELECT 
        p.id, p.product_code, p.barcode, p.barcode2,
        p.product_name, p.scientific_name,
        p.category_id, pc.category_name,
        p.company_id, pco.company_name,
        p.type_id, pt.type_name,
        p.unit_id, pu.unit_name,
        p.purchase_price, p.sale_price, p.wholesale_price,
        p.avg_purchase_price, p.min_price,
        p.reorder_point, p.reorder_quantity,
        p.expiry_alert_days,
        p.is_active, p.is_suspended,
        {$stockSelect}
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    LEFT JOIN product_companies pco ON p.company_id = pco.id
    LEFT JOIN product_types pt ON p.type_id = pt.id
    LEFT JOIN product_units pu ON p.unit_id = pu.id
    {$stockJoin}
    WHERE {$whereSql}
    GROUP BY p.id
    ";
    
    if ($hasStock !== null) {
        $sql .= $hasStock ? " HAVING stock_quantity > 0" : " HAVING stock_quantity = 0";
    }
    
    $sql .= " ORDER BY p.{$orderBy} {$orderDir} LIMIT ? OFFSET ?";
    $bindParams[] = $perPage;
    $bindParams[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($bindParams);
    $products = $stmt->fetchAll();
    
    // Format response
    $formatted = array_map(function($p) {
        return [
            'id' => (int) $p['id'],
            'code' => $p['product_code'],
            'barcode' => $p['barcode'],
            'barcode2' => $p['barcode2'],
            'name' => $p['product_name'],
            'scientific_name' => $p['scientific_name'],
            'category' => ['id' => (int) $p['category_id'], 'name' => $p['category_name']],
            'company' => ['id' => (int) $p['company_id'], 'name' => $p['company_name']],
            'type' => ['id' => (int) $p['type_id'], 'name' => $p['type_name']],
            'unit' => ['id' => (int) $p['unit_id'], 'name' => $p['unit_name']],
            'pricing' => [
                'purchase_price' => (float) $p['purchase_price'],
                'sale_price' => (float) $p['sale_price'],
                'wholesale_price' => (float) $p['wholesale_price'],
                'avg_purchase_price' => (float) $p['avg_purchase_price'],
                'min_price' => (float) $p['min_price'],
            ],
            'stock' => [
                'quantity' => (float) $p['stock_quantity'],
                'reorder_point' => (float) $p['reorder_point'],
            ],
            'is_active' => (bool) $p['is_active'],
        ];
    }, $products);
    
    apiSuccess([
        'products' => $formatted,
        'pagination' => apiPaginationMeta($page, $perPage, $total),
        'search_meta' => [
            'query' => $search,
            'branch_id' => $branchId,
            'store_id' => $storeId,
            'category_id' => $categoryId,
            'results_count' => count($formatted),
            'total_count' => $total,
        ],
    ]);
    
} catch (Exception $e) {
    error_log("[API Products Search] Error: " . $e->getMessage());
    apiError('SEARCH_ERROR', 'حدث خطأ أثناء البحث عن المنتجات', 500);
}
