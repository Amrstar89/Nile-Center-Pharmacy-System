<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$supplier_id = intval($_GET['supplier_id'] ?? 0);
if (!$supplier_id) { echo json_encode([]); exit; }

$stmt = $db->prepare("SELECT id, invoice_number, invoice_date, grand_total FROM purchase_invoices WHERE supplier_id = ? AND status != 'cancelled' ORDER BY invoice_date DESC");
$stmt->execute([$supplier_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
