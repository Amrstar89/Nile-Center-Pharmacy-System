<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'تعديل المخزن';

$store_id = intval($_GET['id'] ?? 0);
if ($store_id <= 0) {
    header("Location: index.php");
    exit;
}

// Get store data
$stmt = $db->prepare("SELECT * FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$store = $stmt->fetch();

if (!$store) {
    header("Location: index.php");
    exit;
}

// Get branches and parent stores
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

$parent_stores = $db->query("
    SELECT s.id, s.store_name, s.store_type, b.branch_name, s.branch_id 
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 AND s.store_type IN ('central_main', 'branch_main') AND s.id != {$store_id}
    ORDER BY s.store_type, s.store_name
")->fetchAll();

// Store types
$store_types = [
    'central_main' => 'مخزن رئيسي مركزي',
    'branch_main' => 'مخزن رئيسي فرعي',
    'sub_store' => 'مخزن فرعي',
    'pharmacy' => 'صيدلية',
    'warehouse' => 'مستودع',
    'damaged' => 'مخزن تالف',
    'expired' => 'مخزن هالك'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];

        $store_code = trim($_POST['store_code'] ?? '');
        $store_name = trim($_POST['store_name'] ?? '');
        $store_type = $_POST['store_type'] ?? '';
        $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
        $parent_store_id = !empty($_POST['parent_store_id']) ? intval($_POST['parent_store_id']) : null;
        $is_main = isset($_POST['is_main']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($store_code)) {
            $errors[] = 'كود المخزن مطلوب';
        } else {
            $stmt = $db->prepare("SELECT id FROM stores WHERE store_code = ? AND id != ?");
            $stmt->execute([$store_code, $store_id]);
            if ($stmt->fetch()) {
                $errors[] = 'كود المخزن موجود بالفعل';
            }
        }

        if (empty($store_name)) {
            $errors[] = 'اسم المخزن مطلوب';
        }

        if (empty($store_type)) {
            $errors[] = 'نوع المخزن مطلوب';
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        $stmt = $db->prepare("
            UPDATE stores SET 
                store_code = ?, store_name = ?, store_type = ?, branch_id = ?, 
                parent_store_id = ?, is_main = ?, is_active = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $store_code,
            $store_name,
            $store_type,
            $branch_id,
            $parent_store_id,
            $is_main,
            $is_active,
            $notes ?: null,
            $store_id
        ]);

        header("Location: index.php?updated=1");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../../includes/sidebar.php';
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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin-bottom: 20px; }
        .form-label { font-weight: 600; color: #555; }
        .store-type-card { cursor: pointer; transition: all 0.3s; border: 2px solid #e9ecef; border-radius: 10px; padding: 15px; text-align: center; }
        .store-type-card:hover { border-color: var(--primary); }
        .store-type-card.selected { border-color: var(--primary); background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%); }
        .store-type-card i { font-size: 24px; margin-bottom: 8px; color: var(--primary); }
        .store-type-card h6 { font-size: 14px; margin: 0; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-pencil-square"></i> <?= $page_title ?></h2>
                <div>
                    <a href="view.php?id=<?= $store_id ?>" class="btn btn-info me-2"><i class="bi bi-eye"></i> عرض</a>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
                </div>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="storeForm">
                <div class="card">
                    <div class="card-body">
                        <h5 class="section-title">معلومات المخزن الأساسية</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">كود المخزن <span class="text-danger">*</span></label>
                                    <input type="text" name="store_code" class="form-control" required value="<?= htmlspecialchars($store['store_code']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                                    <input type="text" name="store_name" class="form-control" required value="<?= htmlspecialchars($store['store_name']) ?>">
                                </div>
                            </div>
                        </div>

                        <h5 class="section-title mt-4">نوع المخزن</h5>
                        <div class="row">
                            <?php foreach ($store_types as $key => $label): ?>
                            <?php
                                $icon = match($key) {
                                    'central_main' => 'bi-building',
                                    'branch_main' => 'bi-shop',
                                    'sub_store' => 'bi-box',
                                    'pharmacy' => 'bi-capsule',
                                    'warehouse' => 'bi-box-seam',
                                    'damaged' => 'bi-trash',
                                    'expired' => 'bi-exclamation-triangle',
                                    default => 'bi-building'
                                };
                            ?>
                            <div class="col-md-3 mb-3">
                                <div class="store-type-card <?= $store['store_type'] === $key ? 'selected' : '' ?>" onclick="selectType('<?= $key ?>')">
                                    <i class="bi <?= $icon ?>"></i>
                                    <h6><?= $label ?></h6>
                                    <input type="radio" name="store_type" value="<?= $key ?>" class="d-none" <?= $store['store_type'] === $key ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <h5 class="section-title mt-4">الهيكل التنظيمي</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">الفرع التابع</label>
                                    <select name="branch_id" id="branch_id" class="form-select">
                                        <option value="">-- غير تابع لفرع --</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?= $branch['id'] ?>" <?= $store['branch_id'] == $branch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['branch_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">المخزن الأب</label>
                                    <select name="parent_store_id" id="parent_store_id" class="form-select">
                                        <option value="">-- بدون مخزن أب --</option>
                                        <?php foreach ($parent_stores as $parent): ?>
                                            <option value="<?= $parent['id'] ?>" <?= $store['parent_store_id'] == $parent['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($parent['store_name']) ?> <?= $parent['branch_name'] ? '(' . htmlspecialchars($parent['branch_name']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input type="checkbox" name="is_main" value="1" class="form-check-input" id="is_main" <?= $store['is_main'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_main">هذا هو المخزن الرئيسي للفرع/الشركة</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" <?= $store['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">مخزن نشط</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">ملاحظات</label>
                                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($store['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ التعديلات
                        </button>
                        <a href="view.php?id=<?= $store_id ?>" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectType(type) {
            document.querySelectorAll('.store-type-card').forEach(card => card.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</body>
</html>
