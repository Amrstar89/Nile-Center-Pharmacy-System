<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

// Statistics
$stats = [];

// Total orders today
$stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = CURDATE()");
$stats['today_orders'] = $stmt->fetch()['count'];

// Open orders (not final status)
$stmt = $db->query("SELECT COUNT(*) as count FROM orders o 
    JOIN order_statuses os ON o.status_id = os.id 
    WHERE os.is_final = 0");
$stats['open_orders'] = $stmt->fetch()['count'];

// Urgent orders
$stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE priority = 'urgent' AND status_id IN (SELECT id FROM order_statuses WHERE is_final = 0)");
$stats['urgent_orders'] = $stmt->fetch()['count'];

// Critical orders
$stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE priority = 'critical' AND status_id IN (SELECT id FROM order_statuses WHERE is_final = 0)");
$stats['critical_orders'] = $stmt->fetch()['count'];

// Recent orders
$stmt = $db->query("SELECT o.*, os.status_name, os.status_color, u.full_name as creator_name 
    FROM orders o 
    JOIN order_statuses os ON o.status_id = os.id 
    JOIN users u ON o.created_by = u.id 
    ORDER BY o.created_at DESC LIMIT 10");
$recent_orders = $stmt->fetchAll();

// Status distribution
$stmt = $db->query("SELECT os.status_name, os.status_color, COUNT(o.id) as count 
    FROM order_statuses os 
    LEFT JOIN orders o ON os.id = o.status_id 
    WHERE os.is_active = 1
    GROUP BY os.id, os.status_name, os.status_color 
    ORDER BY os.sort_order");
$status_dist = $stmt->fetchAll();

// Recent activity
$stmt = $db->query("SELECT al.*, u.full_name as user_name 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC LIMIT 15");
$activities = $stmt->fetchAll();

$page_title = 'الرئيسية';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #0dcaf0;
        }
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            position: fixed;
            right: 0;
            top: 0;
            width: 260px;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand h4 {
            color: white;
            margin: 0;
            font-weight: 700;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.6);
        }
        .nav-menu {
            padding: 15px 0;
        }
        .nav-item {
            margin: 2px 0;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            text-decoration: none;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-right: 3px solid var(--primary);
        }
        .nav-link i {
            width: 25px;
            margin-left: 10px;
            font-size: 18px;
        }
        .main-content {
            margin-right: 260px;
            padding: 20px;
        }
        .topbar {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
            border: none;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .content-card h5 {
            margin-bottom: 20px;
            color: #333;
            font-weight: 700;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        .priority-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        .priority-normal { background: #e9ecef; color: #495057; }
        .priority-urgent { background: #fff3cd; color: #856404; }
        .priority-critical { background: #f8d7da; color: #721c24; }
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 12px;
            font-size: 16px;
            flex-shrink: 0;
        }
        .activity-content {
            flex: 1;
        }
        .activity-time {
            color: #6c757d;
            font-size: 12px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-left: 10px;
        }
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
        }
        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-right: 0; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>  

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0"><?= $page_title ?></h5>
                <small class="text-muted"><?= arabicDate(date('Y-m-d')) ?></small>
            </div>
            <div class="d-flex align-items-center">
                <div class="user-avatar"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div>
                    <div class="fw-bold"><?= $_SESSION['user_name'] ?></div>
                    <small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <?= showAlert($_SESSION['success'], 'success') ?>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <?= showAlert($_SESSION['error'], 'danger') ?>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-cart-plus"></i>
                    </div>
                    <div class="stat-value text-primary"><?= $stats['today_orders'] ?></div>
                    <div class="stat-label">طلبات اليوم</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stat-value text-warning"><?= $stats['open_orders'] ?></div>
                    <div class="stat-label">طلبات مفتوحة</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value text-danger"><?= $stats['urgent_orders'] ?></div>
                    <div class="stat-label">طلبات عاجلة</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-dark bg-opacity-10 text-dark">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <div class="stat-value text-dark"><?= $stats['critical_orders'] ?></div>
                    <div class="stat-label">طلبات حرجة</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="bi bi-clock-history me-2"></i>أحدث الطلبات</h5>
                        <a href="orders.php" class="btn btn-sm btn-primary">عرض الكل</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>الحالة</th>
                                    <th>الأولوية</th>
                                    <th>التاريخ</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td class="fw-bold"><?= $order['order_number'] ?></td>
                                    <td><?= $order['customer_name'] ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?= $order['status_color'] ?>">
                                            <?= $order['status_name'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?= $order['priority'] ?>">
                                            <?= $order['priority'] === 'normal' ? 'عادي' : ($order['priority'] === 'urgent' ? 'عاجل' : 'حرج') ?>
                                        </span>
                                    </td>
                                    <td><?= timeAgo($order['created_at']) ?></td>
                                    <td>
                                        <a href="orders.php?view=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Status Distribution -->
                <div class="content-card">
                    <h5><i class="bi bi-pie-chart me-2"></i>توزيع الحالات</h5>
                    <?php foreach ($status_dist as $status): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>
                            <span class="badge" style="background: <?= $status['status_color'] ?>">&nbsp;</span>
                            <?= $status['status_name'] ?>
                        </span>
                        <span class="fw-bold"><?= $status['count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recent Activity -->
                <div class="content-card">
                    <h5><i class="bi bi-activity me-2"></i>آخر النشاطات</h5>
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="activity-content">
                            <div class="small">
                                <strong><?= $activity['user_name'] ?? 'System' ?></strong>
                                <?= $activity['action'] === 'login' ? 'سجل دخول' : ($activity['action'] === 'create' ? 'أضاف طلب جديد' : ($activity['action'] === 'update' ? 'عدل طلب' : $activity['action'])) ?>
                            </div>
                            <div class="activity-time"><?= timeAgo($activity['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
