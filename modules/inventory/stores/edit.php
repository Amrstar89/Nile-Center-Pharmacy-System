<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Get store info
$stmt = $db->prepare("
    SELECT s.*, b.branch_name
    FROM stores s
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$store = $stmt->fetch();

if (!$store) {
    header('Location: index.php?error=' . urlencode('المخزن غير موجود'));
    exit;
}

$page_title = 'تعديل المخزن: ' . $store['store_name'];

// Get branches
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];

        $store_name = trim($_POST['store_name'] ?? '');
        $store_type = $_POST['store_type'] ?? '';
        $store_category = $_POST['store_category'] ?? '';
        $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
        $notes = trim($_POST['notes'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($store_name)) $errors[] = 'اسم المخزن مطلوب';
        if (empty($store_type)) $errors[] = 'نوع المخزن مطلوب';
        if (empty($store_category)) $errors[] = 'تصنيف المخزن مطلوب';

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        $stmt = $db->prepare("
            UPDATE stores 
            SET store_name = ?, store_type = ?, store_category = ?, branch_id = ?, 
                notes = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $store_name, $store_type, $store_category, $branch_id,
            $notes ?: null, $is_active, $id
        ]);

        header("Location: view.php?id={$id}&success=1");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$sub_categories = [
    ['value' => 'warehouse', 'label' => 'مستودع', 'icon' => 'bi-box-seam', 'desc' => 'مخزن للإمداد والتخزين'],
    ['value' => 'expired', 'label' => 'منتهي الصلاحية', 'icon' => 'bi-calendar-x', 'desc' => 'مخزن للأصناف منتهية الصلاحية'],
    ['value' => 'damaged', 'label' => 'بضاعة تالفة', 'icon' => 'bi-trash', 'desc' => 'مخزن للأصناف التالفة'],
    ['value' => 'surplus', 'label' => 'مخزون فائض', 'icon' => 'bi-stack', 'desc' => 'مخزن للمخزون الزائد'],
    ['value' => 'cold', 'label' => 'ثلاجة أدوية', 'icon' => 'bi-thermometer-low', 'desc' => 'مخزن مبرد للأدوية الحساسة'],
    ['value' => 'narcotics', 'label' => 'مخدرات ومؤثرات', 'icon' => 'bi-shield-lock', 'desc' => 'مخزن آمن للمخدرات'],
    ['value' => 'cosmetics', 'label' => 'مستحضرات تجميل', 'icon' => 'bi-magic', 'desc' => 'مخزن لمستحضرات التجميل'],
    ['value' => 'medical_supply', 'label' => 'مستلزمات طبية', 'icon' => 'bi-bandaid', 'desc' => 'مخزن للمستلزمات الطبية']
];

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
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin: 25px 0 20px; }
        .form-label { font-weight: 600; color: #555; }
        .type-card, .cat-card { cursor: pointer; transition: all 0.3s; border: 2px solid #e9ecef; border-radius: 12px; padding: 20px 15px; text-align: center; }
        .type-card:hover, .cat-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .type-card.selected, .cat-card.selected { border-color: var(--primary); background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%); }
        .type-card i { font-size: 28px; margin-bottom: 8px; }
        .cat-card i { font-size: 22px; margin-bottom: 6px; }
        .store-code-display { background: #e8f5e9; color: #2e7d32; padding: 10px 15px; border-radius: 10px; font-size: 14px; font-weight: 600; border: 1px dashed #81c784; display: inline-block; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-pencil"></i> <?= $page_title ?></h2>
                <a href="view.php?id=<?= $id ?>" class="btn btn-secondary"><i class="bi bi-eye"></i> عرض المخزن</a>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> تم التحديث بنجاح</div>
            <?php endif; ?>

            <form method="POST" action="" id="storeForm">
                <div class="card">
                    <div class="card-body">
                        
                        <!-- Store Code (read only) -->
                        <div class="mb-3">
                            <label class="form-label">كود المخزن</label>
                            <div class="store-code-display"><i class="bi bi-lock"></i> <?= htmlspecialchars($store['store_code']) ?> (لا يمكن التعديل)</div>
                        </div>

                        <!-- Store Name -->
                        <h5 class="section-title">اسم المخزن</h5>
                        <div class="mb-3">
                            <label class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                            <input type="text" name="store_name" class="form-control form-control-lg" required 
                                   value="<?= htmlspecialchars($store['store_name']) ?>">
                        </div>

                        <!-- Store Type -->
                        <h5 class="section-title">نوع المخزن</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="type-card <?= $store['store_type'] === 'main' ? 'selected' : '' ?>" onclick="selectType('main')">
                                    <input type="radio" name="store_type" value="main" class="d-none" <?= $store['store_type'] === 'main' ? 'checked' : '' ?>>
                                    <i class="bi bi-building text-primary"></i>
                                    <h6>مخزن رئيسي</h6>
                                    <small class="text-muted">المخزن الأساسي للفرع</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="type-card <?= $store['store_type'] === 'sub' ? 'selected' : '' ?>" onclick="selectType('sub')">
                                    <input type="radio" name="store_type" value="sub" class="d-none" <?= $store['store_type'] === 'sub' ? 'checked' : '' ?>>
                                    <i class="bi bi-boxes text-secondary"></i>
                                    <h6>مخزن فرعي</h6>
                                    <small class="text-muted">مخزن إضافي تابع للرئيسي</small>
                                </div>
                            </div>
                        </div>

                        <!-- Category -->
                        <h5 class="section-title">تصنيف المخزن</h5>
                        <div class="row g-3" id="categoryContainer">
                            <?php foreach ($sub_categories as $cat): ?>
                            <div class="col-md-3 <?= $store['store_type'] === 'main' ? 'd-none' : '' ?>" data-sub-cat>
                                <div class="cat-card <?= $store['store_category'] === $cat['value'] ? 'selected' : '' ?>" onclick="selectCategory('<?= $cat['value'] ?>')">
                                    <input type="radio" name="store_category" value="<?= $cat['value'] ?>" class="d-none" <?= $store['store_category'] === $cat['value'] ? 'checked' : '' ?>>
                                    <i class="bi <?= $cat['icon'] ?> text-secondary"></i>
                                    <h6><?= $cat['label'] ?></h6>
                                    <small class="text-muted"><?= $cat['desc'] ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Main store category -->
                            <div class="col-md-3 <?= $store['store_type'] === 'sub' ? 'd-none' : '' ?>" id="mainCatCard">
                                <div class="cat-card <?= $store['store_category'] === 'main_store' ? 'selected' : '' ?>" onclick="selectCategory('main_store')">
                                    <input type="radio" name="store_category" value="main_store" class="d-none" <?= $store['store_category'] === 'main_store' ? 'checked' : '' ?>>
                                    <i class="bi bi-building text-primary"></i>
                                    <h6>المخزن الرئيسي</h6>
                                    <small class="text-muted">المخزن الأساسي للفرع</small>
                                </div>
                            </div>
                        </div>

                        <!-- Branch -->
                        <h5 class="section-title">الفرع التابع</h5>
                        <div class="mb-3">
                            <label class="form-label">الفرع <span class="text-danger">*</span></label>
                            <select name="branch_id" class="form-select form-select-lg" required>
                                <option value="">-- اختر الفرع --</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>" <?= $store['branch_id'] == $branch['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($branch['branch_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Notes -->
                        <h5 class="section-title">ملاحظات</h5>
                        <div class="mb-3">
                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($store['notes'] ?? '') ?></textarea>
                        </div>

                        <!-- Active -->
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" <?= $store['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">المخزن نشط</label>
                        </div>

                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> حفظ التعديلات</button>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-lg">إلغاء</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectType(type) {
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
            
            const isMain = (type === 'main');
            document.querySelectorAll('[data-sub-cat]').forEach(el => el.classList.toggle('d-none', isMain));
            document.getElementById('mainCatCard').classList.toggle('d-none', !isMain);
            
            if (isMain) {
                setTimeout(() => {
                    const mc = document.getElementById('mainCatCard').querySelector('.cat-card');
                    document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('selected'));
                    document.querySelectorAll('input[name="store_category"]').forEach(r => r.checked = false);
                    mc.classList.add('selected');
                    mc.querySelector('input').checked = true;
                }, 50);
            }
        }
        
        function selectCategory(val) {
            document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</body>
</html>
