<?php
/**
 * حفظ الجرد اليدوي - أصناف محددة
 */
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: create.php");
    exit;
}

try {
    $store_id = intval($_POST['store_id'] ?? 0);
    $items = $_POST['items'] ?? [];
    $user_id = $_SESSION['user_id'] ?? 1;

    if ($store_id <= 0) throw new Exception('يجب اختيار المخزن');
    if (empty($items)) throw new Exception('يجب إضافة صنف واحد على الأقل');

    // Generate adjustment code
    $year_month = date('Ym');
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM stock_adjustments WHERE adjustment_code LIKE 'ADJ-{$year_month}-%'");
    $count = intval($stmt->fetch()['cnt']) + 1;
    $adjustment_code = 'ADJ-' . $year_month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

    // Insert adjustment
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO stock_adjustments 
        (adjustment_code, store_id, adjustment_type, status, notes, counted_by, counted_at, created_at)
        VALUES (?, ?, 'periodic', 'draft', 'جرد يدوي - أصناف محددة', ?, NOW(), NOW())
    ");
    $stmt->execute([$adjustment_code, $store_id, $user_id]);
    $adjustment_id = $db->lastInsertId();

    $item_stmt = $db->prepare("
        INSERT INTO stock_adjustment_items 
        (adjustment_id, product_id, batch_id, system_qty, actual_qty, variance_qty, unit_cost, variance_cost)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $total_items = 0;
    foreach ($items as $item) {
        $product_id = intval($item['product_id'] ?? 0);
        $actual_qty = floatval($item['actual_qty'] ?? 0);

        if ($product_id <= 0) continue;

        // Get current stock for this product in this store
        $stock = $db->prepare("
            SELECT SUM(quantity) as qty, unit_cost 
            FROM inventory_items 
            WHERE product_id = ? AND store_id = ? AND is_active = 1
            GROUP BY product_id
        ");
        $stock->execute([$product_id, $store_id]);
        $stock_data = $stock->fetch();

        $system_qty = floatval($stock_data['qty'] ?? 0);
        $unit_cost = floatval($stock_data['unit_cost'] ?? 0);
        $variance = $actual_qty - $system_qty;

        // Get first batch if exists
        $batch = $db->prepare("SELECT id FROM inventory_batches WHERE product_id = ? AND store_id = ? AND remaining_qty > 0 ORDER BY exp_date ASC LIMIT 1");
        $batch->execute([$product_id, $store_id]);
        $batch_id = $batch->fetch()['id'] ?? null;

        $item_stmt->execute([
            $adjustment_id, $product_id, $batch_id,
            $system_qty, $actual_qty, $variance,
            $unit_cost, $variance * $unit_cost
        ]);
        $total_items++;
    }

    // Update total items
    $db->prepare("UPDATE stock_adjustments SET total_items = ? WHERE id = ?")
        ->execute([$total_items, $adjustment_id]);

    $db->commit();

    header("Location: view.php?id={$adjustment_id}&success=1&manual=1");
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    header("Location: create.php?error=" . urlencode($e->getMessage()));
    exit;
}
