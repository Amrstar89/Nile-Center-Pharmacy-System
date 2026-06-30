<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    $store_id = intval($_GET['store_id'] ?? 0);
    $search = trim($_GET['q'] ?? '');
    
    if ($store_id <= 0) {
        echo json_encode(['error' => 'معرف المخزن مطلوب']);
        exit;
    }
    
    // Get all products with stock in this store
    $sql = "
        SELECT 
            p.id,
            p.product_name,
            p.product_code,
            p.manual_code,
            p.barcode,
            p.cost_price,
            p.sell_price,
            p.has_expire,
            p.unit_id,
            u.unit_name_ar,
            COALESCE(SUM(ii.quantity), 0) as stock_qty,
            ii.unit_cost,
            GROUP_CONCAT(DISTINCT ib.id ORDER BY ib.exp_date ASC) as batch_ids,
            GROUP_CONCAT(DISTINCT ib.exp_date ORDER BY ib.exp_date ASC SEPARATOR '|') as exp_dates,
            MIN(ib.exp_date) as nearest_exp_date
        FROM products p
        LEFT JOIN inventory_items ii ON ii.product_id = p.id AND ii.store_id = ? AND ii.is_active = 1
        LEFT JOIN inventory_batches ib ON ib.product_id = p.id AND ib.store_id = ? AND ib.remaining_qty > 0
        LEFT JOIN product_units u ON p.unit_id = u.id
        WHERE p.is_active = 1
    ";
    $params = [$store_id, $store_id];
    
    if ($search) {
        $sql .= " AND (p.product_name LIKE ? OR p.barcode = ? OR p.product_code = ? OR p.manual_code = ?)";
        $params[] = "%$search%";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    $sql .= " GROUP BY p.id HAVING stock_qty > 0 ORDER BY p.product_name LIMIT 200";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Format batches
    foreach ($products as &$p) {
        $p['stock_qty'] = floatval($p['stock_qty']);
        $p['unit_cost'] = floatval($p['unit_cost'] ?? $p['cost_price'] ?? 0);
        
        // Build batches array
        $p['batches'] = [];
        if ($p['batch_ids']) {
            $ids = explode(',', $p['batch_ids']);
            $dates = explode('|', $p['exp_dates']);
            foreach ($ids as $i => $bid) {
                if (isset($dates[$i])) {
                    $p['batches'][] = ['id' => $bid, 'exp_date' => $dates[$i]];
                }
            }
        }
        unset($p['batch_ids'], $p['exp_dates']);
    }
    
    echo json_encode($products, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
