<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/estock-bridge.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = $_GET['q'] ?? '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$results = searchESTOCKProducts($query, 50);

$output = [];
foreach ($results as $prod) {
    $output[] = [
        'id' => $prod['product_id'],
        'text' => ($prod['product_name_en'] ?? $prod['product_name_ar'] ?? $prod['product_code']) . ' (' . $prod['product_code'] . ')',
        'code' => $prod['product_code'],
        'price' => $prod['sell_price'] ?? 0
    ];
}

echo json_encode(['results' => $output]);
?>