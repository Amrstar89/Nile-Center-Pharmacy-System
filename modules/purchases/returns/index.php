<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();
$db = getDB();
$page_title = 'مرتجعات المشتريات';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$search = trim($_GET['search'] ?? '');
$supplier_filter = intval($_GET['supplier_id'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where = []; $params = [];
if ($search) { $where[] = '(r.return_number LIKE ? OR r.notes LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($supplier_filter) { $where[] = 'r.supplier_id = ?'; $params[] = $supplier_filter; }
if ($date_from) { $where[] = 'r.return_date >= ?'; $params[] = $date_from; }
if ($date_to) { $where[] = 'r.return_date <= ?'; $params[] = $date_to; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM purchase_returns r $whereSQL");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Data
$stmt = $db->prepare("SELECT r.*, s.supplier_name, s.supplier_code, st.store_name, u.user_name as creator_name, 
    (SELECT COUNT(*) FROM purchase_return_items WHERE return_id = r.id) as item_count 
    FROM purchase_returns r 
    LEFT JOIN suppliers s ON r.supplier_id = s.id 
    LEFT JOIN stores st ON r.store_id = st.id 
    LEFT JOIN users u ON r.created_by = u.id 
    $whereSQL ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$returns = $stmt->fetchAll();

$suppliers = $db->query("SELECT id, supplier_name FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();

require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--ret-red:#c0392b;--sidebar-bg:#1a1a2e;}
*{box-sizing:border-box}body{background:#e8eaf0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto}
.top-header{background:var(--ret-red);color:#fff;padding:10px 20px;display:flex;align-items:center;gap:15px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.85);padding:6px 14px;border-radius:6px;text-decoration:none;font-size:13px;transition:all .2s;white-space:nowrap}
.top-header .menu-item:hover{background:rgba(255,255,255,0.2);color:#fff}
.main-content{padding:20px;margin-right:60px !important}
.filter-bar{background:#fff;padding:15px 20px;border-radius:10px;margin-bottom:15px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.table-card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.1);overflow:hidden}
.table-card th{background:var(--ret-red);color:#fff;font-size:12px;font-weight:600;text-align:center;white-space:nowrap}
.table-card td{font-size:13px;text-align:center;vertical-align:middle}
.table-card tr:hover td{background:#fdeaea}
.badge-ret{background:var(--ret-red);color:#fff;font-size:11px;padding:4px 10px;border-radius:20px}
.badge-direct{background:#e67e22;color:#fff;font-size:11px;padding:4px 10px;border-radius:20px}
.btn-create{background:linear-gradient(135deg,#e74c3c,var(--ret-red));color:#fff;border:none;padding:8px 20px;border-radius:8px;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.btn-create:hover{color:#fff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(192,57,43,0.3)}
.page-link{color:var(--ret-red)}
.page-item.active .page-link{background:var(--ret-red);border-color:var(--ret-red)}
.stat-box{background:#fff;border-radius:10px;padding:15px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
.stat-box i{font-size:28px;color:var(--ret-red);margin-bottom:8px}
.stat-box h5{margin:0;font-size:20px;font-weight:700;color:var(--ret-red)}
.stat-box small{color:#666;font-size:12px}
@media(max-width:768px){.main-content{margin-right:0 !important}}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></span>
    <a href="../" class="menu-item"><i class="bi bi-arrow-right"></i> المشتريات</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?> | <?= arabicDate(date('Y-m-d')) ?></div>
</div>
<div class="main-content">
    <!-- Stats -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="stat-box">
                <i class="bi bi-arrow-return-left"></i>
                <h5><?= number_format($totalRows) ?></h5>
                <small>إجمالي المرتجعات</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <i class="bi bi-calendar-week"></i>
                <h5><?= number_format($db->query("SELECT COUNT(*) FROM purchase_returns WHERE return_date = CURDATE()")->fetchColumn()) ?></h5>
                <small>مرتجعات اليوم</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <i class="bi bi-cash-stack"></i>
                <h5><?= number_format($db->query("SELECT COALESCE(SUM(grand_total),0) FROM purchase_returns WHERE return_date = CURDATE()")->fetchColumn(), 2) ?></h5>
                <small>قيمة مرتجعات اليوم</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-box">
                <i class="bi bi-receipt"></i>
                <h5><?= number_format($db->query("SELECT COUNT(*) FROM purchase_returns WHERE invoice_id IS NOT NULL")->fetchColumn()) ?></h5>
                <small>مرتجعات بفاتورة</small>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="d-flex gap-2 mb-3">
        <a href="create.php" class="btn-create"><i class="bi bi-plus-lg"></i> مرتجع بفاتورة</a>
        <a href="create_direct.php" class="btn-create" style="background:linear-gradient(135deg,#e67e22,#d35400)"><i class="bi bi-plus-lg"></i> مرتجع بدون فاتورة</a>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">بحث</label>
                <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="رقم المرتجع...">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">المورد</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <?php foreach($suppliers as $s){ ?><option value="<?= $s['id'] ?>" <?= $supplier_filter==$s['id']?'selected':'' ?>><?= $s['supplier_name'] ?></option><?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">من</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">إلى</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $date_to ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-danger w-100"><i class="bi bi-search"></i> بحث</button>
            </div>
            <?php if($search||$supplier_filter||$date_from||$date_to){ ?>
            <div class="col-md-1">
                <a href="./" class="btn btn-sm btn-outline-secondary w-100">مسح</a>
            </div>
            <?php } ?>
        </form>
    </div>

    <!-- Table -->
    <div class="table-card">
        <table class="table table-striped table-hover mb-0">
            <thead><tr>
                <th>#</th>
                <th>رقم المرتجع</th>
                <th>النوع</th>
                <th>المورد</th>
                <th>المخزن</th>
                <th>تاريخ المرتجع</th>
                <th>الأصناف</th>
                <th>الإجمالي</th>
                <th>ملاحظات</th>
                <th>بواسطة</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php if(empty($returns)){ ?>
                <tr><td colspan="11" class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:48px"></i><br>لا توجد مرتجعات</td></tr>
            <?php } else { foreach($returns as $r){ ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><strong class="text-danger"><?= $r['return_number'] ?></strong></td>
                    <td><?= $r['invoice_id'] ? '<span class="badge-ret">بفاتورة</span>' : '<span class="badge-direct">مباشر</span>' ?></td>
                    <td><?= $r['supplier_name'] ?? '-' ?></td>
                    <td><?= $r['store_name'] ?? '-' ?></td>
                    <td><?= arabicDate($r['return_date']) ?></td>
                    <td><?= $r['item_count'] ?></td>
                    <td><strong><?= number_format($r['grand_total'],2) ?></strong> ج</td>
                    <td><?= $r['notes'] ? '<i class="bi bi-chat-square-text text-muted" title="'.htmlspecialchars($r['notes']).'"></i>' : '-' ?></td>
                    <td><small class="text-muted"><?= $r['creator_name'] ?? '' ?></small></td>
                    <td>
                        <a href="view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            <?php }} ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if($totalPages>1){ ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php for($i=1;$i<=$totalPages;$i++){ ?>
            <li class="page-item <?= $i==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&supplier_id=<?= $supplier_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
            </li>
            <?php } ?>
        </ul>
    </nav>
    <?php } ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>