<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'لوحة التحكم';

// Quick Stats
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('sunday this week -6 days'));
$monthStart = date('Y-m-01');

// Today's sales
$stmt = $db->query("SELECT COALESCE(SUM(final_price * quantity), 0) as total, COUNT(DISTINCT order_id) as count 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    WHERE DATE(o.created_at) = '$today' AND o.status_id = (SELECT id FROM order_statuses WHERE is_final = 1 LIMIT 1)");
$todaySales = $stmt->fetch();

// Today's orders count
$stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = '$today'");
$todayOrders = $stmt->fetch();

// Pending orders
$stmt = $db->query("SELECT COUNT(*) as count FROM orders o 
    JOIN order_statuses os ON o.status_id = os.id WHERE os.is_final = 0");
$pendingOrders = $stmt->fetch();

// Items need purchase
$stmt = $db->query("SELECT COUNT(*) as count FROM order_items WHERE needs_purchase = 1");
$needPurchase = $stmt->fetch();

// Low stock items
$stmt = $db->query("SELECT COUNT(*) as count FROM inventory_items WHERE quantity <= reorder_point AND reorder_point > 0");
$lowStock = $stmt->fetch();

// Today's purchases
$stmt = $db->query("SELECT COALESCE(SUM(total_cost), 0) as total, COUNT(*) as count 
    FROM purchased_items WHERE DATE(purchased_at) = '$today'");
$todayPurchases = $stmt->fetch();

// Customers count
$stmt = $db->query("SELECT COUNT(*) as count FROM customers WHERE is_active = 1");
$customersCount = $stmt->fetch();

// Suppliers count
$stmt = $db->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1");
$suppliersCount = $stmt->fetch();

// Products count
$stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
$productsCount = $stmt->fetch();

// Recent orders
$stmt = $db->query("SELECT o.*, os.status_name, os.status_color 
    FROM orders o 
    JOIN order_statuses os ON o.status_id = os.id 
    ORDER BY o.created_at DESC LIMIT 10");
$recentOrders = $stmt->fetchAll();

// Pending shift handovers for current user
$stmt = $db->prepare("SELECT sh.*, u.full_name as from_user_name 
    FROM shift_handovers sh 
    JOIN users u ON sh.from_user = u.id 
    WHERE sh.to_user = ? AND sh.is_acknowledged = 0 
    ORDER BY sh.created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$pendingHandovers = $stmt->fetchAll();

// Today's activity
$stmt = $db->query("SELECT al.*, u.full_name as user_name 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    WHERE DATE(al.created_at) = '$today'
    ORDER BY al.created_at DESC LIMIT 10");
$todayActivity = $stmt->fetchAll();

// Month sales chart data
$stmt = $db->query("SELECT DATE(created_at) as date, SUM(final_total) as total, COUNT(*) as count 
    FROM orders 
    WHERE created_at >= '$monthStart' 
    GROUP BY DATE(created_at) 
    ORDER BY date");
$monthSalesData = $stmt->fetchAll();

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; --info: #0dcaf0; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .stat-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-right: 4px solid var(--primary); transition: all 0.3s; cursor: pointer; text-decoration: none; color: inherit; display: block; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px; }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-card p { color: #6c757d; margin: 0; font-size: 14px; }
        .stat-card.primary { border-color: var(--primary); }
        .stat-card.primary .icon-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card.success { border-color: var(--success); }
        .stat-card.success .icon-box { background: linear-gradient(135deg, #198754 0%, #20c997 100%); color: white; }
        .stat-card.warning { border-color: var(--warning); }
        .stat-card.warning .icon-box { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; }
        .stat-card.danger { border-color: var(--danger); }
        .stat-card.danger .icon-box { background: linear-gradient(135deg, #dc3545 0%, #f44336 100%); color: white; }
        .stat-card.info { border-color: var(--info); }
        .stat-card.info .icon-box { background: linear-gradient(135deg, #0dcaf0 0%, #03a9f4 100%); color: white; }
        .section-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-title { font-size: 16px; font-weight: 700; margin: 0; }
        .quick-link { display: flex; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 12px; text-decoration: none; color: #333; transition: all 0.3s; margin-bottom: 10px; border: 1px solid transparent; }
        .quick-link:hover { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transform: translateX(-5px); border-color: var(--primary); }
        .quick-link:hover i { color: white; }
        .quick-link i { font-size: 22px; margin-left: 12px; color: var(--primary); transition: all 0.3s; }
        .quick-link span { font-weight: 600; }
        .quick-link small { display: block; font-size: 12px; opacity: 0.7; }
        .chart-container { position: relative; height: 300px; }
        .activity-item { padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; }
        .activity-item:last-child { border-bottom: none; }
        .activity-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: 12px; font-size: 14px; }
        .activity-content { flex: 1; }
        .activity-time { font-size: 12px; color: #999; }
        .order-row { padding: 10px; border-radius: 8px; margin-bottom: 5px; transition: all 0.2s; }
        .order-row:hover { background: #f8f9fa; }
        .badge-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .alert-card { background: linear-gradient(135deg, #fff3cd 0%, #ffecb3 100%); border: 1px solid #ffc107; border-radius: 12px; padding: 15px; margin-bottom: 15px; }
        .alert-card.danger { background: linear-gradient(135deg, #f8d7da 0%, #ffcdd2 100%); border-color: #dc3545; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0"><i class="bi bi-speedometer2"></i> <?= $page_title ?></h5>
                <small class="text-muted"><?= arabicDate(date('Y-m-d')) ?> | مرحباً <?= $_SESSION['user_name'] ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if (count($pendingHandovers) > 0): ?>
                <a href="<?= APP_URL ?>/modules/shifts/" class="btn btn-warning position-relative">
                    <i class="bi bi-bell-fill"></i> شيفتات جديدة
                    <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger"><?= count($pendingHandovers) ?></span>
                </a>
                <?php endif; ?>
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (count($pendingHandovers) > 0): ?>
        <div class="alert-card">
            <h6><i class="bi bi-bell me-2"></i>شيفتات بانتظار الاستلام</h6>
            <?php foreach ($pendingHandovers as $h): ?>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <span><i class="bi bi-person me-1"></i> تم التسليم من <strong><?= $h['from_user_name'] ?></strong></span>
                <a href="<?= APP_URL ?>/modules/shifts/" class="btn btn-sm btn-warning">استلام الشيفت</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($needPurchase['count'] > 0): ?>
        <div class="alert-card danger">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart-x me-2"></i><strong><?= $needPurchase['count'] ?></strong> صنف تحتاج شراء</span>
                <a href="<?= APP_URL ?>/modules/purchases/" class="btn btn-sm btn-danger">شاشة المشتريات</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($lowStock['count'] > 0): ?>
        <div class="alert-card">
            <div class="d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-triangle me-2"></i><strong><?= $lowStock['count'] ?></strong> صنف وصل للحد الأدنى</span>
                <a href="<?= APP_URL ?>/modules/inventory/" class="btn btn-sm btn-warning">المخازن</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/sales/" class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= number_format($todaySales['total'] ?? 0, 2) ?> <small style="font-size:14px">ج</small></h3>
                            <p>مبيعات اليوم</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-cart-check"></i></div>
                    </div>
                    <small class="text-muted"><?= $todaySales['count'] ?? 0 ?> فاتورة</small>
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/customer-requests/orders.php" class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= $pendingOrders['count'] ?? 0 ?></h3>
                            <p>طلبات مفتوحة</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    <small class="text-muted">من أصل <?= $todayOrders['count'] ?? 0 ?> طلب اليوم</small>
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/purchases/" class="stat-card success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= number_format($todayPurchases['total'] ?? 0, 2) ?> <small style="font-size:14px">ج</small></h3>
                            <p>مشتريات اليوم</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-cart-plus"></i></div>
                    </div>
                    <small class="text-muted"><?= $todayPurchases['count'] ?? 0 ?> عملية</small>
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/inventory/" class="stat-card danger">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= $lowStock['count'] ?? 0 ?></h3>
                            <p>نواقص مخزون</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <small class="text-muted">أصناف وصلت الحد الأدنى</small>
                </a>
            </div>
        </div>

        <!-- Stats Row 2 - Master Data -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/customers/" class="stat-card info">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= $customersCount['count'] ?? 0 ?></h3>
                            <p>العملاء</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-people"></i></div>
                    </div>
                    <small class="text-muted">عميل مسجل</small>
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/suppliers/" class="stat-card info">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= $suppliersCount['count'] ?? 0 ?></h3>
                            <p>الموردين</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-truck"></i></div>
                    </div>
                    <small class="text-muted">مورد مسجل</small>
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/products/" class="stat-card info">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><?= $productsCount['count'] ?? 0 ?></h3>
                            <p>الأصناف</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-boxes"></i></div>
                    </div>
                    <small class="text-muted">صنف مسجل</small>
                </a>
            </div>
            <div class="col-md-3">
                <a href="<?= APP_URL ?>/modules/customer-requests/new-order.php" class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3><i class="bi bi-plus-lg"></i></h3>
                            <p>طلب جديد</p>
                        </div>
                        <div class="icon-box"><i class="bi bi-plus-circle"></i></div>
                    </div>
                    <small class="text-muted">إنشاء طلب عميل جديد</small>
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Sales Chart -->
            <div class="col-lg-8">
                <div class="section-card">
                    <div class="section-header">
                        <h6 class="section-title"><i class="bi bi-graph-up me-2"></i>مبيعات الشهر الحالي</h6>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-4">
                <div class="section-card">
                    <div class="section-header">
                        <h6 class="section-title"><i class="bi bi-lightning me-2"></i>وصول سريع</h6>
                    </div>
                    <a href="<?= APP_URL ?>/modules/customer-requests/new-order.php" class="quick-link">
                        <i class="bi bi-plus-circle"></i>
                        <div><span>طلب جديد</span><small>إنشاء طلب عميل جديد</small></div>
                    </a>
                    <a href="<?= APP_URL ?>/modules/sales/create.php" class="quick-link">
                        <i class="bi bi-cash-register"></i>
                        <div><span>فاتورة بيع</span><small>إنشاء فاتورة بيع مباشر</small></div>
                    </a>
                    <a href="<?= APP_URL ?>/modules/purchases/" class="quick-link">
                        <i class="bi bi-cart-plus"></i>
                        <div><span>شاشة المشتريات</span><small>متابعة أصناف تحتاج شراء</small></div>
                    </a>
                    <a href="<?= APP_URL ?>/modules/shifts/" class="quick-link">
                        <i class="bi bi-arrow-left-right"></i>
                        <div><span>تسليم الشيفت</span><small>تسليم الشيفت للصيدلي التالي</small></div>
                    </a>
                    <a href="<?= APP_URL ?>/modules/inventory/opening_balance/create.php" class="quick-link">
                        <i class="bi bi-journal-plus"></i>
                        <div><span>رصيد افتتاحي</span><small>إضافة رصيد افتتاحي لمخزن</small></div>
                    </a>
                    <a href="<?= APP_URL ?>/modules/inventory/transfers/" class="quick-link">
                        <i class="bi bi-arrow-left-right"></i>
                        <div><span>تحويل مخزني</span><small>تحويل أصناف بين المخازن</small></div>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-6">
                <div class="section-card">
                    <div class="section-header">
                        <h6 class="section-title"><i class="bi bi-clock-history me-2"></i>أحدث الطلبات</h6>
                        <a href="<?= APP_URL ?>/modules/customer-requests/orders.php" class="btn btn-sm btn-outline-primary">الكل</a>
                    </div>
                    <?php foreach ($recentOrders as $order): ?>
                    <div class="order-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= $order['order_number'] ?></strong>
                                <span class="text-muted mx-2">|</span>
                                <span><?= htmlspecialchars($order['customer_name']) ?></span>
                            </div>
                            <span class="badge-pill" style="background:<?= $order['status_color'] ?>;color:#fff"><?= $order['status_name'] ?></span>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-calendar"></i> <?= timeAgo($order['created_at']) ?>
                            <span class="mx-2">|</span>
                            <i class="bi bi-cash"></i> <?= number_format($order['final_total'], 2) ?> ج
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Today's Activity -->
            <div class="col-lg-6">
                <div class="section-card">
                    <div class="section-header">
                        <h6 class="section-title"><i class="bi bi-activity me-2"></i>حركة اليوم</h6>
                    </div>
                    <?php if (count($todayActivity) > 0): ?>
                        <?php foreach ($todayActivity as $act): ?>
                        <div class="activity-item">
                            <?php
                            $actIcon = 'bi-info-circle';
                            $actColor = 'var(--primary)';
                            $actBg = 'rgba(102,126,234,0.1)';
                            if (strpos($act['action'], 'create') !== false) { $actIcon = 'bi-plus-circle'; $actColor = 'var(--success)'; $actBg = 'rgba(25,135,84,0.1)'; }
                            elseif (strpos($act['action'], 'edit') !== false) { $actIcon = 'bi-pencil'; $actColor = 'var(--warning)'; $actBg = 'rgba(255,193,7,0.1)'; }
                            elseif (strpos($act['action'], 'delete') !== false) { $actIcon = 'bi-trash'; $actColor = 'var(--danger)'; $actBg = 'rgba(220,53,69,0.1)'; }
                            elseif (strpos($act['action'], 'purchase') !== false) { $actIcon = 'bi-cart'; $actColor = 'var(--info)'; $actBg = 'rgba(13,202,240,0.1)'; }
                            elseif (strpos($act['action'], 'handover') !== false) { $actIcon = 'bi-arrow-left-right'; $actColor = '#6c757d'; $actBg = 'rgba(108,117,125,0.1)'; }
                            ?>
                            <div class="activity-icon" style="background:<?= $actBg ?>;color:<?= $actColor ?>"><i class="bi <?= $actIcon ?>"></i></div>
                            <div class="activity-content">
                                <strong><?= $act['user_name'] ?? 'نظام' ?></strong>
                                <span class="text-muted"> - <?= $act['action'] ?></span>
                                <?php if ($act['table_name']): ?><small class="text-muted"> (<?= $act['table_name'] ?>)</small><?php endif; ?>
                            </div>
                            <span class="activity-time"><?= timeAgo($act['created_at']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">لا توجد حركات اليوم</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?= json_encode($monthSalesData) ?>;
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.date.substring(8)),
                datasets: [{
                    label: 'المبيعات (ج)',
                    data: salesData.map(d => d.total),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointBackgroundColor: '#667eea',
                    pointRadius: 4
                }, {
                    label: 'عدد الفواتير',
                    data: salesData.map(d => d.count),
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.05)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    yAxisID: 'y1',
                    pointBackgroundColor: '#764ba2',
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: v => v + ' ج' } },
                    y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>
