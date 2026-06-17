<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
requireAdmin();

$db = getDB();

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $time_code = trim($_POST['time_code'] ?? '');
    $time_name = trim($_POST['time_name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        if ($id) {
            $stmt = $db->prepare("UPDATE delivery_times SET time_code = ?, time_name = ?, sort_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$time_code, $time_name, $sort_order, $is_active, $id]);
            logActivity('update', 'delivery_times', $id);
            $_SESSION['success'] = 'تم التحديث بنجاح';
        } else {
            $stmt = $db->prepare("INSERT INTO delivery_times (time_code, time_name, sort_order, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$time_code, $time_name, $sort_order, $is_active]);
            logActivity('create', 'delivery_times', $db->lastInsertId());
            $_SESSION['success'] = 'تم الإضافة بنجاح';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/admin/delivery-times.php');
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $db->prepare("UPDATE delivery_times SET is_active = 0 WHERE id = ?")->execute([$id]);
        logActivity('delete', 'delivery_times', $id);
        $_SESSION['success'] = 'تم التعطيل بنجاح';
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/admin/delivery-times.php');
}

$stmt = $db->query("SELECT * FROM delivery_times ORDER BY sort_order");
$times = $stmt->fetchAll();

$edit_time = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM delivery_times WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_time = $stmt->fetch();
}

$page_title = 'إدارة أوقات التوفير';
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
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h4 { color: white; margin: 0; font-weight: 700; }
        .nav-menu { padding: 15px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 3px solid var(--primary); }
        .nav-link i { width: 25px; margin-left: 10px; font-size: 18px; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #495057; }
        .form-control { border-radius: 10px; padding: 12px 15px; border: 2px solid #e0e0e0; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn-submit { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 10px; padding: 12px 30px; color: white; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-2px); color: white; }
        .table th { background: #f8f9fa; font-weight: 600; }
        .status-active { color: #198754; font-weight: 600; }
        .status-inactive { color: #dc3545; font-weight: 600; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">إضافة وتعديل أوقات التوفير</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted">مدير النظام</small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

        <div class="row">
            <!-- Form -->
            <div class="col-md-4">
                <div class="content-card">
                    <h5 class="mb-4"><?= $edit_time ? 'تعديل وقت' : 'إضافة وقت جديد' ?></h5>
                    <form method="POST" action="">
                        <?php if ($edit_time): ?>
                        <input type="hidden" name="id" value="<?= $edit_time['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">كود الوقت</label>
                            <input type="text" class="form-control" name="time_code" value="<?= $edit_time['time_code'] ?? '' ?>" required placeholder="مثال: 1H">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اسم الوقت</label>
                            <input type="text" class="form-control" name="time_name" value="<?= $edit_time['time_name'] ?? '' ?>" required placeholder="مثال: ساعة">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الترتيب</label>
                            <input type="number" class="form-control" name="sort_order" value="<?= $edit_time['sort_order'] ?? 0 ?>" placeholder="0">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= ($edit_time['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">نشط</label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-submit">
                                <?= $edit_time ? '<i class="bi bi-check-lg me-2"></i>تحديث' : '<i class="bi bi-plus-lg me-2"></i>إضافة' ?>
                            </button>
                            <?php if ($edit_time): ?>
                            <a href="delivery-times.php" class="btn btn-outline-secondary mt-2">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="col-md-8">
                <div class="content-card">
                    <h5 class="mb-4">الأوقات (<?= count($times) ?>)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>كود</th>
                                    <th>الاسم</th>
                                    <th>الترتيب</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($times as $t): ?>
                                <tr>
                                    <td class="fw-bold"><?= $t['time_code'] ?></td>
                                    <td><?= $t['time_name'] ?></td>
                                    <td><?= $t['sort_order'] ?></td>
                                    <td>
                                        <?php if ($t['is_active']): ?>
                                        <span class="status-active"><i class="bi bi-check-circle-fill me-1"></i>نشط</span>
                                        <?php else: ?>
                                        <span class="status-inactive"><i class="bi bi-x-circle-fill me-1"></i>معطل</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <?php if ($t['is_active']): ?>
                                        <a href="?delete=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من تعطيل هذا الوقت؟')"><i class="bi bi-trash"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>