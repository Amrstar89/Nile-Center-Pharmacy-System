<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'تقرير المشتريات';

// Summary stats
$totalPurchases = $db->query("SELECT COALESCE(SUM(grand_total),0) as total, COUNT(*) as count FROM purchase_invoices WHERE status != 'cancelled'")->fetch();
$totalPOs = $db->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status != 'cancelled'")->fetch();
$totalReturns = $db->query("SELECT COALESCE(SUM(grand_total),0) as total, COUNT(*) as count FROM purchase_returns WHERE status != 'cancelled'")->fetch();
$totalDues = $db->query("SELECT COALESCE(SUM(remaining_amount),0) as total FROM purchase_invoices WHERE status IN ('open','partial')")->fetch();

// Top suppliers
$topSuppliers = $db->query("
    SELECT s.supplier_name, s.supplier_code, COUNT(pi.id) as inv_count, SUM(pi.grand_total) as total_purchases
    FROM suppliers s JOIN purchase_invoices pi ON s.id = pi.supplier_id
    WHERE pi.status != 'cancelled' GROUP BY s.id ORDER BY total_purchases DESC LIMIT 8
")->fetchAll();

// Monthly chart
$monthStart = date('Y-m-01');
$monthData = $db->query("
    SELECT DATE(invoice_date) as d, SUM(grand_total) as total FROM purchase_invoices
    WHERE invoice_date >= '$monthStart' AND status != 'cancelled' GROUP BY DATE(invoice_date) ORDER BY d
")->fetchAll();

// Recent invoices for report
$recentInv = $db->query("
    SELECT pi.*, s.supplier_name FROM purchase_invoices pi
    JOIN suppliers s ON pi.supplier_id = s.id WHERE pi.status != 'cancelled' ORDER BY pi.created_at DESC LIMIT 15
")->fetchAll();

require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title><?= $page_title ?> - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--primary:#667eea;--secondary:#764ba2}
body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
.main-content{margin-right:260px;padding:20px}
.topbar{background:white;border-radius:15px;padding:15px 25px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center}
.sec-card{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px}
.stat-box{background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:12px;padding:20px;text-align:center;margin-bottom:15px}
.stat-box h3{font-size:28px;font-weight:700}
@media(max-width:768px){.main-content{margin-right:0}}
@media print{.sidebar,.topbar,.no-print{display:none!important}.main-content{margin-right:0!important}}
</style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar no-print">
        <div><h5 class="mb-0"><i class="bi bi-graph-up"></i> <?= $page_title ?></h5></div>
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> طباعة</button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-box"><h3><?= number_format($totalPurchases['total'],0) ?> ج</h3><p>إجمالي المشتريات</p></div></div>
        <div class="col-md-3"><div class="stat-box" style="background:linear-gradient(135deg,#198754,#20c997)"><h3><?= $totalPurchases['count'] ?></h3><p>فواتير الشراء</p></div></div>
        <div class="col-md-3"><div class="stat-box" style="background:linear-gradient(135deg,#dc3545,#f44336)"><h3><?= number_format($totalReturns['total'],0) ?> ج</h3><p>إجمالي المرتجعات</p></div></div>
        <div class="col-md-3"><div class="stat-box" style="background:linear-gradient(135deg,#ffc107,#ff9800)"><h3><?= number_format($totalDues['total'],0) ?> ج</h3><p>المستحقات للموردين</p></div></div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="sec-card">
                <h6 class="mb-3"><i class="bi bi-graph-up"></i> مشتريات الشهر الحالي</h6>
                <canvas id="chart" height="100"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="sec-card">
                <h6 class="mb-3"><i class="bi bi-trophy"></i> أكثر الموردين شراءً</h6>
                <?php foreach($topSuppliers as $ts): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div><strong><?= $ts['supplier_name'] ?></strong><br><small class="text-muted"><?= $ts['inv_count'] ?> فاتورة</small></div>
                    <span class="fw-bold text-primary"><?= number_format($ts['total_purchases'],0) ?> ج</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="sec-card">
        <h6 class="mb-3"><i class="bi bi-receipt"></i> أحدث فواتير الشراء</h6>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>الفاتورة</th><th>المورد</th><th>التاريخ</th><th>الإجمالي</th><th>المسدد</th><th>المتبقي</th><th>الحالة</th></tr></thead>
                <tbody>
                <?php foreach($recentInv as $ri):
                    $colors=['open'=>'warning','partial'=>'info','paid'=>'success','cancelled'=>'danger'];
                ?>
                <tr>
                    <td><?= $ri['invoice_number'] ?></td>
                    <td><?= $ri['supplier_name'] ?></td>
                    <td><?= arabicDate($ri['invoice_date']) ?></td>
                    <td class="fw-bold"><?= number_format($ri['grand_total'],2) ?> ج</td>
                    <td><?= number_format($ri['paid_amount'],2) ?> ج</td>
                    <td class="text-<?= $ri['remaining_amount']>0?'danger':'success' ?>"><?= number_format($ri['remaining_amount'],2) ?> ج</td>
                    <td><span class="badge bg-<?= $colors[$ri['status']] ?>"><?= $ri['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx=document.getElementById('chart').getContext('2d');
const data=<?= json_encode($monthData) ?>;
new Chart(ctx,{type:'bar',data:{labels:data.map(d=>d.d.substring(8)+'/'+d.d.substring(5,7)),datasets:[{label:'مشتريات (ج)',data:data.map(d=>d.total),backgroundColor:'rgba(102,126,234,0.7)',borderColor:'#667eea',borderWidth:1,borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.05)'}},x:{grid:{display:false}}}});
</script>
</body>
</html>
