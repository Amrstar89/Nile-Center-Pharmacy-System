<?php
/**
 * ============================================
 * API البحث المتقدم عن الأصناف
 * ============================================
 * Parameters:
 *   - store_id (required): معرف المخزن
 *   - type: نوع البحث (name|barcode|code|scientific|company)
 *   - q: نص البحث
 *   - category: فلتر التصنيف
 *   - company: فلتر الشركة
 *   - price_from: الحد الأدنى للسعر
 *   - price_to: الحد الأقصى للسعر
 *   - show_no_stock: إظهار الأصناف بدون رصيد (1/0)
 *   - page: رقم الصفحة
 *   - limit: عدد النتائج (default: 50, max: 100)
 */
require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    $store_id = intval($_GET['store_id'] ?? 0);
    $type = $_GET['type'] ?? 'name';
    $query = trim($_GET['q'] ?? '');
    $category = intval($_GET['category'] ?? 0);
    $company = intval($_GET['company'] ?? 0);
    $price_from = floatval($_GET['price_from'] ?? 0);
    $price_to = floatval($_GET['price_to'] ?? 0);
    $show_no_stock = intval($_GET['show_no_stock'] ?? 0);
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    
    if ($store_id <= 0) {
        echo json_encode(['error' => 'معرف المخزن مطلوب', 'products' => []]);
        exit;
    }
    
    // Build WHERE conditions
    $where = ["p.is_active = 1"];
    $params = [];
    
    // Search type filter
    if ($query) {
        switch ($type) {
            case 'barcode':
                $where[] = "(p.manual_code = ? OR p.manual_code LIKE ?)";
                $params[] = $query;
                $params[] = "%$query%";
                break;
            case 'code':
                $where[] = "(p.manual_code = ? OR p.product_code = ? OR p.manual_code LIKE ? OR p.product_code LIKE ?)";
                $params[] = $query;
                $params[] = $query;
                $params[] = "%$query%";
                $params[] = "%$query%";
                break;
            case 'scientific':
                $where[] = "p.scientific_name LIKE ?";
                $params[] = "%$query%";
                break;
            case 'company':
                $where[] = "pco.company_name_ar LIKE ?";
                $params[] = "%$query%";
                break;
            case 'name':
            default:
                $where[] = "(p.product_name LIKE ? OR p.product_name_en LIKE ? OR p.manual_code = ? OR p.product_code = ?)";
                $params[] = "%$query%";
                $params[] = "%$query%";
                $params[] = $query;
                $params[] = $query;
                break;
        }
    }
    
    // Category filter
    if ($category > 0) {
        $where[] = "p.category_id = ?";
        $params[] = $category;
    }
    
    // Company filter
    if ($company > 0) {
        $where[] = "p.company_id = ?";
        $params[] = $company;
    }
    
    // Price filter
    if ($price_from > 0) {
        $where[] = "p.sell_price >= ?";
        $params[] = $price_from;
    }
    if ($price_to > 0) {
        $where[] = "p.sell_price <= ?";
        $params[] = $price_to;
    }
    
    // Build WHERE string
    $where_str = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM products p 
        LEFT JOIN product_companies pco ON p.company_id = pco.id 
        LEFT JOIN product_categories pca ON p.category_id = pca.id
        WHERE $where_str";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total = intval($count_stmt->fetch()['total'] ?? 0);
    
    // Get products with stock info
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT 
        p.id,
        p.product_name,
        p.product_name_en,
        p.product_code,
        p.manual_code,
        p.cost_price,
        p.sell_price,
        p.has_expire,
        p.scientific_name,
        p.unit1_id,
        u.unit_name_ar,
        p.company_id,
        pco.company_name_ar as company_name,
        p.category_id,
        pca.category_name_ar as category_name,
        COALESCE(SUM(ii.quantity), 0) as stock_qty,
        MAX(ii.unit_cost) as unit_cost
    FROM products p
    LEFT JOIN product_companies pco ON p.company_id = pco.id
    LEFT JOIN product_categories pca ON p.category_id = pca.id
    LEFT JOIN product_units u ON p.unit1_id = u.id
    LEFT JOIN inventory_items ii ON ii.product_id = p.id AND ii.store_id = ? AND ii.is_active = 1
    WHERE $where_str
    GROUP BY p.id
    " . ($show_no_stock ? "" : "HAVING stock_qty > 0") . "
    ORDER BY 
        CASE WHEN stock_qty > 0 THEN 0 ELSE 1 END,
        p.product_name
    LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $all_params = array_merge([$store_id], $params, [$limit, $offset]);
    $stmt->execute($all_params);
    $products = $stmt->fetchAll();
    
    // Get batches for each product
    foreach ($products as &$p) {
        $p['stock_qty'] = floatval($p['stock_qty']);
        $p['cost_price'] = floatval($p['cost_price']);
        $p['sell_price'] = floatval($p['sell_price']);
        $p['unit_cost'] = floatval($p['unit_cost'] ?? $p['cost_price']);
        
        // Get batches for this product in this store
        if ($p['has_expire']) {
            $batch_sql = "SELECT 
                id, batch_number, exp_date, 
                remaining_qty, unit_cost, sell_price
            FROM inventory_batches
            WHERE product_id = ? AND store_id = ? AND remaining_qty > 0
            ORDER BY exp_date ASC
            LIMIT 10";
            $batch_stmt = $db->prepare($batch_sql);
            $batch_stmt->execute([$p['id'], $store_id]);
            $p['batches'] = $batch_stmt->fetchAll();
        } else {
            $p['batches'] = [];
        }
    }
    
    $total_pages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'page' => $page,
        'total_pages' => $total_pages,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'products' => []]);
}
