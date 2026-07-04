<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$success = '';
$error = '';

// Handle handover submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'handover') {
    try {
        $to_user = $_POST['to_user'] ?? 0;
        $general_notes = $_POST['general_notes'] ?? '';
        $critical_notes = $_POST['critical_notes'] ?? '';

        $stmt = $db->query("SELECT COUNT(*) as count FROM orders o 
            JOIN order_statuses os ON o.status_id = os.id WHERE os.is_final = 0");
        $open_orders = $stmt->fetch()['count'];

        $stmt = $db->query("SELECT COUNT(*) as count FROM orders o 
            JOIN order_statuses os ON o.status_id = os.id WHERE os.is_final = 0 AND o.priority = 'urgent'");
        $urgent_orders = $stmt->fetch()['count'];

        $stmt = $db->prepare("
            INSERT INTO shift_handovers (from_user, to_user, open_orders_count, urgent_orders_count, critical_notes, general_notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $to_user, $open_orders, $urgent_orders, $critical_notes, $general_notes
        ]);

        logActivity('shift_handover', 'shift_handovers', $db->lastInsertId());
        $_SESSION['success'] = "تم تسليم الشيفت بنجاح. عدد الطلبات المفتوحة: $open_orders";
        redirect(APP_URL . '/modules/dashboard/');
    } catch (Exception $e) {
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

// Handle acknowledge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handover_id'])) {
    $handover_id = (int)$_POST['handover_id'];
    try {
        $db->prepare("UPDATE shift_handovers SET is_acknowledged = 1, acknowledged_at = NOW() WHERE id = ?")
           ->execute([$handover_id]);
        logActivity('shift_acknowledge', 'shift_handovers', $handover_id);
        $_SESSION['success'] = 'تم استلام الشيفت بنجاح';
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/modules/shifts/');
}

// Get active users for handover
$stmt = $db->prepare("SELECT id, full_name, role FROM users WHERE is_active = 1 AND id != ? ORDER BY full_name");
$stmt->execute([$_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Current open orders
$stmt = $db->query("SELECT o.*, os.status_name, os.status_color, u.full_name as creator_name 
    FROM orders o 
    JOIN order_statuses os ON o.status_id = os.id 
    JOIN users u ON o.created_by = u.id 
    WHERE os.is_final = 0 
    ORDER BY o.priority DESC, o.created_at DESC");
$open_orders_list = $stmt->fetchAll();

// Pending handovers for current user
$stmt = $db->prepare("SELECT sh.*, u.full_name as from_user_name, u2.full_name as to_user_name 
    FROM shift_handovers sh 
    JOIN users u ON sh.from_user = u.id 
    JOIN users u2 ON sh.to_user = u2.id 
    WHERE sh.to_user = ? AND sh.is_acknowledged = 0 
    ORDER BY sh.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$pending_handovers = $stmt->fetchAll();

// All handovers (for admin)
if (isAdmin()) {
    $stmt = $db->query("SELECT sh.*, u.full_name as from_user_name, u2.full_name as to_user_name 
        FROM shift_handovers sh 
        JOIN users u ON sh.from_user = u.id 
        JOIN users u2 ON sh.to_user = u2.id 
        ORDER BY sh.created_at DESC LIMIT 50");
    $all_handovers = $stmt->fetchAll();
}

$page_title = 'إدارة الشيفتات';
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
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .summary-card { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; }
        .summary-stat { text-align: center; padding: 15px; }
        .summary-stat h3 { font-size: 36px; font-weight: 700; margin-bottom: 5px; }
        .summary-stat p { margin: 0; opacity: 0.9; }
        .handover-card { border: 2px solid #e0e0e0; border-radius: 15px; padding: 25px; margin-bottom: 20px; transition: all 0.3s; }
        .handover-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .handover-card.pending { border-color: #ffc107; background: #fffdf5; }
        .handover-card.acknowledged { border-color: #198754; background: #f8fff9; }
        .stat-box { background: #f8f9fa; border-radius: 10px; padding: 15px; text-align: center; }
        .stat-box h4 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-box p { margin: 0; color: #6c757d; }
        .order-mini { background: #f8f9fa; border-radius: 8px; padding: 10px; margin-bottom: 8px; font-size: 13px; }
        .status-badge-mini { padding: 2px 8px; border-radius: 10px; font-size: 10px; color: white; }
        .nav-tabs .nav-link { color: #666; border: none; padding: 15px 25px; font-weight: 600; }
        .nav-tabs .nav-link.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: none; }
        .nav-tabs .nav-link i { margin-left: 8px; }
        .btn-submit { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 10px; padding: 12px 30px; color: white; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-2px); color: white; }
        .form-label { font-weight: 600; }
        .form-control, .form-select { border-radius: 10px; padding: 12px 15px; border: 2px solid #e0e0e0; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        @media (max-width: 768px) { .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> <?= $page_title ?></h5><small class="text-muted">تسليم واستلام الشيفتات</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

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

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="shiftTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#receive">
                    <i class="bi bi-box-arrow-in-down"></i> استلام الشيفت
                    <?php if (count($pending_handovers) > 0): ?>
                    <span class="badge bg-danger"><?= count($pending_handovers) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#handover">
                    <i class="bi bi-arrow-up-circle"></i> تسليم الشيفت
                </a>
            </li>
            <?php if (isAdmin() && isset($all_handovers)): ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#history">
                    <i class="bi bi-clock-history"></i> السجل
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content">
            <!-- Receive Tab -->
            <div class="tab-pane fade show active" id="receive">
                <?php if (!empty($pending_handovers)): ?>
                <div class="content-card mb-4">
                    <h5 class="mb-4"><i class="bi bi-bell me-2 text-warning"></i>شيفتات بانتظار الاستلام (<?= count($pending_handovers) ?>)</h5>
                    <?php foreach ($pending_handovers as $handover): ?>
                    <div class="handover-card pending">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="mb-3">
                                    <i class="bi bi-person me-2"></i>
                                    تم التسليم من: <strong><?= $handover['from_user_name'] ?></strong>
                                    <span class="text-muted">&rarr; إلى: <?= $handover['to_user_name'] ?></span>
                                </h6>
                                <div class="row mb-3">
                                    <div class="col-md-4"><div class="stat-box"><h4 class="text-warning"><?= $handover['open_orders_count'] ?></h4><p>طلبات مفتوحة</p></div></div>
                                    <div class="col-md-4"><div class="stat-box"><h4 class="text-danger"><?= $handover['urgent_orders_count'] ?></h4><p>طلبات عاجلة</p></div></div>
                                    <div class="col-md-4"><div class="stat-box"><h4 class="text-primary"><?= timeAgo($handover['created_at']) ?></h4><p>تاريخ التسليم</p></div></div>
                                </div>
                                <?php if ($handover['critical_notes']): ?>
                                <div class="alert alert-danger"><strong><i class="bi bi-exclamation-triangle me-2"></i>ملاحظات حرجة:</strong><p class="mb-0"><?= nl2br($handover['critical_notes']) ?></p></div>
                                <?php endif; ?>
                                <?php if ($handover['general_notes']): ?>
                                <div class="alert alert-info"><strong><i class="bi bi-info-circle me-2"></i>ملاحظات عامة:</strong><p class="mb-0"><?= nl2br($handover['general_notes']) ?></p></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 d-flex align-items-center justify-content-center">
                                <form method="POST" action="">
                                    <input type="hidden" name="handover_id" value="<?= $handover['id'] ?>">
                                    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-lg me-2"></i>استلام الشيفت</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="content-card mb-4 text-center py-5">
                    <i class="bi bi-check-circle text-success" style="font-size: 64px;"></i>
                    <h5 class="mt-3 text-muted">لا توجد شيفتات بانتظار الاستلام</h5>
                    <p class="text-muted">جميع الشيفتات تم استلامها</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Handover Tab -->
            <div class="tab-pane fade" id="handover">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="content-card">
                            <h5 class="mb-4"><i class="bi bi-arrow-left-right me-2"></i>نموذج تسليم الشيفت</h5>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="handover">
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
                                    <label class="form-label"><i class="bi bi-exclamation-triangle me-1"></i>ملاحظات حرجة</label>
                                    <textarea class="form-control" name="critical_notes" rows="3" placeholder="طلبات مهمة جداً..."></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label"><i class="bi bi-sticky me-1"></i>ملاحظات عامة</label>
                                    <textarea class="form-control" name="general_notes" rows="3" placeholder="أي ملاحظات أخرى..."></textarea>
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn btn-submit btn-lg"><i class="bi bi-check-lg me-2"></i>تسليم الشيفت</button>
                                </div>
                            </form>
                        </div>
                    </div>
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
                                <span class="badge bg-<?= $order['priority'] === 'urgent' ? 'warning' : 'danger' ?>"><?= $order['priority'] === 'urgent' ? 'عاجل' : 'حرج' ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (count($open_orders_list) > 10): ?>
                            <div class="text-center mt-2"><a href="<?= APP_URL ?>/modules/customer-requests/orders.php" class="text-primary">عرض الكل (<?= count($open_orders_list) ?>)</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <?php if (isAdmin() && isset($all_handovers)): ?>
            <div class="tab-pane fade" id="history">
                <div class="content-card">
                    <h5 class="mb-4"><i class="bi bi-clock-history me-2"></i>سجل تسليم الشيفتات</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>التاريخ</th><th>المسلم</th><th>المستلم</th><th>الطلبات المفتوحة</th><th>الحالة</th></tr></thead>
                            <tbody>
                                <?php foreach ($all_handovers as $h): ?>
                                <tr>
                                    <td><?= arabicDate($h['shift_date']) ?></td>
                                    <td><?= $h['from_user_name'] ?></td>
                                    <td><?= $h['to_user_name'] ?></td>
                                    <td><?= $h['open_orders_count'] ?></td>
                                    <td>
                                        <?php if ($h['is_acknowledged']): ?>
                                            <span class="badge bg-success">تم الاستلام</span>
                                            <small class="text-muted"><?= timeAgo($h['acknowledged_at']) ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-warning">بانتظار الاستلام</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
