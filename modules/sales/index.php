<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'المبيعات';

// Get today's sales summary
$today = date('Y-m-d');
$stmt = $db->query("
    SELECT COUNT(DISTINCT o.id) as invoice_count, 
           COALESCE(SUM(oi.final_price), 0) as total_sales,
           COUNT(DISTINCT o.customer_name) as customer_count
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE DATE(o.created_at) = '$today' AND o.status_id = (SELECT id FROM order_statuses WHERE status_name = 'تم التسليم' LIMIT 1)
");
$todaySales = $stmt->fetch();

// Get sales by status for chart
$stmt = $db->query("
    SELECT os.status_name, os.status_color, COUNT(*) as count, SUM(o.final_total) as total
    FROM orders o
    JOIN order_statuses os ON o.status_id = os.id
    WHERE DATE(o.created_at) >= DATE_SUB('$today', INTERVAL 30 DAY)
    GROUP BY o.status_id
");
$salesByStatus = $stmt->fetchAll();

// Recent invoices
$stmt = $db->query("
    SELECT o.*, os.status_name, os.status_color,
           COUNT(oi.id) as items_count, SUM(oi.quantity) as total_qty
    FROM orders o
    JOIN order_statuses os ON o.status_id = os.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 20
");
$recentInvoices = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-right: 4px solid var(--primary); transition: all 0.3s; }
        .stat-card .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px; }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-card p { color: #6c757d; margin: 0; font-size: 14px; }
        .stat-card.primary { border-color: var(--primary); }
        .stat-card.primary .icon-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card.success { border-color: var(--success); }
        .stat-card.success .icon-box { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; }
        .stat-card.warning { border-color: var(--warning); }
        .stat-card.warning .icon-box { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; }
        .invoice-row { padding: 12px 15px; border-radius: 10px; margin-bottom: 8px; transition: all 0.2s; border: 1px solid #f0f0f0; }
        .invoice-row:hover { background: #f8f9fa; border-color: var(--primary); }
        .section-title { border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px; }
        .quick-action { display: flex; align-items: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px; text-decoration: none; transition: all 0.3s; }
        .quick-action:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); color: white; }
        .quick-action i { font-size: 28px; margin-left: 15px; }
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; color: white; }
        @media (max-width: 768px) { .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><i class="bi bi-cash-register"></i> <?= $page_title ?></h5><small class="text-muted">متابعة فواتير البيع والمبيعات</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="create.php" class="quick-action">
                    <i class="bi bi-plus-circle"></i>
                    <div>
                        <h5 class="mb-1">فاتورة بيع جديدة</h5>
                        <small>إنشاء فاتورة بيع مباشر للعميل</small>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="pos.php" class="quick-action" style="background: linear-gradient(135deg, #198754 0%, #20c997 100%);">
                    <i class="bi bi-cart-check"></i>
                    <div>
                        <h5 class="mb-1">نقطة البيع (POS)</h5>
                        <small>واجهة سريعة للبيع الفوري</small>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="returns.php" class="quick-action" style="background: linear-gradient(135deg, #dc3545 0%, #f44336 100%);">
                    <i class="bi bi-arrow-return-left"></i>
                    <div>
                        <h5 class="mb-1">مرتجعات البيع</h5>
                        <small>تسجيل مرتجع من العميل</small>
                    </div>
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= number_format($todaySales['total_sales'] ?? 0, 2) ?> <small style="font-size:14px">ج</small></h3>
                            <p>مبيعات اليوم</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-cash-stack"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= $todaySales['invoice_count'] ?? 0 ?></h3>
                            <p>فواتير اليوم</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-receipt"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= $todaySales['customer_count'] ?? 0 ?></h3>
                            <p>عملاء اليوم</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-people"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= count($recentInvoices) ?></h3>
                            <p>إجمالي الفواتير</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-file-text"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="content-card">
            <div class="section-header d-flex justify-content-between align-items-center">
                <h5 class="section-title"><i class="bi bi-receipt me-2"></i>أحدث الفواتير</h5>
            </div>
            <?php if (count($recentInvoices) > 0): ?>
                <?php foreach ($recentInvoices as $inv): ?>
                <div class="invoice-row">
                    <div class="row align-items-center">
                        <div class="col-md-2"><strong><?= $inv['order_number'] ?></strong></div>
                        <div class="col-md-3"><i class="bi bi-person me-1"></i> <?= htmlspecialchars($inv['customer_name']) ?></div>
                        <div class="col-md-2"><span class="status-pill" style="background:<?= $inv['status_color'] ?>"><?= $inv['status_name'] ?></span></div>
                        <div class="col-md-2"><i class="bi bi-box me-1"></i> <?= $inv['items_count'] ?> صنف (<?= $inv['total_qty'] ?>)</div>
                        <div class="col-md-2"><strong><?= number_format($inv['final_total'], 2) ?> ج</strong></div>
                        <div class="col-md-1"><small class="text-muted"><?= timeAgo($inv['created_at']) ?></small></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center py-4">لا توجد فواتير بعد</p>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
