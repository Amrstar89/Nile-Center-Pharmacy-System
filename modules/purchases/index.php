<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'إدارة المشتريات';

// Quick Stats
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// PO Stats
$poStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status='partial' THEN 1 ELSE 0 END) as partial,
        SUM(CASE WHEN status='received' THEN 1 ELSE 0 END) as received,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
        COALESCE(SUM(CASE WHEN status!='cancelled' AND status!='received' THEN grand_total ELSE 0 END), 0) as pending_value
    FROM purchase_orders
")->fetch();

// Invoice Stats
$invStats = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status='partial' THEN 1 ELSE 0 END) as partial,
        SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid,
        COALESCE(SUM(CASE WHEN status IN ('open','partial') THEN remaining_amount ELSE 0 END), 0) as total_dues,
        COALESCE(SUM(CASE WHEN invoice_date='$today' THEN grand_total ELSE 0 END), 0) as today_total
    FROM purchase_invoices
")->fetch();

// Monthly purchase chart data
$monthData = $db->query("
    SELECT DATE(invoice_date) as d, SUM(grand_total) as total, COUNT(*) as cnt
    FROM purchase_invoices
    WHERE invoice_date >= '$monthStart' AND status != 'cancelled'
    GROUP BY DATE(invoice_date) ORDER BY d
")->fetchAll();

// Recent POs
$recentPOs = $db->query("
    SELECT po.*, s.supplier_name, s.supplier_code, u.full_name as creator
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    JOIN users u ON po.created_by = u.id
    ORDER BY po.created_at DESC LIMIT 8
")->fetchAll();

// Recent Invoices
$recentInvs = $db->query("
    SELECT pi.*, s.supplier_name, s.supplier_code, u.full_name as creator
    FROM purchase_invoices pi
    JOIN suppliers s ON pi.supplier_id = s.id
    JOIN users u ON pi.created_by = u.id
    ORDER BY pi.created_at DESC LIMIT 8
")->fetchAll();

// Suppliers with dues
$supplierDues = $db->query("
    SELECT s.id, s.supplier_name, s.supplier_code, s.phone,
           COUNT(pi.id) as inv_count,
           COALESCE(SUM(pi.remaining_amount), 0) as total_due
    FROM suppliers s
    JOIN purchase_invoices pi ON s.id = pi.supplier_id
    WHERE pi.status IN ('open','partial')
    GROUP BY s.id
    HAVING total_due > 0
    ORDER BY total_due DESC LIMIT 6
")->fetchAll();

// Low stock items that need purchase
$lowStock = $db->query("
    SELECT p.id, p.product_name, p.product_code, p.manual_code, p.min_stock, p.reorder_point,
           COALESCE(ii.quantity, 0) as current_qty,
           c.category_name_ar as category
    FROM products p
    LEFT JOIN inventory_items ii ON p.id = ii.product_id
    LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE p.is_active = 1
      AND (ii.quantity <= p.reorder_point OR ii.quantity IS NULL)
      AND p.reorder_point > 0
    ORDER BY (ii.quantity / p.reorder_point) ASC
    LIMIT 8
")->fetchAll();

// Define lookup arrays once (outside loops)
$po_colors = ['draft'=>'secondary','sent'=>'info','partial'=>'warning','received'=>'success','cancelled'=>'danger'];
$po_labels = ['draft'=>'مسودة','sent'=>'مرسل','partial'=>'جزئي','received'=>'مستلم','cancelled'=>'ملغي'];
$inv_colors = ['open'=>'warning','partial'=>'info','paid'=>'success','cancelled'=>'danger'];
$inv_labels = ['open'=>'مفتوحة','partial'=>'جزئي','paid'=>'مسددة','cancelled'=>'ملغية'];

require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root{--primary:#667eea;--secondary:#764ba2;--success:#198754;--warning:#ffc107;--danger:#dc3545;--info:#0dcaf0;}
        body{background:#f0f2f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
        .main-content{margin-right:260px;padding:20px}
        .topbar{background:white;border-radius:15px;padding:15px 25px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center}
        .card1{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px;border-right:4px solid var(--primary);transition:all .3s}
        .card1:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,0.1)}
        .card1 .icon-box{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:12px}
        .card1 h3{font-size:26px;font-weight:700;margin-bottom:3px}
        .card1 p{color:#6c757d;margin:0;font-size:13px}
        .card1.primary{border-color:var(--primary)}.card1.primary .icon-box{background:linear-gradient(135deg,#667eea,#764ba2);color:white}
        .card1.success{border-color:var(--success)}.card1.success .icon-box{background:linear-gradient(135deg,#198754,#20c997);color:white}
        .card1.warning{border-color:var(--warning)}.card1.warning .icon-box{background:linear-gradient(135deg,#ffc107,#ff9800);color:white}
        .card1.danger{border-color:var(--danger)}.card1.danger .icon-box{background:linear-gradient(135deg,#dc3545,#f44336);color:white}
        .card1.info{border-color:var(--info)}.card1.info .icon-box{background:linear-gradient(135deg,#0dcaf0,#03a9f4);color:white}
        .sec-card{background:white;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px}
        .sec-title{font-size:16px;font-weight:700;margin-bottom:15px;display:flex;align-items:center;gap:8px}
        .sec-title::after{content:'';flex:1;height:2px;background:linear-gradient(90deg,var(--primary),transparent);margin-right:10px}
        .qbtn{display:flex;align-items:center;padding:15px 20px;border-radius:12px;text-decoration:none;color:#333;font-weight:600;transition:all .3s;background:#f8f9fa;border:2px solid transparent;margin-bottom:10px}
        .qbtn:hover{background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-color:var(--primary);transform:translateX(-5px)}
        .qbtn:hover i{color:white!important}
        .qbtn i{font-size:22px;margin-left:12px;color:var(--primary);transition:all .3s}
        .po-row,.inv-row{padding:12px 15px;border-radius:10px;margin-bottom:8px;border:1px solid #f0f0f0;transition:all .2s;font-size:13px}
        .po-row:hover,.inv-row:hover{background:#f8f9fa;border-color:var(--primary)}
        .status-pill{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;color:white}
        .due-card{background:linear-gradient(135deg,#fff3cd,#ffecb3);border:1px solid #ffc107;border-radius:12px;padding:15px;margin-bottom:10px}
        .lowstock-row{padding:10px;border-radius:8px;margin-bottom:6px;background:#f8f9fa;border-right:3px solid var(--danger);font-size:13px}
        @media(max-width:768px){.main-content{margin-right:0}}
    </style>
</head>
<body>
<?= $sidebar ?? '' ?>
<div class="main-content">
    <div class="topbar">
        <div>
            <h5 class="mb-0"><i class="bi bi-cart-plus"></i> <?= $page_title ?></h5>
            <small class="text-muted">متابعة أوامر الشراء، الفواتير، والمدفوعات</small>
        </div>
        <div><small class="text-muted"><?= arabicDate(date('Y-m-d')) ?></small></div>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-md-3"><a href="orders/create.php" class="qbtn"><i class="bi bi-file-earmark-plus"></i><div>أمر شراء جديد</div></a></div>
        <div class="col-md-3"><a href="invoices/create.php" class="qbtn"><i class="bi bi-receipt"></i><div>فاتورة شراء مباشرة</div></a></div>
        <div class="col-md-3"><a href="returns/create.php" class="qbtn"><i class="bi bi-arrow-return-left"></i><div>مرتجع للمورد</div></a></div>
        <div class="col-md-3"><a href="reports/" class="qbtn"><i class="bi bi-graph-up"></i><div>تقارير المشتريات</div></a></div>
    </div>

    <!-- Stats Row 1: Orders -->
    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card1 primary"><div class="d-flex justify-content-between"><div><h3><?= intval($poStats['total'] ?? 0) ?></h3><p>إجمالي أوامر الشراء</p></div><div class="icon-box"><i class="bi bi-file-earmark-text"></i></div></div></div></div>
        <div class="col-md-2"><div class="card1 warning"><div class="d-flex justify-content-between"><div><h3><?= intval($poStats['draft'] ?? 0) ?></h3><p>مسودات</p></div><div class="icon-box"><i class="bi bi-pencil-square"></i></div></div></div></div>
        <div class="col-md-2"><div class="card1 info"><div class="d-flex justify-content-between"><div><h3><?= intval($poStats['sent'] ?? 0) ?></h3><p>مرسلة</p></div><div class="icon-box"><i class="bi bi-send"></i></div></div></div></div>
        <div class="col-md-2"><div class="card1 success"><div class="d-flex justify-content-between"><div><h3><?= intval($poStats['partial'] ?? 0) ?></h3><p>استلام جزئي</p></div><div class="icon-box"><i class="bi bi-check2-circle"></i></div></div></div></div>
        <div class="col-md-2"><div class="card1 primary"><div class="d-flex justify-content-between"><div><h3><?= number_format(floatval($poStats['pending_value'] ?? 0), 0) ?> ج</h3><p>قيمة معلقة</p></div><div class="icon-box"><i class="bi bi-cash-stack"></i></div></div></div></div>
        <div class="col-md-2"><div class="card1 danger"><div class="d-flex justify-content-between"><div><h3><?= intval($poStats['cancelled'] ?? 0) ?></h3><p>ملغاة</p></div><div class="icon-box"><i class="bi bi-x-circle"></i></div></div></div></div>
    </div>

    <!-- Stats Row 2: Invoices & Dues -->
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card1 primary"><div class="d-flex justify-content-between"><div><h3><?= intval($invStats['total'] ?? 0) ?></h3><p>فواتير الشراء</p></div><div class="icon-box"><i class="bi bi-receipt"></i></div></div></div></div>
        <div class="col-md-3"><div class="card1 success"><div class="d-flex justify-content-between"><div><h3><?= intval($invStats['paid'] ?? 0) ?></h3><p>فواتير مسددة</p></div><div class="icon-box"><i class="bi bi-check-circle"></i></div></div></div></div>
        <div class="col-md-3"><div class="card1 warning"><div class="d-flex justify-content-between"><div><h3><?= intval($invStats['open'] ?? 0) + intval($invStats['partial'] ?? 0) ?></h3><p>فواتير مستحقة</p></div><div class="icon-box"><i class="bi bi-hourglass-split"></i></div></div></div></div>
        <div class="col-md-3"><div class="card1 danger"><div class="d-flex justify-content-between"><div><h3><?= number_format(floatval($invStats['total_dues'] ?? 0), 0) ?> ج</h3><p>إجمالي المستحقات</p></div><div class="icon-box"><i class="bi bi-exclamation-triangle"></i></div></div></div></div>
    </div>

    <div class="row">
        <!-- Chart -->
        <div class="col-lg-8">
            <div class="sec-card">
                <div class="sec-title"><i class="bi bi-graph-up"></i> مشتريات الشهر الحالي</div>
                <canvas id="purchaseChart" height="100"></canvas>
            </div>
        </div>
        <!-- Supplier Dues -->
        <div class="col-lg-4">
            <div class="sec-card">
                <div class="sec-title"><i class="bi bi-exclamation-triangle"></i> مستحقات الموردين</div>
                <?php foreach($supplierDues as $sd){ ?>
                <div class="due-card">
                    <div class="d-flex justify-content-between">
                        <strong><?= $sd['supplier_name'] ?></strong>
                        <span class="text-danger fw-bold"><?= number_format(floatval($sd['total_due'] ?? 0), 2) ?> ج</span>
                    </div>
                    <small class="text-muted"><?= $sd['inv_count'] ?> فاتورة | <?= $sd['supplier_code'] ?></small>
                </div>
                <?php } ?>
                <?php if(empty($supplierDues)){ ?><p class="text-muted text-center py-3">لا توجد مستحقات</p><?php } ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Low Stock -->
        <div class="col-lg-4">
            <div class="sec-card">
                <div class="sec-title"><i class="bi bi-exclamation-triangle-fill text-danger"></i> أصناف تحتاج شراء (وصلت الحد الأدنى)</div>
                <?php foreach($lowStock as $ls){ ?>
                <div class="lowstock-row">
                    <div class="d-flex justify-content-between">
                        <div><strong><?= $ls['product_name'] ?></strong><small class="text-muted"> (<?= $ls['product_code'] ?>)</small></div>
                        <span class="badge bg-danger"><?= number_format(floatval($ls['current_qty'] ?? 0), 1) ?> / <?= $ls['reorder_point'] ?></span>
                    </div>
                    <small class="text-muted"><?= $ls['category'] ?> | <a href="orders/create.php?product_id=<?= $ls['id'] ?>" class="text-primary">أمر شراء</a></small>
                </div>
                <?php } ?>
                <?php if(empty($lowStock)){ ?><p class="text-muted text-center py-3">لا توجد أصناف وصلت الحد الأدنى</p><?php } ?>
            </div>
        </div>
        <!-- Recent POs -->
        <div class="col-lg-4">
            <div class="sec-card">
                <div class="sec-title"><i class="bi bi-clock-history"></i> أحدث أوامر الشراء</div>
                <?php foreach($recentPOs as $po){ ?>
                <div class="po-row">
                    <div class="d-flex justify-content-between">
                        <strong><?= $po['po_number'] ?></strong>
                        <span class="status-pill bg-<?= $po_colors[$po['status']] ?>"><?= $po_labels[$po['status']] ?></span>
                    </div>
                    <div><small><i class="bi bi-shop"></i> <?= $po['supplier_name'] ?> | <i class="bi bi-cash"></i> <?= number_format(floatval($po['grand_total'] ?? 0), 2) ?> ج</small></div>
                    <small class="text-muted"><?= timeAgo($po['created_at']) ?> | <?= $po['creator'] ?></small>
                </div>
                <?php } ?>
                <?php if(empty($recentPOs)){ ?><p class="text-muted text-center py-3">لا توجد أوامر شراء</p><?php } ?>
            </div>
        </div>
        <!-- Recent Invoices -->
        <div class="col-lg-4">
            <div class="sec-card">
                <div class="sec-title"><i class="bi bi-receipt"></i> أحدث فواتير الشراء</div>
                <?php foreach($recentInvs as $inv){ ?>
                <div class="inv-row">
                    <div class="d-flex justify-content-between">
                        <strong><?= $inv['invoice_number'] ?></strong>
                        <span class="status-pill bg-<?= $inv_colors[$inv['status']] ?>"><?= $inv_labels[$inv['status']] ?></span>
                    </div>
                    <div><small><i class="bi bi-shop"></i> <?= $inv['supplier_name'] ?> | <i class="bi bi-cash"></i> <?= number_format(floatval($inv['grand_total'] ?? 0), 2) ?> ج</small></div>
                    <small class="text-muted"><?= arabicDate($inv['invoice_date']) ?> | <?= $inv['creator'] ?></small>
                </div>
                <?php } ?>
                <?php if(empty($recentInvs)){ ?><p class="text-muted text-center py-3">لا توجد فواتير شراء</p><?php } ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx=document.getElementById('purchaseChart').getContext('2d');
const data=<?= json_encode($monthData) ?>;
new Chart(ctx,{
    type:'bar',
    data:{labels:data.map(d=>d.d.substring(8)+'/'+d.d.substring(5,7)),datasets:[{
        label:'مشتريات (ج)',data:data.map(d=>d.total),backgroundColor:'rgba(102,126,234,0.7)',borderColor:'#667eea',borderWidth:1,borderRadius:6
    },{label:'عدد الفواتير',data:data.map(d=>d.cnt),backgroundColor:'rgba(118,75,162,0.5)',borderColor:'#764ba2',borderWidth:1,borderRadius:6,yAxisID:'y1'}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.05)'}},y1:{position:'right',beginAtZero:true,grid:{display:false},ticks:{stepSize:1}},x:{grid:{display:false}}}}
});
</script>
</body>
</html>