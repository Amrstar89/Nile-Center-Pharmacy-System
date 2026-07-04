<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'أوامر الشراء';

// Filters
$status = $_GET['status'] ?? '';
$supplier = $_GET['supplier'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = ['1=1']; $params = [];
if ($status) { $where[] = 'po.status = ?'; $params[] = $status; }
if ($supplier) { $where[] = 'po.supplier_id = ?'; $params[] = $supplier; }
if ($search) { $where[] = '(po.po_number LIKE ? OR s.supplier_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($date_from) { $where[] = 'po.order_date >= ?'; $params[] = $date_from; }
if ($date_to) { $where[] = 'po.order_date <= ?'; $params[] = $date_to; }

$sql = "SELECT po.*, s.supplier_name, s.supplier_code, s.phone, b.branch_name, u.full_name as creator,
        (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as items_count,
        (SELECT SUM(quantity) FROM purchase_order_items WHERE po_id = po.id) as total_qty,
        (SELECT SUM(received_qty) FROM purchase_order_items WHERE po_id = po.id) as total_received
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN branches b ON po.branch_id = b.id
        JOIN users u ON po.created_by = u.id
        WHERE " . implode(' AND ', $where) . " ORDER BY po.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$suppliers = $db->query("SELECT id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();

require_once __DIR__ . '/../../../includes/sidebar.php';

$colors_arr = ['draft'=>'secondary','sent'=>'info','partial'=>'warning','received'=>'success','cancelled'=>'danger'];
$labels_arr = ['draft'=>'مسودة','sent'=>'مرسل','partial'=>'جزئي','received'=>'مستلم','cancelled'=>'ملغي'];
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
.sec-card{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
.status-pill{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;color:white}
.progress-slim{height:6px;border-radius:3px}
@media(max-width:768px){.main-content{margin-right:0}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar">
        <div><h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> <?= $page_title ?></h5></div>
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> أمر شراء جديد</a>
    </div>

    <!-- Filters -->
    <div class="sec-card mb-3">
        <form method="GET" class="row g-2">
            <div class="col-md-2"><input type="text" name="search" class="form-control" placeholder="رقم/مورد..." value="<?= $search ?>"></div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">كل الحالات</option>
                    <option value="draft" <?= $status=='draft'?'selected':'' ?>>مسودة</option>
                    <option value="sent" <?= $status=='sent'?'selected':'' ?>>مرسلة</option>
                    <option value="partial" <?= $status=='partial'?'selected':'' ?>>استلام جزئي</option>
                    <option value="received" <?= $status=='received'?'selected':'' ?>>مستلم بالكامل</option>
                    <option value="cancelled" <?= $status=='cancelled'?'selected':'' ?>>ملغاة</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="supplier" class="form-select">
                    <option value="">كل الموردين</option>
                    <?php foreach($suppliers as $sup){ ?><option value="<?= $sup['id'] ?>" <?= $supplier==$sup['id']?'selected':'' ?>><?= $sup['supplier_name'] ?></option><?php } ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="<?= $date_from ?>"></div>
            <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="<?= $date_to ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button></div>
        </form>
    </div>

    <!-- Table -->
    <div class="sec-card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>رقم الأمر</th><th>المورد</th><th>التاريخ</th><th>الأصناف</th><th>التقدم</th><th>الإجمالي</th><th>الحالة</th><th>بواسطة</th><th></th></tr></thead>
                <tbody>
                <?php foreach($orders as $o){ 
                    $pct = $o['total_qty'] > 0 ? round(($o['total_received']/$o['total_qty'])*100) : 0;
                ?>
                <tr>
                    <td class="fw-bold"><?= $o['po_number'] ?></td>
                    <td><?= $o['supplier_name'] ?><br><small class="text-muted"><?= $o['supplier_code'] ?></small></td>
                    <td><?= arabicDate($o['order_date']) ?><br><small class="text-muted">متوقع: <?= $o['expected_date'] ? arabicDate($o['expected_date']) : '-' ?></small></td>
                    <td><?= $o['items_count'] ?> صنف<br><small class="text-muted"><?= $o['total_qty'] ?> وحدة</small></td>
                    <td style="min-width:120px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-fill progress-slim"><div class="progress-bar bg-<?= $colors_arr[$o['status']] ?>" style="width:<?= $pct ?>%"></div></div>
                            <small class="text-muted"><?= $pct ?>%</small>
                        </div>
                    </td>
                    <td class="fw-bold"><?= number_format($o['grand_total'],2) ?> ج</td>
                    <td><span class="status-pill bg-<?= $colors_arr[$o['status']] ?>"><?= $labels_arr[$o['status']] ?></span></td>
                    <td><?= $o['creator'] ?></td>
                    <td><a href="view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
