<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAdmin();

$db = getDB();

// Filters
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT al.*, u.full_name as user_name 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($user_filter) {
    $sql .= " AND al.user_id = ?";
    $params[] = $user_filter;
}
if ($action_filter) {
    $sql .= " AND al.action = ?";
    $params[] = $action_filter;
}
if ($date_from) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY al.created_at DESC LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get users for filter
$users = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// Get unique actions
$actions = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'سجل الحركة';
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
        .content-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .log-row { transition: all 0.2s; }
        .log-row:hover { background: #f8f9fa; }
        .action-badge { padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
 <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">سجل جميع العمليات على النظام</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="content-card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" name="user">
                        <option value="">كل المستخدمين</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>><?= $user['full_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="action">
                        <option value="">كل الإجراءات</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?= $action ?>" <?= $action_filter == $action ? 'selected' : '' ?>><?= $action ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>" placeholder="من">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>" placeholder="إلى">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>بحث</button>
                    <a href="activity-log.php" class="btn btn-outline-secondary">إعادة</a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="content-card">
            <h5 class="mb-4">السجلات (<?= count($logs) ?>)</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المستخدم</th>
                            <th>الإجراء</th>
                            <th>الجدول</th>
                            <th>رقم السجل</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr class="log-row">
                            <td><?= timeAgo($log['created_at']) ?></td>
                            <td><?= $log['user_name'] ?? 'System' ?></td>
                            <td>
                                <span class="action-badge bg-<?= 
                                    $log['action'] === 'login' ? 'success' : 
                                    ($log['action'] === 'logout' ? 'secondary' : 
                                    ($log['action'] === 'create' ? 'primary' : 
                                    ($log['action'] === 'update' ? 'warning' : 
                                    ($log['action'] === 'delete' ? 'danger' : 'info')))) ?>">
                                    <?= $log['action'] ?>
                                </span>
                            </td>
                            <td><?= $log['table_name'] ?? '-' ?></td>
                            <td><?= $log['record_id'] ?? '-' ?></td>
                            <td><code><?= $log['ip_address'] ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (empty($logs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 48px;"></i>
                <p class="text-muted mt-3">لا توجد سجلات</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
