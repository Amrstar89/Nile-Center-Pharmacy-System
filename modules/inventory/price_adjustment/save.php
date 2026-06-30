<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

try {
    $db->beginTransaction();
    $store_id = intval($_POST['store_id'] ?? 0);
    $user_id = $_SESSION['user_id'] ?? 1;

    foreach ($_POST['items'] as $item) {
        $inv_id = intval($item['inv_id'] ?? 0);
        $product_id = intval($item['product_id'] ?? 0);
        $batch_id = !empty($item['batch_id']) ? intval($item['batch_id']) : null;
        
        $new_cost = floatval($item['new_cost'] ?? 0);
        $new_sell = floatval($item['new_sell'] ?? 0);
        $discount = floatval($item['discount'] ?? 0);
        $vat = floatval($item['vat'] ?? 0);

        // Get old values
        $old = $db->prepare("SELECT * FROM inventory_items WHERE id = ?");
        $old->execute([$inv_id]);
        $old_data = $old->fetch();

        if (!$old_data) continue;

        // Log adjustment
        $db->prepare("
            INSERT INTO stock_price_adjustments 
            (adjustment_code, store_id, product_id, batch_id, old_cost_price, new_cost_price, old_sell_price, new_sell_price, old_discount, new_discount, old_vat, new_vat, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            'PRICE-' . time() . '-' . $inv_id,
            $store_id, $product_id, $batch_id,
            $old_data['unit_cost'], $new_cost,
            $old_data['sell_price'], $new_sell,
            $old_data['discount_percent'] ?? 0, $discount,
            $old_data['vat_percent'] ?? 0, $vat,
            $user_id
        ]);

        // Update inventory
        $db->prepare("
            UPDATE inventory_items 
            SET unit_cost = ?, sell_price = ?, discount_percent = ?, vat_percent = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$new_cost, $new_sell, $discount, $vat, $inv_id]);
    }

    $db->commit();
    header("Location: index.php?store_id={$store_id}&success=1");
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    header("Location: index.php?store_id={$store_id}&error=" . urlencode($e->getMessage()));
    exit;
}