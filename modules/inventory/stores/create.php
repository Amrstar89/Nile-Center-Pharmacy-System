<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إضافة مخزن جديد';

// Get branches
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Generate auto store code
function generateStoreCode($db, $branch_id, $store_type) {
    $prefix = $store_type === 'main' ? 'STR' : 'SUB';
    
    if ($branch_id) {
        $branch = $db->prepare("SELECT branch_code FROM branches WHERE id = ?");
        $branch->execute([$branch_id]);
        $bc = $branch->fetch();
        if ($bc && $bc['branch_code']) {
            $prefix .= '-' . $bc['branch_code'];
        }
    }
    
    // Get next number for this prefix
    $check = $db->prepare("SELECT store_code FROM stores WHERE store_code LIKE ? ORDER BY store_code DESC LIMIT 1");
    $check->execute([$prefix . '-%']);
    $last = $check->fetch();
    
    $nextNum = 1;
    if ($last) {
        $parts = explode('-', $last['store_code']);
        $lastNum = (int) end($parts);
        $nextNum = $lastNum + 1;
    }
    
    return $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];

        $store_name = trim($_POST['store_name'] ?? '');
        $store_type = $_POST['store_type'] ?? '';
        $store_category = $_POST['store_category'] ?? '';
        $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
        $notes = trim($_POST['notes'] ?? '');

        // Validation
        if (empty($store_name)) {
            $errors[] = 'اسم المخزن مطلوب';
        }

        if (empty($store_type)) {
            $errors[] = 'نوع المخزن مطلوب';
        }

        if (empty($store_category)) {
            $errors[] = 'تصنيف المخزن مطلوب';
        }

        // Auto generate store code
        $store_code = generateStoreCode($db, $branch_id, $store_type);
        
        // Check uniqueness (just in case)
        $stmt = $db->prepare("SELECT id FROM stores WHERE store_code = ?");
        $stmt->execute([$store_code]);
        if ($stmt->fetch()) {
            // If duplicate, add random suffix and retry once
            $store_code .= '-' . date('His');
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        $stmt = $db->prepare("
            INSERT INTO stores (store_code, store_name, store_type, store_category, branch_id, is_main_store, is_active, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, 0, 1, ?, ?, NOW())
        ");
        $stmt->execute([
            $store_code,
            $store_name,
            $store_type,
            $store_category,
            $branch_id,
            $notes ?: null,
            $_SESSION['user_id'] ?? 1
        ]);

        header("Location: index.php?success=1");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Store categories based on type
$main_categories = [
    ['value' => 'main_store', 'label' => 'المخزن الرئيسي', 'icon' => 'bi-building', 'desc' => 'المخزن الأساسي للفرع']
];

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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin: 25px 0 20px; }
        .form-label { font-weight: 600; color: #555; }
        
        /* Store Type Cards */
        .type-card { cursor: pointer; transition: all 0.3s; border: 2px solid #e9ecef; border-radius: 12px; padding: 25px 15px; text-align: center; height: 100%; }
        .type-card:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(102,126,234,0.2); }
        .type-card.selected { border-color: var(--primary); background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%); }
        .type-card i { font-size: 32px; margin-bottom: 10px; }
        .type-card h5 { font-size: 16px; margin: 0 0 5px; }
        .type-card p { font-size: 12px; color: #888; margin: 0; }
        .type-card.main-type i { color: var(--primary); }
        .type-card.sub-type i { color: var(--secondary); }
        
        /* Category Cards */
        .cat-card { cursor: pointer; transition: all 0.3s; border: 2px solid #e9ecef; border-radius: 10px; padding: 18px 12px; text-align: center; height: 100%; }
        .cat-card:hover { border-color: var(--secondary); transform: translateY(-2px); }
        .cat-card.selected { border-color: var(--secondary); background: linear-gradient(135deg, rgba(118,75,162,0.1) 0%, rgba(102,126,234,0.1) 100%); }
        .cat-card i { font-size: 24px; margin-bottom: 8px; color: var(--secondary); }
        .cat-card h6 { font-size: 13px; margin: 0 0 3px; }
        .cat-card p { font-size: 11px; color: #999; margin: 0; }
        .cat-card.disabled { opacity: 0.4; pointer-events: none; }
        
        .auto-code-badge { 
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); 
            color: #2e7d32; 
            padding: 10px 15px; 
            border-radius: 10px; 
            font-size: 14px; 
            font-weight: 600;
            border: 1px dashed #81c784;
        }
        .auto-code-badge i { margin-left: 5px; }
        
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-plus-lg"></i> <?= $page_title ?></h2>
                <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="storeForm">
                <div class="card">
                    <div class="card-body">
                        
                        <!-- Store Name -->
                        <h5 class="section-title">اسم المخزن</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">اسم المخزن <span class="text-danger">*</span></label>
                                    <input type="text" name="store_name" class="form-control form-control-lg" required 
                                           placeholder="مثال: مخزن المستورد، مخزن الأدوية الباردة..." 
                                           value="<?= isset($_POST['store_name']) ? htmlspecialchars($_POST['store_name']) : '' ?>">
                                    <div class="form-text text-muted">اسم وصفي يعبر عن غرض المخزن</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">كود المخزن</label>
                                    <div class="auto-code-badge">
                                        <i class="bi bi-magic"></i> توليد تلقائي
                                    </div>
                                    <div class="form-text text-muted">يتم توليد الكود تلقائياً حسب الفرع والنوع</div>
                                </div>
                            </div>
                        </div>

                        <!-- Store Type -->
                        <h5 class="section-title">نوع المخزن</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="type-card main-type <?= (isset($_POST['store_type']) && $_POST['store_type'] === 'main') ? 'selected' : '' ?>" 
                                     onclick="selectType('main')">
                                    <input type="radio" name="store_type" value="main" class="d-none" 
                                           <?= (isset($_POST['store_type']) && $_POST['store_type'] === 'main') ? 'checked' : '' ?>>
                                    <i class="bi bi-building"></i>
                                    <h5>مخزن رئيسي</h5>
                                    <p>المخزن الأساسي للفرع - يتم إنشاؤه أولاً</p>
                                    <span class="badge bg-primary">أول مخزن في الفرع</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="type-card sub-type <?= (!isset($_POST['store_type']) || $_POST['store_type'] === 'sub') ? 'selected' : '' ?>" 
                                     onclick="selectType('sub')">
                                    <input type="radio" name="store_type" value="sub" class="d-none" 
                                           <?= (!isset($_POST['store_type']) || $_POST['store_type'] === 'sub') ? 'checked' : '' ?>>
                                    <i class="bi bi-boxes"></i>
                                    <h5>مخزن فرعي</h5>
                                    <p>مخزن إضافي تابع للمخزن الرئيسي</p>
                                    <span class="badge bg-secondary">يمكن إضافة أكثر من واحد</span>
                                </div>
                            </div>
                        </div>

                        <!-- Store Category -->
                        <h5 class="section-title">تصنيف المخزن</h5>
                        <div class="row g-3" id="categoryContainer">
                            <?php foreach ($sub_categories as $cat): ?>
                            <div class="col-md-3">
                                <div class="cat-card <?= (isset($_POST['store_category']) && $_POST['store_category'] === $cat['value']) ? 'selected' : '' ?>" 
                                     onclick="selectCategory('<?= $cat['value'] ?>')"
                                     data-main="0">
                                    <input type="radio" name="store_category" value="<?= $cat['value'] ?>" class="d-none"
                                           <?= (isset($_POST['store_category']) && $_POST['store_category'] === $cat['value']) ? 'checked' : '' ?>>
                                    <i class="bi <?= $cat['icon'] ?>"></i>
                                    <h6><?= $cat['label'] ?></h6>
                                    <p><?= $cat['desc'] ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Main store category (hidden by default) -->
                            <div class="col-md-3 d-none" id="mainCatCard">
                                <div class="cat-card <?= (isset($_POST['store_category']) && $_POST['store_category'] === 'main_store') ? 'selected' : '' ?>"
                                     onclick="selectCategory('main_store')"
                                     data-main="1">
                                    <input type="radio" name="store_category" value="main_store" class="d-none"
                                           <?= (isset($_POST['store_category']) && $_POST['store_category'] === 'main_store') ? 'checked' : '' ?>>
                                    <i class="bi bi-building"></i>
                                    <h6>المخزن الرئيسي</h6>
                                    <p>المخزن الأساسي للفرع</p>
                                </div>
                            </div>
                        </div>

                        <!-- Branch -->
                        <h5 class="section-title">الفرع التابع</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">الفرع <span class="text-danger">*</span></label>
                                    <select name="branch_id" id="branch_id" class="form-select form-select-lg" required>
                                        <option value="">-- اختر الفرع --</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?= $branch['id'] ?>" <?= (isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($branch['branch_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted">كل مخزن يتبع لفرع معين</div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <h5 class="section-title">ملاحظات</h5>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات إضافية عن المخزن..."><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ المخزن
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg">
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
            document.querySelectorAll('.type-card').forEach(card => card.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
            
            // Toggle categories visibility
            const isMain = (type === 'main');
            const subCats = document.querySelectorAll('.cat-card[data-main="0"]');
            const mainCatCard = document.getElementById('mainCatCard');
            
            subCats.forEach(card => {
                card.closest('.col-md-3').classList.toggle('d-none', isMain);
                if (isMain) card.classList.remove('selected');
            });
            
            mainCatCard.classList.toggle('d-none', !isMain);
            if (isMain) {
                // Auto-select main_store category
                setTimeout(() => {
                    const mainCard = mainCatCard.querySelector('.cat-card');
                    mainCard.click();
                }, 50);
            } else {
                document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('selected'));
                document.querySelectorAll('input[name="store_category"]').forEach(r => r.checked = false);
            }
        }
        
        function selectCategory(value) {
            document.querySelectorAll('.cat-card').forEach(card => card.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
        
        // Form validation
        document.getElementById('storeForm').addEventListener('submit', function(e) {
            const storeType = document.querySelector('input[name="store_type"]:checked');
            const storeCategory = document.querySelector('input[name="store_category"]:checked');
            const branchId = document.getElementById('branch_id').value;
            
            if (!storeType) {
                e.preventDefault();
                alert('يرجى اختيار نوع المخزن');
                return false;
            }
            if (!branchId) {
                e.preventDefault();
                alert('يرجى اختيار الفرع');
                return false;
            }
            if (!storeCategory) {
                e.preventDefault();
                alert('يرجى اختيار تصنيف المخزن');
                return false;
            }
        });
    </script>
</body>
</html>
