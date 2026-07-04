<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$id = intval($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/modules/purchases/orders/');

// Handle receive submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive') {
    try {
        $db->beginTransaction();
        
        $db->prepare("INSERT INTO po_receipts (po_id, receipt_date, received_by, notes) VALUES (?, NOW(), ?, ?)")
           ->execute([$id, $_SESSION['user_id'], $_POST['receipt_notes'] ?? '']);
        $receipt_id = $db->lastInsertId();
        
        $receiptItemStmt = $db->prepare("INSERT INTO po_receipt_items (receipt_id, po_item_id, quantity, unit_cost, batch_number, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
        $updateItemStmt = $db->prepare("UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ?");
        
        foreach ($_POST['receive'] as $item_id => $data) {
            $qty = floatval($data['quantity']);
            if ($qty <= 0) continue;
            
            $item = $db->prepare("SELECT * FROM purchase_order_items WHERE id = ?")->execute([$item_id]) ? $db->prepare("SELECT * FROM purchase_order_items WHERE id = ?")->fetch() : null;
            if (!$item) continue;
            
            $receiptItemStmt->execute([$receipt_id, $item_id, $qty, $item['unit_cost'], $data['batch'] ?? null, $data['expiry'] ?: null]);
            $updateItemStmt->execute([$qty, $item_id]);
        }
        
        // Check if fully received
        $statusCheck = $db->prepare("SELECT SUM(quantity) as total_qty, SUM(received_qty) as total_received FROM purchase_order_items WHERE po_id = ?")->execute([$id]) ? $db->prepare("SELECT SUM(quantity) as total_qty, SUM(received_qty) as total_received FROM purchase_order_items WHERE po_id = ?")->fetch() : null;
        if ($statusCheck) {
            $newStatus = $statusCheck['total_received'] >= $statusCheck['total_qty'] ? 'received' : 'partial';
            $db->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        }
        
        logActivity('po_receive', 'purchase_orders', $id);
        $db->commit();
        $_SESSION['success'] = 'تم تسجيل الاستلام بنجاح';
        redirect(APP_URL . '/modules/purchases/orders/view.php?id=' . $id);
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

$po = $db->prepare("SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?")->execute([$id]) ? $db->prepare("SELECT po.*, s.supplier_name FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE po.id = ?")->fetch() : null;
if (!$po) redirect(APP_URL . '/modules/purchases/orders/');

$items = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?")->execute([$id]) ? $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?")->fetchAll() : [];

$page_title = 'استلام أمر شراء ' . $po['po_number'];
require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#667eea;--secondary:#764ba2}
body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
.main-content{margin-right:260px;padding:20px}
.topbar{background:white;border-radius:15px;padding:15px 25px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
.sec-card{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px}
@media(max-width:768px){.main-content{margin-right:0}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar">
        <div><h5 class="mb-0"><i class="bi bi-box-arrow-in-down"></i> <?= $page_title ?></h5></div>
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> عودة</a>
    </div>

    <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="sec-card">
        <h6>المورد: <?= $po['supplier_name'] ?> | الإجمالي: <?= number_format($po['grand_total'],2) ?> ج</h6>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="receive">
        <div class="sec-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>الصنف</th><th>الكمية المطلوبة</th><th>تم استلامه</th><th>المتبقي</th><th>الكمية المستلمة الآن</th><th>رقم الباتش</th><th>تاريخ الصلاحية</th></tr></thead>
                    <tbody>
                    <?php foreach($items as $it):
                        $remaining = $it['quantity'] - $it['received_qty'];
                    ?>
                    <tr>
                        <td><strong><?= $it['product_name'] ?></strong><br><small class="text-muted"><?= $it['product_code'] ?></small></td>
                        <td><?= $it['quantity'] ?></td>
                        <td><?= $it['received_qty'] ?></td>
                        <td><span class="badge bg-<?= $remaining>0?'warning':'success' ?>"><?= $remaining ?></span></td>
                        <td><input type="number" name="receive[<?= $it['id'] ?>][quantity]" class="form-control" step="0.001" min="0" max="<?= $remaining ?>" value="<?= $remaining > 0 ? $remaining : 0 ?>" <?= $remaining<=0?'disabled':'' ?>></td>
                        <td><input type="text" name="receive[<?= $it['id'] ?>][batch]" class="form-control" placeholder="باتش" <?= $remaining<=0?'disabled':'' ?>></td>
                        <td><input type="date" name="receive[<?= $it['id'] ?>][expiry]" class="form-control" <?= $remaining<=0?'disabled':'' ?>></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sec-card">
            <label class="form-label">ملاحظات الاستلام</label>
            <textarea name="receipt_notes" class="form-control" rows="2"></textarea>
        </div>

        <div class="text-center mb-4">
            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-lg"></i> تأكيد الاستلام</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
