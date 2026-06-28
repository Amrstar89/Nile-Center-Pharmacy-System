<?php
require_once __DIR__ . '/../core/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$product_id = intval($_GET['product_id'] ?? 0);
$store_id = intval($_GET['store_id'] ?? 0);

if ($product_id <= 0 || $store_id <= 0) {
    echo json_encode(['error' => 'معرفات غير صالحة']);
    exit;
}

$stmt = $db->prepare("
    SELECT id, batch_number, exp_date, remaining_qty, unit_cost, sell_price
    FROM inventory_batches
    WHERE product_id = ? AND store_id = ? AND remaining_qty > 0 AND exp_date >= CURDATE()
    ORDER BY exp_date ASC
");
$stmt->execute([$product_id, $store_id]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($batches);
