<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

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
    redirect(APP_URL . '/modules/customer-requests/shift-receive.php');
}

// Get unacknowledged handovers for current user
$stmt = $db->prepare("SELECT sh.*, u.full_name as from_user_name, u2.full_name as to_user_name 
    FROM shift_handovers sh 
    JOIN users u ON sh.from_user = u.id 
    JOIN users u2 ON sh.to_user = u2.id 
    WHERE sh.to_user = ? AND sh.is_acknowledged = 0 
    ORDER BY sh.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$pending_handovers = $stmt->fetchAll();

// Get all handovers (for admin)
if (isAdmin()) {
    $stmt = $db->query("SELECT sh.*, u.full_name as from_user_name, u2.full_name as to_user_name 
        FROM shift_handovers sh 
        JOIN users u ON sh.from_user = u.id 
        JOIN users u2 ON sh.to_user = u2.id 
        ORDER BY sh.created_at DESC LIMIT 50");
    $all_handovers = $stmt->fetchAll();
}

$page_title = 'استلام الشيفت';
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
        .handover-card { border: 2px solid #e0e0e0; border-radius: 15px; padding: 25px; margin-bottom: 20px; transition: all 0.3s; }
        .handover-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .handover-card.pending { border-color: #ffc107; background: #fffdf5; }
        .handover-card.acknowledged { border-color: #198754; background: #f8fff9; }
        .stat-box { background: #f8f9fa; border-radius: 10px; padding: 15px; text-align: center; }
        .stat-box h4 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-box p { margin: 0; color: #6c757d; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
 <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">استلام الشيفت من الصيدلي السابق</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

        <!-- Pending Handovers -->
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
                            <span class="text-muted">→ إلى: <?= $handover['to_user_name'] ?></span>
                        </h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="stat-box">
                                    <h4 class="text-warning"><?= $handover['open_orders_count'] ?></h4>
                                    <p>طلبات مفتوحة</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-box">
                                    <h4 class="text-danger"><?= $handover['urgent_orders_count'] ?></h4>
                                    <p>طلبات عاجلة</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-box">
                                    <h4 class="text-primary"><?= timeAgo($handover['created_at']) ?></h4>
                                    <p>تاريخ التسليم</p>
                                </div>
                            </div>
                        </div>

                        <?php if ($handover['critical_notes']): ?>
                        <div class="alert alert-danger">
                            <strong><i class="bi bi-exclamation-triangle me-2"></i>ملاحظات حرجة:</strong>
                            <p class="mb-0"><?= nl2br($handover['critical_notes']) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if ($handover['general_notes']): ?>
                        <div class="alert alert-info">
                            <strong><i class="bi bi-info-circle me-2"></i>ملاحظات عامة:</strong>
                            <p class="mb-0"><?= nl2br($handover['general_notes']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 d-flex align-items-center justify-content-center">
                        <form method="POST" action="">
                            <input type="hidden" name="handover_id" value="<?= $handover['id'] ?>">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-check-lg me-2"></i>استلام الشيفت
                            </button>
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

        <!-- All Handovers (Admin Only) -->
        <?php if (isAdmin() && isset($all_handovers)): ?>
        <div class="content-card">
            <h5 class="mb-4"><i class="bi bi-clock-history me-2"></i>سجل تسليم الشيفتات</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المسلم</th>
                            <th>المستلم</th>
                            <th>الطلبات المفتوحة</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
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
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
