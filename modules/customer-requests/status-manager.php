<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAdmin();

$db = getDB();

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $status_name = $_POST['status_name'] ?? '';
    $status_color = $_POST['status_color'] ?? '#6c757d';
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    $is_final = isset($_POST['is_final']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        if ($id) {
            // Update
            $stmt = $db->prepare("
                UPDATE order_statuses SET status_name = ?, status_color = ?, sort_order = ?, 
                is_default = ?, is_final = ?, is_active = ? WHERE id = ?
            ");
            $stmt->execute([$status_name, $status_color, $sort_order, $is_default, $is_final, $is_active, $id]);
            logActivity('update', 'order_statuses', $id, null, ['status_name' => $status_name]);
            $_SESSION['success'] = 'تم تحديث الحالة بنجاح';
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO order_statuses (status_name, status_color, sort_order, is_default, is_final, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$status_name, $status_color, $sort_order, $is_default, $is_final, $is_active]);
            logActivity('create', 'order_statuses', $db->lastInsertId(), null, ['status_name' => $status_name]);
            $_SESSION['success'] = 'تم إضافة الحالة بنجاح';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/modules/customer-requests/status-manager.php');
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        // Check if status is used
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE status_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];

        if ($count > 0) {
            $_SESSION['error'] = 'لا يمكن حذف الحالة لأنها مستخدمة في ' . $count . ' طلب';
        } else {
            $db->prepare("DELETE FROM order_statuses WHERE id = ?")->execute([$id]);
            logActivity('delete', 'order_statuses', $id);
            $_SESSION['success'] = 'تم حذف الحالة بنجاح';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/modules/customer-requests/status-manager.php');
}

// Get all statuses
$stmt = $db->query("SELECT * FROM order_statuses ORDER BY sort_order, id");
$statuses = $stmt->fetchAll();

// Get status for edit
$edit_status = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM order_statuses WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_status = $stmt->fetch();
}

$page_title = 'إدارة الحالات';
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
        .status-preview { width: 100px; height: 40px; border-radius: 20px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
 <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">إضافة وتعديل وحذف حالات الطلبات</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

        <div class="row">
            <!-- Form -->
            <div class="col-lg-4">
                <div class="content-card">
                    <h5 class="mb-4"><?= $edit_status ? 'تعديل حالة' : 'إضافة حالة جديدة' ?></h5>
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?= $edit_status['id'] ?? '' ?>">

                        <div class="mb-3">
                            <label class="form-label">اسم الحالة</label>
                            <input type="text" class="form-control" name="status_name" value="<?= $edit_status['status_name'] ?? '' ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">اللون</label>
                            <input type="color" class="form-control form-control-color w-100" name="status_color" value="<?= $edit_status['status_color'] ?? '#6c757d' ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الترتيب</label>
                            <input type="number" class="form-control" name="sort_order" value="<?= $edit_status['sort_order'] ?? 0 ?>">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="isDefault" <?= ($edit_status['is_default'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isDefault">حالة افتراضية للطلبات الجديدة</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_final" id="isFinal" <?= ($edit_status['is_final'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isFinal">حالة نهائية (لا يمكن تغييرها بعد ذلك)</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= ($edit_status['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">نشط</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">معاينة</label>
                            <div class="status-preview" id="statusPreview" style="background: <?= $edit_status['status_color'] ?? '#6c757d' ?>">
                                <?= $edit_status['status_name'] ?? 'معاينة' ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-2"></i><?= $edit_status ? 'تحديث' : 'إضافة' ?>
                        </button>

                        <?php if ($edit_status): ?>
                        <a href="status-manager.php" class="btn btn-outline-secondary w-100 mt-2">إلغاء</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="col-lg-8">
                <div class="content-card">
                    <h5 class="mb-4">الحالات الموجودة</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>الترتيب</th>
                                    <th>الحالة</th>
                                    <th>اللون</th>
                                    <th>افتراضي</th>
                                    <th>نهائي</th>
                                    <th>نشط</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statuses as $status): ?>
                                <tr>
                                    <td><?= $status['sort_order'] ?></td>
                                    <td>
                                        <span class="badge" style="background: <?= $status['status_color'] ?>; padding: 8px 15px;">
                                            <?= $status['status_name'] ?>
                                        </span>
                                    </td>
                                    <td><code><?= $status['status_color'] ?></code></td>
                                    <td><?= $status['is_default'] ? '<i class="bi bi-check-circle text-success"></i>' : '-' ?></td>
                                    <td><?= $status['is_final'] ? '<i class="bi bi-check-circle text-success"></i>' : '-' ?></td>
                                    <td><?= $status['is_active'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
                                    <td>
                                        <a href="?edit=<?= $status['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="?delete=<?= $status['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد؟')">
                                            <i class="bi bi-trash"></i>
                                        </a>
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
    <script>
        document.querySelector('input[name="status_name"]').addEventListener('input', function() {
            document.getElementById('statusPreview').textContent = this.value || 'معاينة';
        });
        document.querySelector('input[name="status_color"]').addEventListener('input', function() {
            document.getElementById('statusPreview').style.background = this.value;
        });
    </script>
</body>
</html>
