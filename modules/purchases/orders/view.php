<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$id = intval($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/modules/purchases/orders/');

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_status') {
        $newStatus = $_POST['status'];
        $db->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
        logActivity('po_status_change', 'purchase_orders', $id, null, ['status' => $newStatus]);
        $_SESSION['success'] = 'تم تحديث الحالة بنجاح';
        redirect(APP_URL . '/modules/purchases/orders/view.php?id=' . $id);
    }
    if ($_POST['action'] === 'delete') {
        $db->prepare("DELETE FROM purchase_orders WHERE id = ?")->execute([$id]);
        logActivity('po_delete', 'purchase_orders', $id);
        $_SESSION['success'] = 'تم الحذف بنجاح';
        redirect(APP_URL . '/modules/purchases/orders/');
    }
}

$po = $db->prepare("
    SELECT po.*, s.supplier_name, s.supplier_code, s.phone, s.address, s.email,
           b.branch_name, st.store_name, u.full_name as creator
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN branches b ON po.branch_id = b.id
    LEFT JOIN stores st ON po.store_id = st.id
    JOIN users u ON po.created_by = u.id
    WHERE po.id = ?
")->execute([$id]) ? $db->prepare("SELECT po.*, s.supplier_name, s.supplier_code, s.phone, s.address, s.email, b.branch_name, st.store_name, u.full_name as creator FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id LEFT JOIN branches b ON po.branch_id = b.id LEFT JOIN stores st ON po.store_id = st.id JOIN users u ON po.created_by = u.id WHERE po.id = ?")->fetch() : null;

if (!$po) redirect(APP_URL . '/modules/purchases/orders/');

$items = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?")->execute([$id]) ? $db->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?")->fetchAll() : [];

$statusColors = ['draft' => 'secondary', 'sent' => 'info', 'partial' => 'warning', 'received' => 'success', 'cancelled' => 'danger'];
$statusLabels = ['draft' => 'مسودة', 'sent' => 'مرسل', 'partial' => 'استلام جزئي', 'received' => 'مستلم بالكامل', 'cancelled' => 'ملغي'];

$page_title = 'أمر شراء ' . $po['po_number'];
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
.topbar{background:white;border-radius:15px;padding:15px 25px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center}
.sec-card{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px}
.status-badge{font-size:20px;padding:8px 20px;border-radius:25px}
@media(max-width:768px){.main-content{margin-right:0}}
@media print{.sidebar,.topbar,.no-print{display:none!important}.main-content{margin-right:0!important}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar no-print">
        <div><h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> <?= $page_title ?></h5></div>
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> عودة</a>
            <a href="create.php?copy=<?= $po['id'] ?>" class="btn btn-info btn-sm"><i class="bi bi-copy"></i> نسخ</a>
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success no-print"><?= $_SESSION['success'] ?></div><?php unset($_SESSION['success']); endif; ?>

    <!-- Supplier Info -->
    <div class="sec-card">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="bi bi-shop"></i> <?= $po['supplier_name'] ?></h5>
                <p class="text-muted mb-1">كود: <?= $po['supplier_code'] ?></p>
                <?php if($po['phone']): ?><p class="mb-1"><i class="bi bi-telephone"></i> <?= $po['phone'] ?></p><?php endif; ?>
                <?php if($po['address']): ?><p class="mb-1"><i class="bi bi-geo-alt"></i> <?= $po['address'] ?></p><?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="badge bg-<?= $statusColors[$po['status']] ?> status-badge"><?= $statusLabels[$po['status']] ?></span>
                <h4 class="mt-2"><?= $po['po_number'] ?></h4>
                <p class="text-muted mb-1">التاريخ: <?= arabicDate($po['order_date']) ?></p>
                <?php if($po['expected_date']): ?><p class="mb-1">متوقع الاستلام: <?= arabicDate($po['expected_date']) ?></p><?php endif; ?>
                <?php if($po['store_name']): ?><p class="mb-1"><i class="bi bi-building"></i> <?= $po['store_name'] ?></p><?php endif; ?>
                <?php if($po['branch_name']): ?><p class="mb-1"><i class="bi bi-shop"></i> <?= $po['branch_name'] ?></p><?php endif; ?>
                <p class="text-muted">بواسطة: <?= $po['creator'] ?></p>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="sec-card">
        <h6 class="mb-3"><i class="bi bi-boxes"></i> الأصناف (<?= count($items) ?>)</h6>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>#</th><th>الصنف</th><th>الكود</th><th>الوحدة</th><th>الكمية</th><th>مستلم</th><th>سعر الوحدة</th><th>سعر البيع</th><th>الإجمالي</th></tr></thead>
                <tbody>
                <?php foreach($items as $i => $it):
                    $pct = $it['quantity'] > 0 ? round(($it['received_qty']/$it['quantity'])*100) : 0;
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= $it['product_name'] ?></td>
                    <td><?= $it['product_code'] ?: '-' ?></td>
                    <td><?= $it['unit_name'] ?></td>
                    <td><?= $it['quantity'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2" style="min-width:100px">
                            <?= $it['received_qty'] ?> / <?= $it['quantity'] ?>
                            <div class="progress flex-fill" style="height:6px"><div class="progress-bar bg-success" style="width:<?= $pct ?%"></div></div>
                        </div>
                    </td>
                    <td><?= number_format($it['unit_cost'],2) ?> ج</td>
                    <td><?= $it['sell_price'] ? number_format($it['sell_price'],2).' ج' : '-' ?></td>
                    <td class="fw-bold"><?= number_format($it['line_total'],2) ?> ج</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Totals -->
    <div class="sec-card">
        <div class="row">
            <div class="col-md-3"><strong>الإجمالي:</strong> <?= number_format($po['subtotal'],2) ?> ج</div>
            <div class="col-md-3"><strong>الخصم:</strong> <?= number_format($po['discount_value'],2) ?> <?= $po['discount_type']==='percentage'?'%':'ج' ?></div>
            <div class="col-md-3"><strong>الضريبة (<?= $po['vat_percent'] ?>%):</strong> <?= number_format($po['vat_amount'],2) ?> ج</div>
            <div class="col-md-3"><strong>النهائي:</strong> <span class="text-primary fw-bold fs-5"><?= number_format($po['grand_total'],2) ?> ج</span></div>
        </div>
        <?php if($po['notes']): ?><div class="mt-3"><strong>ملاحظات:</strong> <?= nl2br($po['notes']) ?></div><?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="sec-card no-print">
        <h6 class="mb-3">الإجراءات</h6>
        <div class="row g-2">
            <div class="col-md-3">
                <form method="POST"><input type="hidden" name="action" value="change_status">
                    <div class="input-group">
                        <select name="status" class="form-select">
                            <?php foreach($statusLabels as $k=>$v): ?><option value="<?= $k ?>" <?= $po['status']==$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">تحديث</button>
                    </div>
                </form>
            </div>
            <div class="col-md-3">
                <a href="../invoices/create.php?po_id=<?= $po['id'] ?>" class="btn btn-success w-100"><i class="bi bi-receipt"></i> إنشاء فاتورة شراء</a>
            </div>
            <div class="col-md-3">
                <a href="receive.php?id=<?= $po['id'] ?>" class="btn btn-info w-100 <?= $po['status']==='received' || $po['status']==='cancelled' ? 'disabled' : '' ?>"><i class="bi bi-box-arrow-in-down"></i> استلام البنود</a>
            </div>
            <div class="col-md-3">
                <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؞')"><input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger w-100"><i class="bi bi-trash"></i> حذف</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
