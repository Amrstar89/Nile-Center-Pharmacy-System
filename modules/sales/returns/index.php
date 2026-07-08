<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'مرتجعات المبيعات';

$returns = $db->query("
    SELECT sr.*, c.customer_name, si.invoice_number as orig_invoice, s.store_name
    FROM sale_returns sr
    LEFT JOIN customers c ON sr.customer_id = c.id
    LEFT JOIN sale_invoices si ON sr.invoice_id = si.id
    LEFT JOIN stores s ON sr.store_id = s.id
    ORDER BY sr.created_at DESC
    LIMIT 100
")->fetchAll();

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
:root{--ret-red:#c0392b;--primary:#667eea;}
body{background:#f8f9fa;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;margin:0;padding:0;overflow-y:auto}
.top-header{background:var(--ret-red);color:#fff;padding:10px 20px;display:flex;align-items:center;gap:20px;position:sticky;top:0;z-index:100}
.top-header .menu-item{color:rgba(255,255,255,0.8);padding:6px 14px;border-radius:6px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none}
.top-header .menu-item:hover{background:rgba(255,255,255,0.2);color:#fff}
.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table th{background:#f8f9fa;padding:10px;text-align:center;font-weight:600;border-bottom:2px solid #dee2e6}
.data-table td{padding:8px;text-align:center;border-bottom:1px solid #e9ecef}
</style>
</head>
<body>
<div class="top-header">
    <span style="font-weight:700"><i class="bi bi-arrow-return-left"></i> <?= $page_title ?></span>
    <a href="create.php" class="menu-item"><i class="bi bi-plus-lg"></i> مرتجع جديد</a>
    <a href="../invoices.php" class="menu-item"><i class="bi bi-receipt"></i> فواتير البيع</a>
    <a href="../../dashboard/" class="menu-item"><i class="bi bi-speedometer2"></i> الرئيسية</a>
</div>
<div class="container-fluid" style="padding:20px">
    <div class="card"><div class="card-body p-0">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>رقم المرتجع</th><th>التاريخ</th><th>العميل</th><th>الفاتورة الأصلية</th><th>المخزن</th><th>الإجمالي</th><th>الحالة</th></tr>
                </thead>
                <tbody>
                    <?php foreach($returns as $i=>$r): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td class="fw-bold text-danger"><?= $r['return_number'] ?></td>
                        <td><?= $r['return_date'] ?></td>
                        <td><?= $r['customer_name'] ?? '-' ?></td>
                        <td><?= $r['orig_invoice'] ?? 'فاتورة #'.$r['invoice_id'] ?></td>
                        <td><?= $r['store_name'] ?? 'مخزن #'.$r['store_id'] ?></td>
                        <td class="fw-bold text-danger"><?= number_format($r['grand_total'], 2) ?></td>
                        <td><span class="badge bg-<?= $r['status']==='open'?'warning':'success' ?>"><?= $r['status']==='open'?'مفتوح':'مغلق' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($returns)): ?><tr><td colspan="8" class="text-center text-muted py-5">لا توجد مرتجعات</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>