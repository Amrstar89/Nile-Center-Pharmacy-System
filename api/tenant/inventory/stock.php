<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/inventory/stock - MATCHES REAL DATABASE SCHEMA           ║
 * ║  inventory_items: store_id (no branch_id), batch_id, quantity, unit_cost  ║
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
$productId = !empty($params['product_id']) ? (int) $params['product_id'] : null;
$categoryId = !empty($params['category_id']) ? (int) $params['category_id'] : null;
$companyId = !empty($params['company_id']) ? (int) $params['company_id'] : null;
$lowStock = isset($params['low_stock']) ? (int) $params['low_stock'] : null;
$search = apiSanitize($params['q'] ?? '');
[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);

    $where = ["p.is_active = 1"];
    $bindParams = [];

    if ($productId) { $where[] = "p.id = ?"; $bindParams[] = $productId; }
    if ($categoryId) { $where[] = "p.category_id = ?"; $bindParams[] = $categoryId; }
    if ($companyId) { $where[] = "p.company_id = ?"; $bindParams[] = $companyId; }
    if ($search) {
        $where[] = "(p.product_name LIKE ? OR p.product_code LIKE ?)";
        $like = "%{$search}%"; array_push($bindParams, $like, $like);
    }

    $whereSql = implode(' AND ', $where);

    if ($storeId) {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id AND ii.store_id = " . (int) $storeId;
    } else {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id";
    }

    $countSql = "SELECT COUNT(DISTINCT p.id) FROM products p WHERE {$whereSql}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($bindParams);
    $total = (int) $stmt->fetchColumn();

    $sql = "SELECT 
        p.id, p.product_code, p.barcode, p.product_name, p.scientific_name,
        p.category_id, p.category, p.company_id, p.manufacturer,
        p.cost_price, p.sell_price, p.reorder_point, p.min_stock, p.max_stock,
        p.is_drug, p.has_expire,
        COALESCE(SUM(ii.quantity), 0) as stock_quantity
    FROM products p
    {$stockJoin}
    WHERE {$whereSql}
    GROUP BY p.id
    ";

    if ($lowStock === 1) {
        $sql .= " HAVING stock_quantity <= p.reorder_point AND p.reorder_point > 0";
    }

    $sql .= " ORDER BY p.product_name ASC LIMIT ? OFFSET ?";
    $bindParams[] = $perPage;
    $bindParams[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($bindParams);
    $items = $stmt->fetchAll();

    $formatted = array_map(function($it) {
        $stock = (float) $it['stock_quantity'];
        $reorder = (float) $it['reorder_point'];
        $stockStatus = 'normal';
        if ($stock <= 0) $stockStatus = 'out_of_stock';
        elseif ($reorder > 0 && $stock <= $reorder) $stockStatus = 'low_stock';

        return [
            'id' => (int) $it['id'],
            'code' => $it['product_code'],
            'barcode' => $it['barcode'],
            'name' => $it['product_name'],
            'scientific_name' => $it['scientific_name'],
            'category' => ['id' => (int) $it['category_id'], 'name' => $it['category']],
            'company' => ['id' => (int) $it['company_id'], 'name' => $it['manufacturer']],
            'pricing' => [
                'cost_price' => (float) $it['cost_price'],
                'sell_price' => (float) $it['sell_price'],
            ],
            'stock' => [
                'quantity' => $stock,
                'reorder_point' => $reorder,
                'min_stock' => (float) $it['min_stock'],
                'max_stock' => (float) $it['max_stock'],
                'status' => $stockStatus,
            ],
            'is_drug' => (bool) $it['is_drug'],
            'has_expire' => (bool) $it['has_expire'],
        ];
    }, $items);

    apiSuccess([
        'items' => $formatted,
        'pagination' => apiPaginationMeta($page, $perPage, $total),
    ]);

} catch (Exception $e) {
    error_log("[API Inventory Stock] Error: " . $e->getMessage());
    apiError('INVENTORY_ERROR', 'حدث خطأ أثناء جلب بيانات المخزون', 500);
}
