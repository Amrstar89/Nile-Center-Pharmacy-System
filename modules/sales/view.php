<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();
$id = intval($_GET['id'] ?? 0);
if (!$id) { redirect(APP_URL . '/modules/sales/invoices.php'); }

$invoice = $db->prepare("
    SELECT si.*, c.customer_name, c.customer_code, c.phone, s.store_name, u.full_name as user_name
    FROM sale_invoices si
    LEFT JOIN customers c ON si.customer_id = c.id
    LEFT JOIN stores s ON si.store_id = s.id
    LEFT JOIN users u ON si.user_id = u.id
    WHERE si.id = ?
");
$invoice->execute([$id]);
$inv = $invoice->fetch();
if (!$inv) { $_SESSION['error'] = 'الفاتورة غير موجودة'; redirect(APP_URL . '/modules/sales/invoices.php'); }

$items = $db->prepare("SELECT * FROM sale_invoice_items WHERE invoice_id = ?");
$items->execute([$id]);
$itemList = $items->fetchAll();

$payments = $db->prepare("SELECT * FROM customer_payments WHERE invoice_id = ? ORDER BY created_at DESC");
$payments->execute([$id]);
$payList = $payments->fetchAll();

$movements = $db->prepare("SELECT * FROM inventory_movements WHERE reference_type = 'sale_invoice' AND reference_id = ? ORDER BY created_at DESC");
$movements->execute([$id]);
$movList = $movements->fetchAll();

$page_title = 'فاتورة بيع ' . $inv['invoice_number'];
$payLabels = ['cash'=>'كاش','visa'=>'فيزا','credit'=>'آجل','pending'=>'معلقة','delivery'=>'توصيل منزلي'];
$statusLabels = ['open'=>'مفتوحة','paid'=>'مدفوعة','partial'=>'جزئية','cancelled'=>'ملغاة'];
$statusColors = ['open'=>'warning','paid'=>'success','partial'=>'info','cancelled'=>'danger'];
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#667eea;--secondary:#764ba2;--green:#198754;--red:#dc3545;}
body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto;min-width:1200px}
.top-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:10px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none}
.top-header .menu-item:hover{background:rgba(255,255,255,0.2);color:#fff}
.card-view{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin:20px;padding:25px}
.card-view h5{color:var(--primary);border-bottom:2px solid #e8eaf0;padding-bottom:10px;margin-bottom:20px}
.info-row{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:15px}
.info-item{flex:1;min-width:180px}
.info-item label{color:#666;font-size:12px;display:block}
.info-item strong{color:#333;font-size:14px}
.items-table{width:100%;border-collapse:collapse;font-size:13px}
.items-table th{background:var(--green);color:#fff;padding:8px;text-align:center}
.items-table td{padding:8px;text-align:center;border-bottom:1px solid #e9ecef}
.items-table tr:hover td{background:#e8f5e9}
.badge-status{padding:5px 12px;border-radius:20px;font-size:12px}
.grand-total{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border:2px solid var(--green);border-radius:10px;padding:15px;text-align:center}
.grand-total .val{color:var(--green);font-size:28px;font-weight:700}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-receipt"></i> <?= $page_title ?></span>
    <a href="invoices.php" class="menu-item"><i class="bi bi-arrow-right"></i> عودة للفواتير</a>
    <a href="create.php" class="menu-item"><i class="bi bi-plus-lg"></i> فاتورة جديدة</a>
    <a href="../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?></div>
</div>

<div class="container-fluid" style="padding:20px">
    <!-- Invoice Info -->
    <div class="card-view">
        <div class="row">
            <div class="col-md-8">
                <h5><i class="bi bi-file-earmark-text"></i> بيانات الفاتورة</h5>
                <div class="info-row">
                    <div class="info-item"><label>رقم الفاتورة</label><strong style="font-size:18px;color:var(--primary)"><?= $inv['invoice_number'] ?></strong></div>
                    <div class="info-item"><label>التاريخ</label><strong><?= $inv['invoice_date'] ?></strong></div>
                    <div class="info-item"><label>الحالة</label><span class="badge-status bg-<?= $statusColors[$inv['status']] ?>"><?= $statusLabels[$inv['status']] ?></span></div>
                    <div class="info-item"><label>نوع الدفع</label><strong><?= $payLabels[$inv['payment_method']] ?? $inv['payment_method'] ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-item"><label>العميل</label><strong><?= $inv['customer_name'] ?? 'نقدي' ?> <?= $inv['customer_code'] ? '('.$inv['customer_code'].')' : '' ?></strong></div>
                    <div class="info-item"><label>المخزن</label><strong><?= $inv['store_name'] ?? 'مخزن #'.$inv['store_id'] ?></strong></div>
                    <div class="info-item"><label>البائع</label><strong><?= $inv['user_name'] ?? '-' ?></strong></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="grand-total">
                    <div style="color:#666;font-size:13px">الصافي</div>
                    <div class="val"><?= number_format($inv['grand_total'], 2) ?> ج</div>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-6"><div class="p-2 bg-light rounded text-center"><div class="text-muted" style="font-size:11px">المدفوع</div><div class="text-success fw-bold"><?= number_format($inv['paid_amount'], 2) ?></div></div></div>
                    <div class="col-6"><div class="p-2 bg-light rounded text-center"><div class="text-muted" style="font-size:11px">المتبقي</div><div class="text-danger fw-bold"><?= number_format($inv['remaining_amount'], 2) ?></div></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items -->
    <div class="card-view">
        <h5><i class="bi bi-list-check"></i> الأصناف (<?= count($itemList) ?>)</h5>
        <div class="table-responsive">
            <table class="items-table">
                <thead>
                    <tr><th>#</th><th style="min-width:200px">الصنف</th><th>كود</th><th>الكمية</th><th>الوحدة</th><th>سعر البيع</th><th>خصم %</th><th>ق الخصم</th><th>ضريبة %</th><th>ق الضريبة</th><th>الإجمالي</th><th>الربح</th></tr>
                </thead>
                <tbody>
                    <?php foreach($itemList as $i=>$it): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td style="text-align:right;font-weight:600"><?= $it['product_name'] ?></td>
                        <td><?= $it['product_code'] ?? '-' ?></td>
                        <td><?= number_format($it['quantity'], 3) ?></td>
                        <td><?= $it['unit_name'] ?></td>
                        <td><?= number_format($it['sell_price'], 2) ?></td>
                        <td><?= $it['discount_pct'] ?>%</td>
                        <td><?= number_format($it['discount_val'], 2) ?></td>
                        <td><?= $it['vat_pct'] ?>%</td>
                        <td><?= number_format($it['vat_val'], 2) ?></td>
                        <td class="fw-bold text-primary"><?= number_format($it['line_total'], 2) ?></td>
                        <td class="text-success"><?= number_format($it['profit_val'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($itemList)): ?><tr><td colspan="12" class="text-center text-muted py-4">لا يوجد أصناف</td></tr><?php endif; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8f9fa;font-weight:700">
                        <td colspan="5" style="text-align:left">الإجماليات:</td>
                        <td colspan="2">الإجمالي: <?= number_format($inv['subtotal'], 2) ?></td>
                        <td><?= number_format(array_sum(array_column($itemList, 'discount_val')), 2) ?></td>
                        <td colspan="2">الضريبة: <?= number_format($inv['vat_amount'], 2) ?></td>
                        <td class="text-primary"><?= number_format($inv['grand_total'], 2) ?></td>
                        <td class="text-success"><?= number_format($inv['profit_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Payments -->
    <?php if(count($payList) > 0): ?>
    <div class="card-view">
        <h5><i class="bi bi-cash-stack"></i> الدفعات</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>#</th><th>رقم الدفعة</th><th>المبلغ</th><th>الطريقة</th><th>التاريخ</th></tr></thead>
                <tbody>
                    <?php foreach($payList as $i=>$p): ?>
                    <tr><td><?= $i+1 ?></td><td><?= $p['payment_number'] ?></td><td class="fw-bold text-success"><?= number_format($p['amount'], 2) ?></td><td><?= $p['payment_method'] ?></td><td><?= $p['payment_date'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Movements -->
    <?php if(count($movList) > 0): ?>
    <div class="card-view">
        <h5><i class="bi bi-arrow-left-right"></i> حركات المخزون</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>#</th><th>المنتج</th><th>النوع</th><th>الكمية</th><th>التكلفة</th><th>التاريخ</th></tr></thead>
                <tbody>
                    <?php foreach($movList as $i=>$m): ?>
                    <tr><td><?= $i+1 ?></td><td>منتج #<?= $m['product_id'] ?></td><td><?= $m['movement_type'] ?></td><td><?= number_format($m['quantity'], 3) ?></td><td><?= number_format($m['unit_cost'], 2) ?></td><td><?= $m['created_at'] ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-4">
        <a href="invoices.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> عودة</a>
        <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> طباعة</button>
        <a href="create.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> فاتورة جديدة</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>