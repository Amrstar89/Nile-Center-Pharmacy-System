<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'فواتير البيع';

$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment_method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

$where = ['1=1']; $params = [];
if ($status_filter) { $where[] = 'si.status = ?'; $params[] = $status_filter; }
if ($payment_filter) { $where[] = 'si.payment_method = ?'; $params[] = $payment_filter; }
if ($date_from) { $where[] = 'si.invoice_date >= ?'; $params[] = $date_from; }
if ($date_to) { $where[] = 'si.invoice_date <= ?'; $params[] = $date_to; }
if ($search) { $where[] = '(si.invoice_number LIKE ? OR c.customer_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSql = implode(' AND ', $where);

$totalInv = $db->query("SELECT COUNT(*) FROM sale_invoices WHERE $whereSql", $params)->fetchColumn();
$totalAmount = $db->query("SELECT COALESCE(SUM(grand_total),0) FROM sale_invoices WHERE $whereSql", $params)->fetchColumn();
$totalPaid = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM sale_invoices WHERE $whereSql", $params)->fetchColumn();

$invoices = $db->prepare("
    SELECT si.*, c.customer_name, c.customer_code, s.store_name, u.full_name as user_name
    FROM sale_invoices si
    LEFT JOIN customers c ON si.customer_id = c.id
    LEFT JOIN stores s ON si.store_id = s.id
    LEFT JOIN users u ON si.user_id = u.id
    WHERE $whereSql
    ORDER BY si.created_at DESC
    LIMIT 100
");
$invoices->execute($params);
$list = $invoices->fetchAll();

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
:root{--primary:#667eea;--secondary:#764ba2;--green:#198754;}
body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto}
.top-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:10px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none}
.top-header .menu-item:hover{background:rgba(255,255,255,0.2);color:#fff}
.stat-card{background:#fff;border-radius:12px;padding:15px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.stat-card i{font-size:22px;margin-bottom:6px}
.stat-card .val{font-size:20px;font-weight:700}
.stat-card .lbl{font-size:11px;color:#888}
.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table th{background:#f8f9fa;padding:10px;text-align:center;font-weight:600;border-bottom:2px solid #dee2e6}
.data-table td{padding:8px 10px;text-align:center;border-bottom:1px solid #e9ecef}
.data-table tr:hover td{background:#f8f9fa}
.badge-status{padding:4px 10px;border-radius:20px;font-size:11px}
.filter-bar{background:#fff;padding:15px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:20px}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-cart3"></i> <?= $page_title ?></span>
    <a href="./create.php" class="menu-item"><i class="bi bi-plus-lg"></i> فاتورة جديدة</a>
    <a href="./returns/" class="menu-item"><i class="bi bi-arrow-return-left"></i> المرتجعات</a>
    <a href="../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?></div>
</div>
<div class="container-fluid" style="padding:20px">
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-card"><i class="bi bi-receipt text-primary"></i><div class="val"><?= number_format($totalInv) ?></div><div class="lbl">الفواتير</div></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="bi bi-cash-stack text-success"></i><div class="val text-success"><?= number_format($totalAmount,2) ?></div><div class="lbl">الإجمالي</div></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="bi bi-check-circle text-info"></i><div class="val text-info"><?= number_format($totalPaid,2) ?></div><div class="lbl">المدفوع</div></div></div>
        <div class="col-md-3"><div class="stat-card"><i class="bi bi-wallet text-danger"></i><div class="val text-danger"><?= number_format($totalAmount-$totalPaid,2) ?></div><div class="lbl">المتبقي</div></div></div>
    </div>
    <div class="filter-bar">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="رقم أو عميل..." value="<?= htmlspecialchars($search) ?>"></div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm"><option value="">الحالة</option>
                    <?php foreach($statusLabels as $k=>$v){ ?><option value="<?= $k ?>" <?= $status_filter==$k?'selected':'' ?>><?= $v ?></option><?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_method" class="form-select form-select-sm"><option value="">النوع</option>
                    <?php foreach($payLabels as $k=>$v){ ?><option value="<?= $k ?>" <?= $payment_filter==$k?'selected':'' ?>><?= $v ?></option><?php } ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= $date_from ?>"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= $date_to ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> تصفية</button></div>
        </form>
    </div>
    <div class="card"><div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>#</th><th>الرقم</th><th>التاريخ</th><th>العميل</th><th>المخزن</th><th>البائع</th><th>النوع</th><th>الحالة</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
                <tbody>
                    <?php foreach($list as $i=>$inv): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><a href="view.php?id=<?= $inv['id'] ?>" class="fw-bold text-primary"><?= $inv['invoice_number'] ?></a></td>
                        <td><?= $inv['invoice_date'] ?></td>
                        <td><?= $inv['customer_name'] ?? 'نقدي' ?></td>
                        <td><?= $inv['store_name'] ?? 'مخزن #'.$inv['store_id'] ?></td>
                        <td><?= $inv['user_name'] ?? '-' ?></td>
                        <td><?= $payLabels[$inv['payment_method']] ?? $inv['payment_method'] ?></td>
                        <td><span class="badge-status bg-<?= $statusColors[$inv['status']] ?? 'secondary' ?>"><?= $statusLabels[$inv['status']] ?? $inv['status'] ?></span></td>
                        <td class="fw-bold"><?= number_format($inv['grand_total'],2) ?></td>
                        <td class="text-success"><?= number_format($inv['paid_amount'],2) ?></td>
                        <td class="text-danger"><?= number_format($inv['remaining_amount'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($list)): ?><tr><td colspan="11" class="text-center text-muted py-5">لا توجد فواتير</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>