<?php
/**
 * API البحث المتقدم عن الأصناف
 */
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(0);

function sendError($msg) {
    echo json_encode(['error' => $msg, 'products' => [], 'total' => 0, 'total_pages' => 0, 'page' => 1]);
    exit;
}

try {
    $db = new PDO("mysql:host=localhost;dbname=nile_center;charset=utf8mb4", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    sendError('DB: ' . $e->getMessage());
}

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
    sendError('معرف المخزن مطلوب');
}

try {
    // Check tables exist
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $has_products = in_array('products', $tables);
    $has_inventory = in_array('inventory_items', $tables);
    
    if (!$has_products) {
        sendError('جدول products غير موجود');
    }

    // Build WHERE conditions
    $where = ["p.is_active = 1"];
    $params = [];

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
            default:
                $where[] = "(p.product_name LIKE ? OR p.product_name_en LIKE ? OR p.manual_code = ? OR p.product_code = ?)";
                $params[] = "%$query%";
                $params[] = "%$query%";
                $params[] = $query;
                $params[] = $query;
        }
    }

    if ($category > 0) { $where[] = "p.category_id = ?"; $params[] = $category; }
    if ($company > 0) { $where[] = "p.company_id = ?"; $params[] = $company; }
    if ($price_from > 0) { $where[] = "p.sell_price >= ?"; $params[] = $price_from; }
    if ($price_to > 0) { $where[] = "p.sell_price <= ?"; $params[] = $price_to; }

    $where_str = implode(' AND ', $where);
    $offset = ($page - 1) * $limit;

    // Check if inventory_items table exists and has is_active column
    $has_is_active = false;
    if ($has_inventory) {
        $cols = $db->query("SHOW COLUMNS FROM inventory_items LIKE 'is_active'")->fetchAll();
        $has_is_active = count($cols) > 0;
    }

    // Build the active condition for inventory_items
    $ii_active = $has_is_active ? "AND ii.is_active = 1" : "";

    // Check product_units join column
    $pu_col = "p.unit1_id";
    $pu_check = $db->query("SHOW COLUMNS FROM products LIKE 'unit_id'")->fetchAll();
    if (count($pu_check) > 0) {
        $pu_col = "p.unit_id";
    }

    // Get products with LIMIT/OFFSET inline (not as placeholders for MariaDB)
    $sql = "SELECT 
        p.id, p.product_name, p.product_name_en, p.product_code, p.manual_code,
        p.cost_price, p.sell_price, p.has_expire, p.scientific_name,
        u.unit_name_ar, pco.company_name_ar, pca.category_name_ar,
        COALESCE(SUM(ii.quantity), 0) as stock_qty,
        MAX(ii.unit_cost) as unit_cost
    FROM products p
    LEFT JOIN product_companies pco ON p.company_id = pco.id
    LEFT JOIN product_categories pca ON p.category_id = pca.id
    LEFT JOIN product_units u ON {$pu_col} = u.id
    ";
    
    if ($has_inventory) {
        $sql .= "LEFT JOIN inventory_items ii ON ii.product_id = p.id AND ii.store_id = ? {$ii_active}\n";
    } else {
        $sql .= "LEFT JOIN (SELECT ? as store_id, 0 as quantity, 0 as unit_cost, 0 as product_id) ii ON ii.product_id = p.id\n";
    }
    
    $sql .= "WHERE {$where_str}
    GROUP BY p.id";
    
    if (!$show_no_stock && $has_inventory) {
        $sql .= "\nHAVING stock_qty > 0";
    }
    
    // MariaDB fix: inline LIMIT/OFFSET as integers, not placeholders
    $sql .= "\nORDER BY p.product_name\nLIMIT " . intval($limit) . " OFFSET " . intval($offset);

    $stmt = $db->prepare($sql);
    
    // Bind store_id as first param
    $stmt->bindValue(1, intval($store_id), PDO::PARAM_INT);
    
    // Bind remaining params
    $paramIdx = 2;
    foreach ($params as $p) {
        $stmt->bindValue($paramIdx, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $paramIdx++;
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll();

    // Get total count
    $count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM products p 
        LEFT JOIN product_companies pco ON p.company_id = pco.id 
        LEFT JOIN product_categories pca ON p.category_id = pca.id
        WHERE {$where_str}";
    $count_stmt = $db->prepare($count_sql);
    foreach ($params as $i => $p) {
        $count_stmt->bindValue($i + 1, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $count_stmt->execute();
    $total = intval($count_stmt->fetch()['total'] ?? 0);

    // Get batches for each product
    foreach ($products as &$p) {
        $p['stock_qty'] = floatval($p['stock_qty']);
        $p['cost_price'] = floatval($p['cost_price']);
        $p['sell_price'] = floatval($p['sell_price']);
        $p['unit_cost'] = floatval($p['unit_cost'] ?? $p['cost_price']);
        $p['company_name'] = $p['company_name_ar'] ?? '-';
        $p['category_name'] = $p['category_name_ar'] ?? '-';
        
        if ($p['has_expire']) {
            $batch_stmt = $db->prepare("SELECT id, batch_number, exp_date, remaining_qty, unit_cost 
                FROM inventory_batches WHERE product_id = ? AND store_id = ? AND remaining_qty > 0 ORDER BY exp_date ASC LIMIT 10");
            $batch_stmt->execute([$p['id'], $store_id]);
            $p['batches'] = $batch_stmt->fetchAll();
        } else {
            $p['batches'] = [];
        }
    }

    $total_pages = max(1, ceil($total / $limit));
    
    echo json_encode([
        'success' => true, 
        'total' => $total, 
        'page' => $page,
        'total_pages' => $total_pages, 
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    sendError('SQL: ' . $e->getMessage());
}
