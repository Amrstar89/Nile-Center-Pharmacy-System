<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/products/search - MATCHES REAL DATABASE SCHEMA           ║
 * ║  products: sell_price, cost_price, product_type_id, category, manufacturer ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

$tenant = requireTenant();
$params = apiParams();

$search = apiSanitize($params['q'] ?? '');
$barcode = apiSanitize($params['barcode'] ?? '');
$storeId = !empty($params['store_id']) ? (int) $params['store_id'] : null;
$categoryId = !empty($params['category_id']) ? (int) $params['category_id'] : null;
$companyId = !empty($params['company_id']) ? (int) $params['company_id'] : null;
$typeId = !empty($params['type_id']) ? (int) $params['type_id'] : null;
$isActive = isset($params['is_active']) ? (int) $params['is_active'] : 1;
$isDrug = isset($params['is_drug']) ? (int) $params['is_drug'] : null;
[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);

    $where = ["p.is_active = ?"];
    $bindParams = [$isActive];

    if ($search) {
        $where[] = "(p.product_name LIKE ? OR p.product_code LIKE ? OR p.scientific_name LIKE ?)";
        $like = "%{$search}%";
        array_push($bindParams, $like, $like, $like);
    }
    if ($barcode) {
        $where[] = "(p.barcode = ? OR EXISTS(SELECT 1 FROM product_barcodes pb WHERE pb.product_id = p.id AND pb.barcode = ?))";
        array_push($bindParams, $barcode, $barcode);
    }
    if ($categoryId) { $where[] = "p.category_id = ?"; $bindParams[] = $categoryId; }
    if ($companyId) { $where[] = "p.company_id = ?"; $bindParams[] = $companyId; }
    if ($typeId) { $where[] = "p.product_type_id = ?"; $bindParams[] = $typeId; }
    if ($isDrug !== null) { $where[] = "p.is_drug = ?"; $bindParams[] = $isDrug; }

    $whereSql = implode(' AND ', $where);

    // Stock aggregation
    if ($storeId) {
        $stockJoin = "LEFT JOIN inventory_items ii ON p.id = ii.product_id AND ii.store_id = " . (int) $storeId;
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
        p.id, p.product_code, p.barcode, p.product_name, p.product_name_en,
        p.scientific_name, p.category_id, p.category, p.company_id, p.manufacturer,
        p.product_type_id, p.cost_price, p.sell_price, p.unit2_sell_price, p.unit3_sell_price,
        p.is_drug, p.has_expire, p.is_service, p.reorder_point, p.min_stock, p.max_stock,
        p.is_active, p.print_barcode, p.is_imported, p.notes,
        pt.type_name as product_type_name,
        COALESCE(SUM(ii.quantity), 0) as stock_quantity
    FROM products p
    LEFT JOIN product_types pt ON p.product_type_id = pt.id
    {$stockJoin}
    WHERE {$whereSql}
    GROUP BY p.id
    ORDER BY p.product_name ASC
    LIMIT ? OFFSET ?";

    $bindParams[] = $perPage;
    $bindParams[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($bindParams);
    $products = $stmt->fetchAll();

    $formatted = array_map(function($p) {
        $stock = (float) $p['stock_quantity'];
        $reorder = (float) $p['reorder_point'];
        $stockStatus = 'normal';
        if ($stock <= 0) $stockStatus = 'out_of_stock';
        elseif ($reorder > 0 && $stock <= $reorder) $stockStatus = 'low_stock';

        return [
            'id' => (int) $p['id'],
            'code' => $p['product_code'],
            'barcode' => $p['barcode'],
            'name' => $p['product_name'],
            'name_en' => $p['product_name_en'],
            'scientific_name' => $p['scientific_name'],
            'category' => ['id' => (int) $p['category_id'], 'name' => $p['category']],
            'company' => ['id' => (int) $p['company_id'], 'name' => $p['manufacturer']],
            'type' => ['id' => (int) $p['product_type_id'], 'name' => $p['product_type_name']],
            'pricing' => [
                'cost_price' => (float) $p['cost_price'],
                'sell_price' => (float) $p['sell_price'],
                'unit2_price' => (float) $p['unit2_sell_price'],
                'unit3_price' => (float) $p['unit3_sell_price'],
            ],
            'flags' => [
                'is_drug' => (bool) $p['is_drug'],
                'has_expire' => (bool) $p['has_expire'],
                'is_service' => (bool) $p['is_service'],
                'is_imported' => (bool) $p['is_imported'],
            ],
            'stock' => [
                'quantity' => $stock,
                'reorder_point' => $reorder,
                'min_stock' => (float) $p['min_stock'],
                'max_stock' => (float) $p['max_stock'],
                'status' => $stockStatus,
            ],
            'is_active' => (bool) $p['is_active'],
        ];
    }, $products);

    apiSuccess([
        'products' => $formatted,
        'pagination' => apiPaginationMeta($page, $perPage, $total),
    ]);

} catch (Exception $e) {
    error_log("[API Products Search] Error: " . $e->getMessage());
    apiError('SEARCH_ERROR', 'حدث خطأ أثناء البحث عن المنتجات', 500);
}
