<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$invoice_id = intval($_GET['invoice_id'] ?? 0);

if (!$invoice_id) {
    echo json_encode(['store_id' => null, 'store_name' => '', 'items' => []]);
    exit;
}

// Get the invoice store info
$invStmt = $db->prepare("
    SELECT si.store_id, s.store_name 
    FROM sale_invoices si 
    LEFT JOIN stores s ON si.store_id = s.id 
    WHERE si.id = ?
");
$invStmt->execute([$invoice_id]);
$invoice = $invStmt->fetch();

$store_id = $invoice ? intval($invoice['store_id']) : null;
$store_name = $invoice ? ($invoice['store_name'] ?? '') : '';

// Get items with current stock in that store
$stmt = $db->prepare("
    SELECT 
        sii.id as invoice_item_id,
        sii.product_id,
        sii.product_name,
        sii.product_code,
        sii.barcode,
        sii.unit_name,
        sii.quantity,
        sii.unit_cost,
        sii.sell_price,
        sii.discount_pct,
        sii.discount_val,
        sii.vat_pct,
        sii.vat_val,
        sii.line_total,
        sii.profit_val,
        sii.expiry_date,
        sii.batch_number,
        COALESCE(ii.quantity, 0) as current_stock
    FROM sale_invoice_items sii
    LEFT JOIN inventory_items ii ON ii.product_id = sii.product_id AND ii.store_id = :store_id
    WHERE sii.invoice_id = :invoice_id
    ORDER BY sii.id ASC
");
$stmt->execute([':store_id' => $store_id ?: 0, ':invoice_id' => $invoice_id]);
$items = $stmt->fetchAll();

// Get already returned quantities for each item
$retStmt = $db->prepare("
    SELECT 
        sri.invoice_item_id,
        COALESCE(SUM(sri.quantity), 0) as returned_qty
    FROM sale_return_items sri
    JOIN sale_returns sr ON sri.return_id = sr.id
    WHERE sr.invoice_id = ? AND sr.status != 'cancelled'
    GROUP BY sri.invoice_item_id
");
$retStmt->execute([$invoice_id]);
$returned = [];
while ($r = $retStmt->fetch()) {
    $returned[$r['invoice_item_id']] = floatval($r['returned_qty']);
}

// Add remaining_qty to each item
foreach ($items as &$item) {
    $item['returned_qty'] = $returned[$item['invoice_item_id']] ?? 0;
    $item['remaining_qty'] = floatval($item['quantity']) - $item['returned_qty'];
}
unset($item);

// Filter out fully returned items
$items = array_filter($items, function($it) {
    return floatval($it['remaining_qty']) > 0;
});

echo json_encode([
    'store_id' => $store_id,
    'store_name' => $store_name,
    'items' => array_values($items)
], JSON_UNESCAPED_UNICODE);
