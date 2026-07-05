<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$id = intval($_GET['id'] ?? 0);
if (!$id) { redirect(APP_URL . '/modules/purchases/returns/'); }

$return = $db->prepare("SELECT pr.*, s.supplier_name, s.supplier_code, st.store_name, u.user_name as created_by_name
    FROM purchase_returns pr
    LEFT JOIN suppliers s ON pr.supplier_id = s.id
    LEFT JOIN stores st ON pr.store_id = st.id
    LEFT JOIN users u ON pr.created_by = u.id
    WHERE pr.id = ?");
$return->execute([$id]);
$ret = $return->fetch();
if (!$ret) { $_SESSION['error'] = 'المرتجع غير موجود'; redirect(APP_URL . '/modules/purchases/returns/'); }

$items = $db->prepare("SELECT pri.*, p.product_code, p.barcode
    FROM purchase_return_items pri
    LEFT JOIN products p ON pri.product_id = p.id
    WHERE pri.return_id = ?");
$items->execute([$id]);
$retItems = $items->fetchAll();

$page_title = 'عرض مرتجع شراء ' . $ret['return_number'];
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
:root{--ret-red:#c0392b;}
*{box-sizing:border-box}
body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto;min-width:1200px}
.main-content{padding:0;margin-right:0 !important}
.top-header{background:var(--ret-red);color:#fff;padding:10px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none}
.top-header .menu-item:hover{background:rgba(255,255,255,0.2);color:#fff}
.card-view{background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin:20px;padding:25px}
.card-view h5{color:var(--ret-red);border-bottom:2px solid #fdeaea;padding-bottom:10px;margin-bottom:20px}
.info-row{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:15px}
.info-item{flex:1;min-width:200px}
.info-item label{color:#666;font-size:12px;display:block}
.info-item strong{color:#333;font-size:14px}
.items-table{width:100%;border-collapse:collapse;font-size:13px}
.items-table th{background:var(--ret-red);color:#fff;padding:8px;text-align:center}
.items-table td{padding:8px;text-align:center;border-bottom:1px solid #e9ecef}
.items-table tr:hover td{background:#fdeaea}
.badge-status{padding:5px 12px;border-radius:20px;font-size:12px}
.badge-open{background:#fff3cd;color:#856404}
.badge-closed{background:#d4edda;color:#155724}
.actions{display:flex;gap:10px;margin-top:20px}
@media print{.top-header,.actions{display:none!important}.card-view{box-shadow:none;margin:0}}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></span>
    <a href="./" class="menu-item"><i class="bi bi-arrow-right"></i> عودة للقائمة</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
    <div class="ms-auto" style="font-size:12px;opacity:.7"><?= $_SESSION['user_name'] ?? '' ?></div>
</div>

<div class="card-view">
    <div class="row">
        <div class="col-md-6">
            <h5><i class="bi bi-file-earmark-text"></i> بيانات المرتجع</h5>
            <div class="info-row">
                <div class="info-item">
                    <label>رقم المرتجع</label>
                    <strong style="font-size:18px;color:var(--ret-red)"><?= $ret['return_number'] ?></strong>
                </div>
                <div class="info-item">
                    <label>تاريخ المرتجع</label>
                    <strong><?= $ret['return_date'] ?></strong>
                </div>
                <div class="info-item">
                    <label>الحالة</label>
                    <span class="badge-status badge-<?= $ret['status'] ?? 'open' ?>"><?= ($ret['status'] ?? 'open') === 'open' ? 'مفتوح' : 'مغلق' ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item">
                    <label>المورد</label>
                    <strong><?= $ret['supplier_name'] ?> (<?= $ret['supplier_code'] ?>)</strong>
                </div>
                <div class="info-item">
                    <label>المخزن</label>
                    <strong><?= $ret['store_name'] ?? '-' ?></strong>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <h5><i class="bi bi-calculator"></i> ملخص المبالغ</h5>
            <div class="info-row">
                <div class="info-item">
                    <label>الإجمالي</label>
                    <strong style="font-size:18px;color:var(--ret-red)"><?= number_format($ret['grand_total'], 2) ?> ج</strong>
                </div>
            </div>
            <?php if ($ret['notes']): ?>
            <div class="info-row">
                <div class="info-item">
                    <label>ملاحظات</label>
                    <strong><?= nl2br(htmlspecialchars($ret['notes'])) ?></strong>
                </div>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <div class="info-item">
                    <label>أنشأه</label>
                    <strong><?= $ret['created_by_name'] ?? '-' ?> | <?= $ret['created_at'] ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-view">
    <h5><i class="bi bi-list-check"></i> الأصناف المرتجعة (<?= count($retItems) ?>)</h5>
    <div class="table-responsive">
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th style="min-width:200px">اسم الصنف</th>
                    <th>كود</th>
                    <th>باركود</th>
                    <th>الكمية</th>
                    <th>سعر الشراء</th>
                    <th>الإجمالي</th>
                    <th>السبب</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($retItems as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td style="text-align:right;font-weight:600"><?= $it['product_name'] ?></td>
                    <td><?= $it['product_code'] ?? '-' ?></td>
                    <td><?= $it['barcode'] ?? '-' ?></td>
                    <td><?= number_format($it['quantity'], 3) ?></td>
                    <td><?= number_format($it['unit_cost'], 2) ?></td>
                    <td style="font-weight:700;color:var(--ret-red)"><?= number_format($it['line_total'], 2) ?></td>
                    <td><?= $it['reason'] ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($retItems)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">لا يوجد أصناف</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background:#fdeaea;font-weight:700">
                    <td colspan="6" style="text-align:left">الإجمالي:</td>
                    <td style="color:var(--ret-red);font-size:16px"><?= number_format($ret['grand_total'], 2) ?> ج</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="actions" style="margin:0 20px 30px">
    <a href="./" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> عودة</a>
    <button onclick="window.print()" class="btn btn-outline-danger"><i class="bi bi-printer"></i> طباعة</button>
</div>
</body>
</html>