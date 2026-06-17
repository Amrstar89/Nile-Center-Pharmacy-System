<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
requireAdmin();

$db = getDB();

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $username = $_POST['username'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'pharmacist';
    $branch_code = $_POST['branch_code'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        if ($id) {
            // Update
            if ($password) {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                $stmt = $db->prepare("
                    UPDATE users SET username = ?, password = ?, full_name = ?, role = ?, 
                    branch_code = ?, phone = ?, is_active = ? WHERE id = ?
                ");
                $stmt->execute([$username, $hashed, $full_name, $role, $branch_code, $phone, $is_active, $id]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users SET username = ?, full_name = ?, role = ?, 
                    branch_code = ?, phone = ?, is_active = ? WHERE id = ?
                ");
                $stmt->execute([$username, $full_name, $role, $branch_code, $phone, $is_active, $id]);
            }
            logActivity('update', 'users', $id);
            $_SESSION['success'] = 'تم تحديث المستخدم بنجاح';
        } else {
            // Insert
            if (empty($password)) {
                $_SESSION['error'] = 'كلمة المرور مطلوبة للمستخدم الجديد';
                redirect(APP_URL . '/admin/users.php');
            }
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, role, branch_code, phone, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hashed, $full_name, $role, $branch_code, $phone, $is_active]);
            logActivity('create', 'users', $db->lastInsertId());
            $_SESSION['success'] = 'تم إضافة المستخدم بنجاح';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/admin/users.php');
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error'] = 'لا يمكن حذف حسابك الحالي';
    } else {
        try {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            logActivity('delete', 'users', $id);
            $_SESSION['success'] = 'تم حذف المستخدم بنجاح';
        } catch (Exception $e) {
            $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
        }
    }
    redirect(APP_URL . '/admin/users.php');
}

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get user for edit
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

$page_title = 'إدارة المستخدمين';
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
        .role-badge { padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; }
        .role-admin { background: #f8d7da; color: #721c24; }
        .role-pharmacist { background: #d1ecf1; color: #0c5460; }
        .role-purchaser { background: #fff3cd; color: #856404; }
        .role-cashier { background: #d4edda; color: #155724; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">إضافة وتعديل وحذف المستخدمين</small></div>
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
                    <h5 class="mb-4"><?= $edit_user ? 'تعديل مستخدم' : 'إضافة مستخدم جديد' ?></h5>
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?= $edit_user['id'] ?? '' ?>">

                        <div class="mb-3">
                            <label class="form-label">اسم المستخدم</label>
                            <input type="text" class="form-control" name="username" value="<?= $edit_user['username'] ?? '' ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الاسم الكامل</label>
                            <input type="text" class="form-control" name="full_name" value="<?= $edit_user['full_name'] ?? '' ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">كلمة المرور <?= $edit_user ? '(اتركها فارغة إذا لم ترد التغيير)' : '' ?></label>
                            <input type="password" class="form-control" name="password" <?= !$edit_user ? 'required' : '' ?>>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الدور</label>
                            <select class="form-select" name="role" required>
                                <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>مدير النظام</option>
                                <option value="pharmacist" <?= ($edit_user['role'] ?? '') === 'pharmacist' ? 'selected' : '' ?>>صيدلي</option>
                                <option value="purchaser" <?= ($edit_user['role'] ?? '') === 'purchaser' ? 'selected' : '' ?>>مشتريات</option>
                                <option value="cashier" <?= ($edit_user['role'] ?? '') === 'cashier' ? 'selected' : '' ?>>كاشير</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">كود الفرع (للربط المستقبلي)</label>
                            <input type="text" class="form-control" name="branch_code" value="<?= $edit_user['branch_code'] ?? '' ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="text" class="form-control" name="phone" value="<?= $edit_user['phone'] ?? '' ?>">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= ($edit_user['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">نشط</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-2"></i><?= $edit_user ? 'تحديث' : 'إضافة' ?>
                        </button>

                        <?php if ($edit_user): ?>
                        <a href="users.php" class="btn btn-outline-secondary w-100 mt-2">إلغاء</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="col-lg-8">
                <div class="content-card">
                    <h5 class="mb-4">المستخدمين (<?= count($users) ?>)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>الاسم</th>
                                    <th>الدور</th>
                                    <th>الفرع</th>
                                    <th>آخر دخول</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['username'] ?></td>
                                    <td><?= $user['full_name'] ?></td>
                                    <td>
                                        <span class="role-badge role-<?= $user['role'] ?>">
                                            <?= $user['role'] === 'admin' ? 'مدير' : ($user['role'] === 'pharmacist' ? 'صيدلي' : ($user['role'] === 'purchaser' ? 'مشتريات' : 'كاشير')) ?>
                                        </span>
                                    </td>
                                    <td><?= $user['branch_code'] ?? '-' ?></td>
                                    <td><?= $user['last_login'] ? timeAgo($user['last_login']) : 'لم يسبق له' ?></td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">نشط</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">معطل</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من حذف <?= $user['full_name'] ?>؟')">
                                            <i class="bi bi-trash"></i>
                                        </a>
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
