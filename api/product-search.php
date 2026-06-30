<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    $barcode = trim($_GET['barcode'] ?? '');
    $store_id = intval($_GET['store_id'] ?? 0);
    $all_store = isset($_GET['all_store']) && $_GET['all_store'] == '1';
    $query = trim($_GET['q'] ?? '');
    
    $sql = "
        SELECT p.*, c.company_name, u.unit_name_ar,
            (SELECT SUM(ii.quantity) FROM inventory_items ii WHERE ii.product_id = p.id AND ii.is_active = 1" . ($store_id > 0 ? " AND ii.store_id = ?" : "") . ") as stock_qty
    ";
    $params = [];
    if ($store_id > 0) $params[] = $store_id;
    
    $sql .= " FROM products p 
        LEFT JOIN companies c ON p.company_id = c.id 
        LEFT JOIN product_units u ON p.unit_id = u.id 
        WHERE p.is_active = 1 ";
    
    if (!empty($barcode)) {
        $sql .= " AND (p.barcode = ? OR p.product_code = ? OR p.manual_code = ?)";
        $params[] = $barcode;
        $params[] = $barcode;
        $params[] = $barcode;
    } elseif (!empty($query)) {
        $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.manual_code LIKE ?)";
        $params[] = "%{$query}%";
        $params[] = "%{$query}%";
        $params[] = "%{$query}%";
    }
    
    $sql .= " ORDER BY p.product_name LIMIT 50";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get batches for each product if store specified
    foreach ($products as &$product) {
        $product['batches'] = [];
        if ($store_id > 0 || $all_store) {
            $batch_sql = "SELECT id, batch_number, exp_date, remaining_qty, unit_cost, sell_price 
                          FROM inventory_batches 
                          WHERE product_id = ? AND remaining_qty > 0";
            $batch_params = [$product['id']];
            
            if ($store_id > 0) {
                $batch_sql .= " AND store_id = ?";
                $batch_params[] = $store_id;
            }
            $batch_sql .= " ORDER BY exp_date ASC";
            
            $batch_stmt = $db->prepare($batch_sql);
            $batch_stmt->execute($batch_params);
            $product['batches'] = $batch_stmt->fetchAll();
        }
    }
    
    echo json_encode($products, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}