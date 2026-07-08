<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$customer_id = intval($_GET['customer_id'] ?? 0);

if (!$customer_id) {
    echo json_encode([]);
    exit;
}

// Get all paid/partial invoices for this customer that have items that can be returned
$stmt = $db->prepare("
    SELECT 
        si.id,
        si.invoice_number,
        si.invoice_date,
        si.grand_total,
        si.paid_amount,
        si.payment_method,
        si.status,
        si.store_id,
        s.store_name
    FROM sale_invoices si
    LEFT JOIN stores s ON si.store_id = s.id
    WHERE si.customer_id = ?
      AND si.status IN ('paid', 'partial', 'open')
      AND si.id IN (
          SELECT DISTINCT invoice_id FROM sale_invoice_items
      )
    ORDER BY si.invoice_date DESC, si.id DESC
    LIMIT 50
");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();

echo json_encode($invoices, JSON_UNESCAPED_UNICODE);
