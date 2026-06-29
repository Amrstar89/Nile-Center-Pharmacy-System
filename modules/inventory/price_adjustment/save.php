<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    $db->beginTransaction();
    
    $store_id = intval($_POST['store_id'] ?? 0);
    $selected = $_POST['selected'] ?? [];
    $costs = $_POST['cost'] ?? [];
    $sells = $_POST['sell'] ?? [];
    $discounts = $_POST['discount'] ?? [];
    $vats = $_POST['vat'] ?? [];
    
    if ($store_id <= 0 || empty($selected)) {
        throw new Exception('يجب اختيار صنف واحد على الأقل');
    }
    
    $updated = 0;
    $stmt = $db->prepare("
        UPDATE inventory_items 
        SET unit_cost = ?, sell_price = ?, discount_percent = ?, vat_percent = ?, updated_at = NOW()
        WHERE id = ? AND store_id = ?
    ");
    
    foreach ($selected as $item_id) {
        $cost = isset($costs[$item_id]) ? floatval($costs[$item_id]) : null;
        $sell = isset($sells[$item_id]) ? floatval($sells[$item_id]) : null;
        $discount = isset($discounts[$item_id]) ? floatval($discounts[$item_id]) : 0;
        $vat = isset($vats[$item_id]) ? floatval($vats[$item_id]) : 0;
        
        $stmt->execute([$cost, $sell, $discount, $vat, $item_id, $store_id]);
        $updated++;
    }
    
    $db->commit();
    
    header("Location: index.php?branch=" . intval($_POST['branch'] ?? 0) . "&store=$store_id&success=1");
    exit;
    
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit;
}
