<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $to_user = $_POST['to_user'] ?? 0;
        $general_notes = $_POST['general_notes'] ?? '';
        $critical_notes = $_POST['critical_notes'] ?? '';

        // Count open orders
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders o 
            JOIN order_statuses os ON o.status_id = os.id 
            WHERE os.is_final = 0");
        $open_orders = $stmt->fetch()['count'];

        // Count urgent orders
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders o 
            JOIN order_statuses os ON o.status_id = os.id 
            WHERE os.is_final = 0 AND o.priority = 'urgent'");
        $urgent_orders = $stmt->fetch()['count'];

        // Count critical orders
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders o 
            JOIN order_statuses os ON o.status_id = os.id 
            WHERE os.is_final = 0 AND o.priority = 'critical'");
        $critical_orders = $stmt->fetch()['count'];

        // Insert handover
        $stmt = $db->prepare("
            INSERT INTO shift_handovers (from_user, to_user, open_orders_count, urgent_orders_count, critical_notes, general_notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $to_user, $open_orders, $urgent_orders, $critical_notes, $general_notes
        ]);

        logActivity('shift_handover', 'shift_handovers', $db->lastInsertId());

        $_SESSION['success'] = "تم تسليم الشيفت بنجاح. عدد الطلبات المفتوحة: $open_orders";
        redirect(APP_URL . '/modules/customer-requests/index.php');

    } catch (Exception $e) {
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// Get active users for handover
$stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE is_active = 1 AND id != ? ORDER BY full_name");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Get current open orders summary
$stmt = $db->query("SELECT o.*, os.status_name, os.status_color, u.full_name as creator_name 
    FROM orders o 
    JOIN order_statuses os ON o.status_id = os.id 
    JOIN users u ON o.created_by = u.id 
    WHERE os.is_final = 0 
    ORDER BY o.priority DESC, o.created_at DESC");
$open_orders_list = $stmt->fetchAll();

$page_title = 'تسليم الشيفت';
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
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000;
        }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h4 { color: white; margin: 0; font-weight: 700; }
        .nav-menu { padding: 15px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 3px solid var(--primary); }
        .nav-link i { width: 25px; margin-left: 10px; font-size: 18px; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .summary-card { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; }
        .summary-stat { text-align: center; padding: 15px; }
        .summary-stat h3 { font-size: 36px; font-weight: 700; margin-bottom: 5px; }
        .summary-stat p { margin: 0; opacity: 0.9; }
        .form-label { font-weight: 600; }
        .form-control, .form-select { border-radius: 10px; padding: 12px 15px; border: 2px solid #e0e0e0; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn-submit { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 10px; padding: 12px 30px; color: white; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-2px); color: white; }
        .order-mini { background: #f8f9fa; border-radius: 8px; padding: 10px; margin-bottom: 8px; font-size: 13px; }
        .status-badge-mini { padding: 2px 8px; border-radius: 10px; font-size: 10px; color: white; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
   <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">تسليم الشيفت للصيدلي التالي</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if ($error): ?><?= showAlert($error, 'danger') ?><?php endif; ?>

        <!-- Summary -->
        <div class="summary-card">
            <div class="row">
                <div class="col-md-4">
                    <div class="summary-stat">
                        <h3><?= count($open_orders_list) ?></h3>
                        <p><i class="bi bi-hourglass-split me-1"></i>طلبات مفتوحة</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-stat">
                        <h3><?= count(array_filter($open_orders_list, fn($o) => $o['priority'] === 'urgent')) ?></h3>
                        <p><i class="bi bi-exclamation-triangle me-1"></i>طلبات عاجلة</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="summary-stat">
                        <h3><?= count(array_filter($open_orders_list, fn($o) => $o['priority'] === 'critical')) ?></h3>
                        <p><i class="bi bi-lightning-charge me-1"></i>طلبات حرجة</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Handover Form -->
            <div class="col-lg-8">
                <div class="content-card">
                    <h5 class="mb-4"><i class="bi bi-arrow-left-right me-2"></i>نموذج تسليم الشيفت</h5>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-person-check me-1"></i>تسليم إلى</label>
                            <select class="form-select" name="to_user" required>
                                <option value="">-- اختر الصيدلي --</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= $user['full_name'] ?> (<?= $user['role'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-exclamation-triangle me-1"></i>ملاحظات حرجة (طلبات مهمة جداً)</label>
                            <textarea class="form-control" name="critical_notes" rows="3" placeholder="مثال: عميل ينتظر Ozempic منذ 3 أيام..."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label"><i class="bi bi-sticky me-1"></i>ملاحظات عامة</label>
                            <textarea class="form-control" name="general_notes" rows="3" placeholder="أي ملاحظات أخرى على الشيفت..."></textarea>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-submit btn-lg">
                                <i class="bi bi-check-lg me-2"></i>تسليم الشيفت
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Open Orders List -->
            <div class="col-lg-4">
                <div class="content-card">
                    <h5 class="mb-3"><i class="bi bi-list-task me-2"></i>الطلبات المفتوحة</h5>
                    <?php foreach (array_slice($open_orders_list, 0, 10) as $order): ?>
                    <div class="order-mini">
                        <div class="d-flex justify-content-between">
                            <strong><?= $order['order_number'] ?></strong>
                            <span class="status-badge-mini" style="background: <?= $order['status_color'] ?>"><?= $order['status_name'] ?></span>
                        </div>
                        <div class="text-muted"><?= $order['customer_name'] ?></div>
                        <?php if ($order['priority'] !== 'normal'): ?>
                        <span class="badge bg-<?= $order['priority'] === 'urgent' ? 'warning' : 'danger' ?>">
                            <?= $order['priority'] === 'urgent' ? 'عاجل' : 'حرج' ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($open_orders_list) > 10): ?>
                    <div class="text-center mt-2">
                        <a href="orders.php" class="text-primary">عرض الكل (<?= count($open_orders_list) ?>)</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
