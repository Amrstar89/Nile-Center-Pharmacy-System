<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'فواتير الشراء';

$status = $_GET['status'] ?? '';
$supplier = $_GET['supplier'] ?? '';
$search = $_GET['search'] ?? '';

$where = ['1=1']; $params = [];
if ($status) { $where[] = 'pi.status = ?'; $params[] = $status; }
if ($supplier) { $where[] = 'pi.supplier_id = ?'; $params[] = $supplier; }
if ($search) { $where[] = '(pi.invoice_number LIKE ? OR s.supplier_name LIKE ? OR pi.supplier_invoice_no LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sql = "SELECT pi.*, s.supplier_name, s.supplier_code, po.po_number, u.full_name as creator,
        (SELECT COUNT(*) FROM purchase_invoice_items WHERE invoice_id = pi.id) as items_count
        FROM purchase_invoices pi
        JOIN suppliers s ON pi.supplier_id = s.id
        LEFT JOIN purchase_orders po ON pi.po_id = po.id
        JOIN users u ON pi.created_by = u.id
        WHERE " . implode(' AND ', $where) . " ORDER BY pi.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$suppliers = $db->query("SELECT id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();

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
.sec-card{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
.status-pill{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;color:white}
.due-badge{background:#f8d7da;color:#721c24;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
@media(max-width:768px){.main-content{margin-right:0}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar">
        <div><h5 class="mb-0"><i class="bi bi-receipt"></i> <?= $page_title ?></h5></div>
        <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> فاتورة شراء جديدة</a>
    </div>

    <div class="sec-card mb-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="رقم/مورد/فاتورة المورد..." value="<?= $search ?>"></div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">كل الحالات</option>
                    <option value="open" <?= $status=='open'?'selected':'' ?>>مفتوحة</option>
                    <option value="partial" <?= $status=='partial'?'selected':'' ?>>جزئي</option>
                    <option value="paid" <?= $status=='paid'?'selected':'' ?>>مسددة</option>
                    <option value="cancelled" <?= $status=='cancelled'?'selected':'' ?>>ملغية</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="supplier" class="form-select"><option value="">كل الموردين</option>
                    <?php foreach($suppliers as $sup): ?><option value="<?= $sup['id'] ?>" <?= $supplier==$sup['id']?'selected':'' ?>><?= $sup['supplier_name'] ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
        </form>
    </div>

    <div class="sec-card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>الفاتورة</th><th>المورد</th><th>التاريخ</th><th>الأصناف</th><th>الإجمالي</th><th>المسدد</th><th>المتبقي</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                <?php foreach($invoices as $inv):
                    $icolors=['open'=>'warning','partial'=>'info','paid'=>'success','cancelled'=>'danger'];
                    $ilabels=['open'=>'مفتوحة','partial'=>'جزئي','paid'=>'مسددة','cancelled'=>'ملغية'];
                ?>
                <tr>
                    <td class="fw-bold"><?= $inv['invoice_number'] ?>
                        <?php if($inv['po_number']): ?><br><small class="text-muted"><?= $inv['po_number'] ?></small><?php endif; ?>
                        <?php if($inv['supplier_invoice_no']): ?><br><small class="text-muted">ف.المورد: <?= $inv['supplier_invoice_no'] ?></small><?php endif; ?>
                    </td>
                    <td><?= $inv['supplier_name'] ?><br><small class="text-muted"><?= $inv['supplier_code'] ?></small></td>
                    <td><?= arabicDate($inv['invoice_date']) ?><br><small class="text-muted">استحقاق: <?= $inv['due_date'] ? arabicDate($inv['due_date']) : '-' ?></small></td>
                    <td><?= $inv['items_count'] ?> صنف</td>
                    <td class="fw-bold"><?= number_format($inv['grand_total'],2) ?> ج</td>
                    <td><?= number_format($inv['paid_amount'],2) ?> ج</td>
                    <td><?php if($inv['remaining_amount']>0): ?><span class="due-badge"><?= number_format($inv['remaining_amount'],2) ?> ج</span><?php else: ?>-<?php endif; ?></td>
                    <td><span class="status-pill bg-<?= $icolors[$inv['status']] ?>"><?= $ilabels[$inv['status']] ?></span></td>
                    <td><a href="view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
