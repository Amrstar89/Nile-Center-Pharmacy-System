<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$code = trim($_GET['code'] ?? '');
$name = trim($_GET['name'] ?? '');
$barcode = trim($_GET['barcode'] ?? '');
$scientific = trim($_GET['scientific'] ?? '');
$company = intval($_GET['company'] ?? 0);
$category = intval($_GET['category'] ?? 0);
$type = intval($_GET['type'] ?? 0);
$store_id = intval($_GET['store_id'] ?? 0);

$where = "WHERE p.is_active = 1";
$params = [];

if (!empty($code)) {
    $where .= " AND (p.product_code LIKE ? OR p.manual_code LIKE ?)";
    $params[] = "%{$code}%";
    $params[] = "%{$code}%";
}

if (!empty($name)) {
    $where .= " AND (p.product_name LIKE ? OR p.product_name_en LIKE ?)";
    $params[] = "%{$name}%";
    $params[] = "%{$name}%";
}

if (!empty($barcode)) {
    $where .= " AND EXISTS (SELECT 1 FROM product_barcodes pb WHERE pb.product_id = p.id AND pb.barcode LIKE ?)";
    $params[] = "%{$barcode}%";
}

if (!empty($scientific)) {
    $where .= " AND p.scientific_name LIKE ?";
    $params[] = "%{$scientific}%";
}

if ($company > 0) {
    $where .= " AND p.company_id = ?";
    $params[] = $company;
}

if ($category > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category;
}

if ($type > 0) {
    $where .= " AND p.product_type_id = ?";
    $params[] = $type;
}

$sql = "SELECT 
    p.id, p.product_code, p.manual_code, p.product_name, p.product_name_en,
    p.scientific_name, p.cost_price, p.sell_price,
    pc.company_name_ar as company_name,
    pt.type_name_ar as type_name,
    cat.category_name_ar as category_name,
    (SELECT SUM(ii.quantity) FROM inventory_items ii WHERE ii.product_id = p.id AND ii.is_active = 1) as stock_qty
FROM products p
LEFT JOIN product_companies pc ON p.company_id = pc.id
LEFT JOIN product_types pt ON p.product_type_id = pt.id
LEFT JOIN product_categories cat ON p.category_id = cat.id
{$where}
ORDER BY p.product_name
LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($products);
