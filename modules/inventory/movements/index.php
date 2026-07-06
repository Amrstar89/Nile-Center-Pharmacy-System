<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'حركات المخزون';

// Filters
$store_filter = intval($_GET['store_id'] ?? 0);
$product_filter = intval($_GET['product_id'] ?? 0);
$type_filter = $_GET['movement_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where = ['1=1'];
$params = [];
if ($store_filter) { $where[] = 'im.store_id = ?'; $params[] = $store_filter; }
if ($product_filter) { $where[] = 'im.product_id = ?'; $params[] = $product_filter; }
if ($type_filter) { $where[] = 'im.movement_type = ?'; $params[] = $type_filter; }
if ($date_from) { $where[] = 'im.created_at >= ?'; $params[] = $date_from . ' 00:00:00'; }
if ($date_to) { $where[] = 'im.created_at <= ?'; $params[] = $date_to . ' 23:59:59'; }
$whereSql = implode(' AND ', $where);

// Stats
$totalMovements = $db->query("SELECT COUNT(*) FROM inventory_movements WHERE $whereSql", $params)->fetchColumn();
$purchaseIn = $db->query("SELECT COALESCE(SUM(quantity),0) FROM inventory_movements WHERE movement_type='purchase' AND $whereSql", $params)->fetchColumn();
$returnOut = $db->query("SELECT COALESCE(SUM(quantity),0) FROM inventory_movements WHERE movement_type='purchase_return' AND $whereSql", $params)->fetchColumn();

// Get movements with joins
$movements = $db->prepare("
    SELECT im.*, s.store_name, p.product_name, p.product_code
    FROM inventory_movements im
    LEFT JOIN stores s ON im.store_id = s.id
    LEFT JOIN products p ON im.product_id = p.id
    WHERE $whereSql
    ORDER BY im.created_at DESC
    LIMIT 200
");
$movements->execute($params);
$list = $movements->fetchAll();

// Dropdowns
$stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();
$products = $db->query("SELECT id, product_name FROM products WHERE is_active = 1 ORDER BY product_name LIMIT 100")->fetchAll();

$typeLabels = [
    'purchase' => ['شراء', 'bi-receipt', 'text-success', '+'],
    'purchase_return' => ['مرتجع شراء', 'bi-arrow-return-left', 'text-danger', '-'],
    'sale' => ['بيع', 'bi-cart', 'text-primary', '-'],
    'sale_return' => ['مرتجع بيع', 'bi-arrow-return-right', 'text-warning', '+'],
    'transfer_in' => ['تحويل وارد', 'bi-arrow-down', 'text-info', '+'],
    'transfer_out' => ['تحويل صادر', 'bi-arrow-up', 'text-secondary', '-'],
    'adjustment' => ['تسوية', 'bi-sliders', 'text-dark', ''],
    'opening_balance' => ['رصيد افتتاحي', 'bi-box', 'text-muted', '+']
];

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
:root{--primary:#667eea;--secondary:#764ba2;}
*{box-sizing:border-box}
body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto;min-width:1200px}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;padding:10px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none}
.top-header .menu-item:hover{background:rgba(255,255,255,0.2);color:#fff}
.stat-card{background:#fff;border-radius:12px;padding:20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.stat-card i{font-size:24px;margin-bottom:8px}
.stat-card .val{font-size:22px;font-weight:700}
.stat-card .lbl{font-size:12px;color:#888}
.filter-bar{background:#fff;padding:15px 20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:20px}
.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table th{background:#f8f9fa;padding:10px;text-align:center;font-weight:600;border-bottom:2px solid #dee2e6;position:sticky;top:0;z-index:10}
.data-table td{padding:8px 10px;text-align:center;border-bottom:1px solid #e9ecef}
.data-table tr:hover td{background:#f8f9fa}
.movement-in{color:var(--bs-success);font-weight:700}
.movement-out{color:var(--bs-danger);font-weight:700}
.badge-ref{padding:3px 8px;border-radius:12px;font-size:11px;background:#e9ecef}
@media(max-width:768px){body{min-width:auto}}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-left-right"></i> <?= $page_title ?></span>
    <a href="../stores/" class="menu-item"><i class="bi bi-building"></i> المخازن</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?></div>
</div>

<div class="container-fluid" style="padding:20px">
    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="bi bi-arrow-left-right text-primary"></i>
                <div class="val"><?= number_format($totalMovements) ?></div>
                <div class="lbl">إجمالي الحركات</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="bi bi-receipt text-success"></i>
                <div class="val text-success"><?= number_format($purchaseIn, 3) ?></div>
                <div class="lbl">وارد شراء</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="bi bi-arrow-return-left text-danger"></i>
                <div class="val text-danger"><?= number_format($returnOut, 3) ?></div>
                <div class="lbl">صادر مرتجع</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="bi bi-calculator text-info"></i>
                <div class="val text-info"><?= number_format($purchaseIn - $returnOut, 3) ?></div>
                <div class="lbl">الرصيد الصافي</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted">المخزن</label>
                <select name="store_id" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <?php foreach($stores as $st){ ?><option value="<?= $st['id'] ?>" <?= $store_filter==$st['id']?'selected':'' ?>><?= $st['store_name'] ?></option><?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">الصنف</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <?php foreach($products as $pr){ ?><option value="<?= $pr['id'] ?>" <?= $product_filter==$pr['id']?'selected':'' ?>><?= $pr['product_name'] ?></option><?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">نوع الحركة</label>
                <select name="movement_type" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <?php foreach($typeLabels as $key=>$tl){ ?><option value="<?= $key ?>" <?= $type_filter==$key?'selected':'' ?>><?= $tl[0] ?></option><?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">من تاريخ</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $date_to ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> تصفية</button>
                <a href="./" class="btn btn-outline-secondary btn-sm w-100 mt-1"><i class="bi bi-x"></i> مسح</a>
            </div>
        </form>
    </div>

    <!-- Movements Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height:600px;overflow-y:auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>التاريخ</th>
                            <th>المخزن</th>
                            <th>الصنف</th>
                            <th>نوع الحركة</th>
                            <th>المرجع</th>
                            <th>الكمية</th>
                            <th>التكلفة</th>
                            <th>الإجمالي</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($list as $i=>$m):
                            $tl = $typeLabels[$m['movement_type']] ?? ['حركة', 'bi-arrow-left-right', 'text-dark', ''];
                            $isIn = in_array($m['movement_type'], ['purchase','sale_return','transfer_in','opening_balance']);
                        ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td style="font-size:12px"><?= $m['created_at'] ?></td>
                            <td><?= $m['store_name'] ?? 'مخزن #'.$m['store_id'] ?></td>
                            <td style="text-align:right;font-weight:600"><?= $m['product_name'] ?? 'صنف #'.$m['product_id'] ?></td>
                            <td><i class="bi <?= $tl[1] ?> <?= $tl[2] ?>"></i> <?= $tl[0] ?></td>
                            <td><?php if($m['reference_number']): ?><span class="badge-ref"><?= $m['reference_number'] ?></span><?php else: ?>-<?php endif; ?></td>
                            <td class="<?= $isIn ? 'movement-in' : 'movement-out' ?>"><?= $tl[3] ?><?= number_format($m['quantity'], 3) ?></td>
                            <td><?= number_format($m['unit_cost'], 2) ?></td>
                            <td class="fw-bold"><?= number_format($m['total_cost'], 2) ?></td>
                            <td style="font-size:11px"><?= $m['notes'] ?: '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($list)): ?><tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-inbox" style="font-size:48px"></i><br>لا توجد حركات</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>