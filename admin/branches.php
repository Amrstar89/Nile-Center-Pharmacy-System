<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
requireAdmin();

$db = getDB();

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $branch_code = trim($_POST['branch_code'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        if ($id) {
            // Update
            $stmt = $db->prepare("
                UPDATE branches SET 
                    branch_code = ?,
                    branch_name = ?,
                    address = ?,
                    phone = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$branch_code, $branch_name, $address, $phone, $is_active, $id]);
            logActivity('update', 'branches', $id);
            $_SESSION['success'] = 'تم تحديث الفرع بنجاح';
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO branches (branch_code, branch_name, address, phone, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$branch_code, $branch_name, $address, $phone, $is_active]);
            logActivity('create', 'branches', $db->lastInsertId());
            $_SESSION['success'] = 'تم إضافة الفرع بنجاح';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/admin/branches.php');
}

// Handle delete (soft delete)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $db->prepare("UPDATE branches SET is_active = 0 WHERE id = ?")->execute([$id]);
        logActivity('delete', 'branches', $id);
        $_SESSION['success'] = 'تم تعطيل الفرع بنجاح';
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/admin/branches.php');
}

// Handle activate
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    try {
        $db->prepare("UPDATE branches SET is_active = 1 WHERE id = ?")->execute([$id]);
        logActivity('activate', 'branches', $id);
        $_SESSION['success'] = 'تم تفعيل الفرع بنجاح';
    } catch (Exception $e) {
        $_SESSION['error'] = 'خطأ: ' . $e->getMessage();
    }
    redirect(APP_URL . '/admin/branches.php');
}

// Get all branches
$stmt = $db->query("SELECT * FROM branches ORDER BY created_at DESC");
$branches = $stmt->fetchAll();

// Get branch for edit
$edit_branch = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_branch = $stmt->fetch();
}

$page_title = 'إدارة الفروع';
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
        .form-label { font-weight: 600; color: #495057; }
        .form-control { border-radius: 10px; padding: 12px 15px; border: 2px solid #e0e0e0; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn-submit { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 10px; padding: 12px 30px; color: white; font-weight: 600; }
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
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">إضافة وتعديل وحذف الفروع</small></div>
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
                    <h5 class="mb-4"><?= $edit_branch ? 'تعديل فرع' : 'إضافة فرع جديد' ?></h5>
                    <form method="POST" action="">
                        <?php if ($edit_branch): ?>
                        <input type="hidden" name="id" value="<?= $edit_branch['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">كود الفرع</label>
                            <input type="text" class="form-control" name="branch_code" value="<?= $edit_branch['branch_code'] ?? '' ?>" required placeholder="مثال: BR01">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اسم الفرع</label>
                            <input type="text" class="form-control" name="branch_name" value="<?= $edit_branch['branch_name'] ?? '' ?>" required placeholder="اسم الفرع">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">العنوان</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="عنوان الفرع"><?= $edit_branch['address'] ?? '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">التليفون</label>
                            <input type="text" class="form-control" name="phone" value="<?= $edit_branch['phone'] ?? '' ?>" placeholder="رقم التليفون">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= ($edit_branch['is_active'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">فرع نشط</label>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-submit">
                                <?= $edit_branch ? '<i class="bi bi-check-lg me-2"></i>تحديث' : '<i class="bi bi-plus-lg me-2"></i>إضافة' ?>
                            </button>
                            <?php if ($edit_branch): ?>
                            <a href="branches.php" class="btn btn-outline-secondary mt-2">إلغاء</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List -->
            <div class="col-md-8">
                <div class="content-card">
                    <h5 class="mb-4">قائمة الفروع (<?= count($branches) ?>)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>كود الفرع</th>
                                    <th>اسم الفرع</th>
                                    <th>العنوان</th>
                                    <th>التليفون</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td class="fw-bold"><?= $branch['branch_code'] ?></td>
                                    <td><?= $branch['branch_name'] ?></td>
                                    <td><?= $branch['address'] ?: '-' ?></td>
                                    <td><?= $branch['phone'] ?: '-' ?></td>
                                    <td>
                                        <?php if ($branch['is_active']): ?>
                                        <span class="status-active"><i class="bi bi-check-circle-fill me-1"></i>نشط</span>
                                        <?php else: ?>
                                        <span class="status-inactive"><i class="bi bi-x-circle-fill me-1"></i>معطل</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="?edit=<?= $branch['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <?php if ($branch['is_active']): ?>
                                        <a href="?delete=<?= $branch['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من تعطيل هذا الفرع؟')"><i class="bi bi-trash"></i></a>
                                        <?php else: ?>
                                        <a href="?activate=<?= $branch['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></a>
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