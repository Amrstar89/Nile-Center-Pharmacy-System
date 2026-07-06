<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$invoice_id = intval($_GET['invoice_id'] ?? 0);
if (!$invoice_id) { echo json_encode(['store_id'=>0,'store_name'=>'','items'=>[]]); exit; }

// Get store_id and store_name from the invoice
$inv = $db->prepare("SELECT pi.store_id, s.store_name FROM purchase_invoices pi LEFT JOIN stores s ON pi.store_id = s.id WHERE pi.id = ?");
$inv->execute([$invoice_id]);
$invData = $inv->fetch(PDO::FETCH_ASSOC);
$store_id = intval($invData['store_id'] ?? 0);
$store_name = $invData['store_name'] ?? '';

$stmt = $db->prepare("
    SELECT pii.id as invoice_item_id, pii.product_id, pii.product_name, pii.product_code, pii.barcode, pii.unit_name, pii.quantity, pii.unit_cost, pii.expiry_date, pii.batch_number, COALESCE(ii.quantity, 0) as current_stock
    FROM purchase_invoice_items pii
    LEFT JOIN inventory_items ii ON pii.product_id = ii.product_id AND ii.store_id = ?
    WHERE pii.invoice_id = ?
    ORDER BY pii.id
");
$stmt->execute([$store_id, $invoice_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'store_id' => $store_id,
    'store_name' => $store_name,
    'items' => $items
]);
