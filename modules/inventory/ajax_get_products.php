<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$store_id = intval($_GET['store_id'] ?? 0);
$search = $_GET['search'] ?? '';

if (!$store_id) {
    echo json_encode([]);
    exit;
}

// Get inventory products for a specific store with product details
$sql = "
    SELECT 
        ii.id,
        ii.product_id,
        p.product_name,
        p.product_code,
        p.barcode,
        p.sell_price,
        ii.unit_cost,
        ii.quantity as current_stock,
        pu.id as unit_id,
        pu.unit_name_ar as unit_name,
        pc.company_name,
        p.location
    FROM inventory_items ii
    JOIN products p ON ii.product_id = p.id
    LEFT JOIN product_units pu ON p.default_unit_id = pu.id
    LEFT JOIN product_companies pc ON p.company_id = pc.id
    WHERE ii.store_id = ? AND ii.quantity > 0 AND p.is_active = 1
";

$params = [$store_id];

if ($search) {
    $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.product_name LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Add units array for each product
foreach ($products as &$product) {
    // Get product units
    $unitsStmt = $db->prepare("
        SELECT pu.id, pu.unit_name_ar as name, pup.is_default
        FROM product_unit_products pup
        JOIN product_units pu ON pup.unit_id = pu.id
        WHERE pup.product_id = ?
    ");
    $unitsStmt->execute([$product['product_id']]);
    $product['units'] = $unitsStmt->fetchAll() ?: [];
    
    // Ensure numeric values
    $product['id'] = intval($product['id']);
    $product['product_id'] = intval($product['product_id']);
    $product['unit_cost'] = floatval($product['unit_cost'] ?: 0);
    $product['sell_price'] = floatval($product['sell_price'] ?: 0);
    $product['current_stock'] = floatval($product['current_stock'] ?: 0);
}
unset($product);

echo json_encode($products, JSON_UNESCAPED_UNICODE);
