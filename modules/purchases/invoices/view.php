<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$id = intval($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/modules/purchases/invoices/');

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $amount = floatval($_POST['payment_amount']);
    $method = $_POST['payment_method'];
    $ref = $_POST['reference_no'] ?? '';
    
    $db->beginTransaction();
    
    $payNum = 'PAY-' . date('Y') . '-' . time();
    $db->prepare("INSERT INTO supplier_payments (payment_number, supplier_id, invoice_id, amount, payment_date, payment_method, reference_no, created_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)")
       ->execute([$payNum, $_POST['supplier_id'], $id, $amount, $method, $ref, $_SESSION['user_id']]);
    
    $db->prepare("UPDATE purchase_invoices SET paid_amount = paid_amount + ? WHERE id = ?")->execute([$amount, $id]);
    
    // Update status
    $inv = $db->prepare("SELECT grand_total, paid_amount FROM purchase_invoices WHERE id = ?")->execute([$id]) ? $db->prepare("SELECT grand_total, paid_amount FROM purchase_invoices WHERE id = ?")->fetch() : null;
    if ($inv) {
        $newStatus = $inv['paid_amount'] >= $inv['grand_total'] ? 'paid' : 'partial';
        $db->prepare("UPDATE purchase_invoices SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
    }
    
    $db->commit();
    $_SESSION['success'] = 'تم تسجيل الدفعة بنجاح';
    redirect(APP_URL . '/modules/purchases/invoices/view.php?id=' . $id);
}

$inv = $db->prepare("SELECT pi.*, s.supplier_name, s.supplier_code, s.phone, s.address, po.po_number, u.full_name as creator
    FROM purchase_invoices pi JOIN suppliers s ON pi.supplier_id = s.id LEFT JOIN purchase_orders po ON pi.po_id = po.id JOIN users u ON pi.created_by = u.id WHERE pi.id = ?")->execute([$id]) ? $db->prepare("SELECT pi.*, s.supplier_name, s.supplier_code, s.phone, s.address, po.po_number, u.full_name as creator FROM purchase_invoices pi JOIN suppliers s ON pi.supplier_id = s.id LEFT JOIN purchase_orders po ON pi.po_id = po.id JOIN users u ON pi.created_by = u.id WHERE pi.id = ?")->fetch() : null;
if (!$inv) redirect(APP_URL . '/modules/purchases/invoices/');

$items = $db->prepare("SELECT * FROM purchase_invoice_items WHERE invoice_id = ?")->execute([$id]) ? $db->prepare("SELECT * FROM purchase_invoice_items WHERE invoice_id = ?")->fetchAll() : [];
$payments = $db->prepare("SELECT * FROM supplier_payments WHERE invoice_id = ? ORDER BY created_at DESC")->execute([$id]) ? $db->prepare("SELECT * FROM supplier_payments WHERE invoice_id = ? ORDER BY created_at DESC")->fetchAll() : [];

$icolors=['open'=>'warning','partial'=>'info','paid'=>'success','cancelled'=>'danger'];
$ilabels=['open'=>'مفتوحة','partial'=>'جزئي','paid'=>'مسددة','cancelled'=>'ملغية'];

$page_title = 'فاتورة شراء ' . $inv['invoice_number'];
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
@media(max-width:768px){.main-content{margin-right:0}}
@media print{.sidebar,.topbar,.no-print{display:none!important}.main-content{margin-right:0!important}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar no-print">
        <div><h5 class="mb-0"><i class="bi bi-receipt"></i> <?= $page_title ?></h5></div>
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-right"></i> عودة</a>
            <a href="create.php" class="btn btn-info btn-sm"><i class="bi bi-plus-lg"></i> جديدة</a>
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success no-print"><?= $_SESSION['success'] ?></div><?php unset($_SESSION['success']); endif; ?>

    <div class="sec-card">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="bi bi-shop"></i> <?= $inv['supplier_name'] ?></h5>
                <p class="text-muted mb-1">كود: <?= $inv['supplier_code'] ?></p>
                <?php if($inv['phone']): ?><p class="mb-1"><i class="bi bi-telephone"></i> <?= $inv['phone'] ?></p><?php endif; ?>
                <?php if($inv['address']): ?><p class="mb-1"><i class="bi bi-geo-alt"></i> <?= $inv['address'] ?></p><?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="badge bg-<?= $icolors[$inv['status']] ?> fs-6 px-3 py-2"><?= $ilabels[$inv['status']] ?></span>
                <h4 class="mt-2"><?= $inv['invoice_number'] ?></h4>
                <?php if($inv['supplier_invoice_no']): ?><p class="mb-1 text-muted">فاتورة المورد: <?= $inv['supplier_invoice_no'] ?></p><?php endif; ?>
                <?php if($inv['po_number']): ?><p class="mb-1"><i class="bi bi-link"></i> <?= $inv['po_number'] ?></p><?php endif; ?>
                <p class="text-muted mb-1">التاريخ: <?= arabicDate($inv['invoice_date']) ?></p>
                <p class="text-muted mb-1">استحقاق: <?= $inv['due_date'] ? arabicDate($inv['due_date']) : 'فوري' ?></p>
                <p class="text-muted">بواسطة: <?= $inv['creator'] ?></p>
            </div>
        </div>
    </div>

    <div class="sec-card">
        <h6 class="mb-3"><i class="bi bi-boxes"></i> الأصناف (<?= count($items) ?>)</h6>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>#</th><th>الصنف</th><th>الكود</th><th>الوحدة</th><th>الكمية</th><th>سعر الوحدة</th><th>الخصم</th><th>الإجمالي</th></tr></thead>
                <tbody>
                <?php foreach($items as $i=>$it): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= $it['product_name'] ?></td>
                    <td><?= $it['product_code'] ?: '-' ?></td>
                    <td><?= $it['unit_name'] ?></td>
                    <td><?= $it['quantity'] ?></td>
                    <td><?= number_format($it['unit_cost'],2) ?> ج</td>
                    <td><?= $it['discount_percent'] ?>%</td>
                    <td class="fw-bold"><?= number_format($it['line_total'],2) ?> ج</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="sec-card">
        <div class="row">
            <div class="col-md-2"><strong>الإجمالي:</strong> <?= number_format($inv['subtotal'],2) ?> ج</div>
            <div class="col-md-2"><strong>الخصم:</strong> <?= $inv['discount_type']==='percentage' ? $inv['discount_value'].'%' : number_format($inv['discount_value'],2).' ج' ?></div>
            <div class="col-md-2"><strong>الضريبة:</strong> <?= number_format($inv['vat_amount'],2) ?> ج</div>
            <div class="col-md-2"><strong>الشحن:</strong> <?= number_format($inv['shipping_cost'],2) ?> ج</div>
            <div class="col-md-2"><strong>النهائي:</strong> <span class="text-primary fw-bold"><?= number_format($inv['grand_total'],2) ?> ج</span></div>
            <div class="col-md-2"><strong>المتبقي:</strong> <span class="text-danger fw-bold"><?= number_format($inv['remaining_amount'],2) ?> ج</span></div>
        </div>
        <?php if($inv['notes']): ?><div class="mt-3"><strong>ملاحظات:</strong> <?= nl2br($inv['notes']) ?></div><?php endif; ?>
    </div>

    <!-- Payments -->
    <div class="sec-card">
        <h6 class="mb-3"><i class="bi bi-cash-coin"></i> المدفوعات (<?= count($payments) ?>)</h6>
        <?php if($payments): ?>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>رقم الدفعة</th><th>المبلغ</th><th>الطريقة</th><th>التاريخ</th></tr></thead>
                <tbody>
                <?php foreach($payments as $p): ?>
                <tr><td><?= $p['payment_number'] ?></td><td class="fw-bold"><?= number_format($p['amount'],2) ?> ج</td><td><?= $p['payment_method'] ?></td><td><?= arabicDate($p['payment_date']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><p class="text-muted">لا توجد مدفوعات</p><?php endif; ?>
    </div>

    <!-- Add Payment -->
    <?php if($inv['remaining_amount'] > 0): ?>
    <div class="sec-card no-print">
        <h6 class="mb-3"><i class="bi bi-plus-circle"></i> تسجيل دفعة جديدة</h6>
        <form method="POST" class="row g-2">
            <input type="hidden" name="action" value="add_payment">
            <input type="hidden" name="supplier_id" value="<?= $inv['supplier_id'] ?>">
            <div class="col-md-3"><input type="number" name="payment_amount" class="form-control" step="0.01" max="<?= $inv['remaining_amount'] ?>" placeholder="المبلغ *" required></div>
            <div class="col-md-2">
                <select name="payment_method" class="form-select">
                    <option value="cash">نقدي</option><option value="bank_transfer">تحويل بنكي</option><option value="check">شيك</option><option value="credit">آجل</option>
                </select>
            </div>
            <div class="col-md-3"><input type="text" name="reference_no" class="form-control" placeholder="رقم المرجع"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-success w-100"><i class="bi bi-check-lg"></i> تسجيل</button></div>
        </form>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
