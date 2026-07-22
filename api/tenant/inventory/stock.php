<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/inventory/stock                                            ║
 * ║                                                                             ║
 *  Query: ?store_id=1&product_id=&category_id=&low_stock=0&page=1              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

$userPayload = apiAuth();
$tenant = requireTenant();
$params = apiParams();

$storeId = !empty($params['store_id']) ? (int) $params['store_id'] : null;
$branchId = !empty($params['branch_id']) ? (int) $params['branch_id'] : null;
$productId = !empty($params['product_id']) ? (int) $params['product_id'] : null;
$categoryId = !empty($params['category_id']) ? (int) $params['category_id'] : null;
$lowStock = isset($params['low_stock']) ? (int) $params['low_stock'] : null;
$search = apiSanitize($params['q'] ?? '');
[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);
    
    $where = ["p.is_active = 1"];
    $bindParams = [];
    
    if ($productId) {
        $where[] = "p.id = ?";
        $bindParams[] = $productId;
    }
    if ($categoryId) {
        $where[] = "p.category_id = ?";
        $bindParams[] = $categoryId;
    }
    if ($search) {
        $where[] = "(p.product_name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
        $like = "%{$search}%";
        array_push($bindParams, $like, $like, $like);
    }
    
    $whereSql = implode(' AND ', $where);
    
    // Stock selection based on store/branch
    $stockJoin = "";
    $stockGroup = "";
    $stockSelect = "COALESCE(SUM(ii.quantity), 0) as stock_quantity";
    
    if ($storeId) {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id AND ii.store_id = {$storeId}";
    } elseif ($branchId) {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id AND ii.branch_id = {$branchId}";
    } else {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id";
    }
    
    // Count
    $countSql = "SELECT COUNT(DISTINCT p.id) FROM products p WHERE {$whereSql}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($bindParams);
    $total = (int) $stmt->fetchColumn();
    
    // Main query
    $sql = "SELECT 
        p.id, p.product_code, p.barcode, p.product_name, p.scientific_name,
        p.category_id, pc.category_name,
        p.company_id, pco.company_name,
        p.purchase_price, p.sale_price, p.avg_purchase_price,
        p.reorder_point, p.expiry_alert_days,
        {$stockSelect},
        SUM(CASE WHEN ii.expiry_date <= DATE_ADD(CURDATE(), INTERVAL p.expiry_alert_days DAY) 
                 AND ii.expiry_date > CURDATE() THEN ii.quantity ELSE 0 END) as near_expiry,
        SUM(CASE WHEN ii.expiry_date <= CURDATE() THEN ii.quantity ELSE 0 END) as expired,
        MIN(ii.expiry_date) as nearest_expiry
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    LEFT JOIN product_companies pco ON p.company_id = pco.id
    {$stockJoin}
    WHERE {$whereSql}
    GROUP BY p.id
    ";
    
    if ($lowStock === 1) {
        $sql .= " HAVING stock_quantity <= p.reorder_point AND p.reorder_point > 0";
    } elseif ($lowStock === 0 && $lowStock !== null) {
        $sql .= " HAVING stock_quantity > p.reorder_point OR p.reorder_point = 0";
    }
    
    $sql .= " ORDER BY p.product_name ASC LIMIT ? OFFSET ?";
    $bindParams[] = $perPage;
    $bindParams[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($bindParams);
    $items = $stmt->fetchAll();
    
    $formatted = array_map(function($item) {
        $stock = (float) $item['stock_quantity'];
        $reorder = (float) $item['reorder_point'];
        $stockStatus = 'normal';
        if ($stock <= 0) $stockStatus = 'out_of_stock';
        elseif ($reorder > 0 && $stock <= $reorder) $stockStatus = 'low_stock';
        
        return [
            'id' => (int) $item['id'],
            'code' => $item['product_code'],
            'barcode' => $item['barcode'],
            'name' => $item['product_name'],
            'scientific_name' => $item['scientific_name'],
            'category' => ['id' => (int) $item['category_id'], 'name' => $item['category_name']],
            'company' => ['id' => (int) $item['company_id'], 'name' => $item['company_name']],
            'pricing' => [
                'purchase_price' => (float) $item['purchase_price'],
                'sale_price' => (float) $item['sale_price'],
                'avg_cost' => (float) $item['avg_purchase_price'],
            ],
            'stock' => [
                'quantity' => $stock,
                'reorder_point' => $reorder,
                'status' => $stockStatus,
                'near_expiry' => (float) $item['near_expiry'],
                'expired' => (float) $item['expired'],
                'nearest_expiry_date' => $item['nearest_expiry'],
            ],
        ];
    }, $items);
    
    apiSuccess([
        'items' => $formatted,
        'pagination' => apiPaginationMeta($page, $perPage, $total),
        'filters' => [
            'store_id' => $storeId,
            'branch_id' => $branchId,
            'low_stock_only' => $lowStock === 1,
        ],
    ]);
    
} catch (Exception $e) {
    error_log("[API Inventory Stock] Error: " . $e->getMessage());
    apiError('INVENTORY_ERROR', 'حدث خطأ أثناء جلب بيانات المخزون', 500);
}
