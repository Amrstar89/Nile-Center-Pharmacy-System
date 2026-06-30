<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إدارة المخازن';

// Check which columns exist
$cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores'")->fetchAll(PDO::FETCH_COLUMN);
$has_store_category = in_array('store_category', $cols);
$has_is_main_store = in_array('is_main_store', $cols);

$cols_items = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items'")->fetchAll(PDO::FETCH_COLUMN);
$has_total_cost = in_array('total_cost', $cols_items);

// Get all branches
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Get filter
$branch_filter = intval($_GET['branch'] ?? 0);
$category_filter = trim($_GET['category'] ?? '');
$search = trim($_GET['search'] ?? '');

// Build query conditions
$where = "WHERE s.is_active = 1";
$params = [];

if ($branch_filter > 0) {
    $where .= " AND s.branch_id = ?";
    $params[] = $branch_filter;
}
if (!empty($category_filter) && $has_store_category) {
    $where .= " AND s.store_category = ?";
    $params[] = $category_filter;
}
if (!empty($search)) {
    $where .= " AND (s.store_name LIKE ? OR s.store_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

// Build SELECT based on available columns
$total_cost_select = $has_total_cost 
    ? "(SELECT COALESCE(SUM(total_cost), 0) FROM inventory_items WHERE store_id = s.id) as total_value"
    : "0 as total_value";

$order_by = $has_is_main_store
    ? "CASE WHEN s.is_main_store = 1 THEN 0 ELSE 1 END, b.branch_name, s.store_name"
    : "b.branch_name, s.store_name";

// Get all stores grouped by branch
$sql = "SELECT 
    s.*,
    b.branch_name,
    b.branch_code,
    (SELECT COUNT(DISTINCT product_id) FROM inventory_items WHERE store_id = s.id) as products_count,
    (SELECT COALESCE(SUM(quantity), 0) FROM inventory_items WHERE store_id = s.id) as total_stock,
    {$total_cost_select}
FROM stores s
LEFT JOIN branches b ON s.branch_id = b.id
{$where}
ORDER BY {$order_by}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$all_stores = $stmt->fetchAll();

// Group stores by branch
$grouped_stores = [];
foreach ($all_stores as $store) {
    $branchKey = $store['branch_id'] ?? 0;
    $branchName = $store['branch_name'] ?? 'مركزي';
    if (!isset($grouped_stores[$branchKey])) {
        $grouped_stores[$branchKey] = [
            'branch_name' => $branchName,
            'branch_code' => $store['branch_code'] ?? '',
            'stores' => []
        ];
    }
    $grouped_stores[$branchKey]['stores'][] = $store;
}

// Category labels & styles
$category_labels = [
    'main_store' => ['المخزن الرئيسي', 'bi-building', 'primary', 'border-primary'],
    'warehouse' => ['مستودع', 'bi-box-seam', 'info', 'border-info'],
    'cold' => ['ثلاجة أدوية', 'bi-thermometer-low', 'success', 'border-success'],
    'narcotics' => ['مخدرات ومؤثرات', 'bi-shield-lock', 'danger', 'border-danger'],
    'cosmetics' => ['مستحضرات تجميل', 'bi-magic', 'warning', 'border-warning'],
    'medical_supply' => ['مستلزمات طبية', 'bi-bandaid', 'secondary', 'border-secondary'],
    'surplus' => ['مخزون فائض', 'bi-stack', 'dark', 'border-dark'],
    'damaged' => ['بضاعة تالفة', 'bi-trash', 'danger', 'border-danger'],
    'expired' => ['منتهي الصلاحية', 'bi-calendar-x', 'dark', 'border-dark']
];

// Summary stats
$total_stores = count($all_stores);
$total_main = $has_is_main_store ? count(array_filter($all_stores, fn($s) => $s['is_main_store'])) : 0;
$total_sub = $total_stores - $total_main;
$total_products = array_sum(array_column($all_stores, 'products_count'));

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
        .stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .stat-card i { font-size: 28px; color: var(--primary); margin-bottom: 8px; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: #333; }
        .stat-card .label { font-size: 12px; color: #888; }
        .branch-section { margin-bottom: 30px; }
        .branch-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; border-radius: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .branch-header h4 { margin: 0; font-size: 16px; }
        .branch-header .badge { background: rgba(255,255,255,0.2); color: white; font-size: 12px; }
        .store-card { transition: all 0.3s; border-right: 4px solid transparent; border-radius: 12px; background: white; }
        .store-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); }
        .store-card.main-store { border-right-color: #667eea; }
        .store-card .store-code { background: #f0f0f0; color: #666; padding: 3px 10px; border-radius: 8px; font-size: 11px; font-family: monospace; }
        .store-card .main-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 10px; padding: 3px 8px; border-radius: 6px; }
        .stats-row { display: flex; gap: 12px; margin-top: 12px; }
        .stats-row .stat { text-align: center; padding: 8px 12px; background: #f8f9fa; border-radius: 8px; flex: 1; }
        .stats-row .stat .val { font-size: 16px; font-weight: 700; color: var(--primary); }
        .stats-row .stat .lbl { font-size: 10px; color: #888; }
        .filter-box { border-radius: 25px; }
        .search-box { position: relative; }
        .search-box i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; }
        .search-box input { padding-right: 40px; border-radius: 25px; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-building"></i> <?= $page_title ?></h2>
                <a href="create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> مخزن جديد</a>
            </div>

            <?php if (!$has_store_category || !$has_is_main_store): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    <strong>تنبيه:</strong> الداتابيز محتاجة تحديث. افتح phpMyAdmin → اختار داتابيز nile_center → تبويب SQL → شغل الكود اللي في ملف <code>database/fix_missing_columns.sql</code>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill"></i> تمت العملية بنجاح
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card"><i class="bi bi-building"></i><div class="value"><?= number_format($total_stores) ?></div><div class="label">إجمالي المخازن</div></div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card"><i class="bi bi-building" style="color: var(--primary)"></i><div class="value"><?= number_format($total_main) ?></div><div class="label">مخازن رئيسية</div></div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card"><i class="bi bi-boxes" style="color: var(--secondary)"></i><div class="value"><?= number_format($total_sub) ?></div><div class="label">مخازن فرعية</div></div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card"><i class="bi bi-boxes" style="color: #198754"></i><div class="value"><?= number_format($total_products) ?></div><div class="label">إجمالي الأصناف</div></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" name="search" class="form-control" placeholder="ابحث باسم المخزن أو الكود..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="branch" class="form-select filter-box" onchange="this.form.submit()">
                                <option value="0">كل الفروع</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($has_store_category): ?>
                        <div class="col-md-3">
                            <select name="category" class="form-select filter-box" onchange="this.form.submit()">
                                <option value="">كل التصنيفات</option>
                                <?php foreach ($category_labels as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $category_filter === $key ? 'selected' : '' ?>><?= $label[0] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stores by Branch -->
            <?php if (empty($grouped_stores)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-building" style="font-size: 48px; color: #ddd;"></i>
                        <h5 class="mt-3 text-muted">لا يوجد مخازن</h5>
                        <a href="create.php" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i> إضافة مخزن جديد</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($grouped_stores as $branchKey => $group): ?>
                <div class="branch-section">
                    <div class="branch-header">
                        <div>
                            <i class="bi bi-building"></i>
                            <h4 class="d-inline-block me-2"><?= htmlspecialchars($group['branch_name']) ?></h4>
                            <?php if ($group['branch_code']): ?><span class="badge"><?= htmlspecialchars($group['branch_code']) ?></span><?php endif; ?>
                        </div>
                        <span class="badge"><i class="bi bi-box"></i> <?= count($group['stores']) ?> مخزن</span>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($group['stores'] as $store): 
                            $cat_key = $has_store_category ? ($store['store_category'] ?? '') : '';
                            $cat_info = $category_labels[$cat_key] ?? ['مخزن', 'bi-box', 'secondary', 'border-secondary'];
                            $is_main = $has_is_main_store ? ($store['is_main_store'] ?? 0) : 0;
                            $card_class = $is_main ? 'main-store' : '';
                            $products_count = intval($store['products_count'] ?? 0);
                            $total_stock = floatval($store['total_stock'] ?? 0);
                            $total_value = floatval($store['total_value'] ?? 0);
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card store-card <?= $card_class ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="store-code"><?= htmlspecialchars($store['store_code']) ?></span>
                                            <?php if ($is_main): ?><span class="main-badge ms-1"><i class="bi bi-star-fill"></i> رئيسي</span><?php endif; ?>
                                        </div>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?= $store['id'] ?>" class="btn btn-sm btn-outline-info" title="عرض"><i class="bi bi-eye"></i></a>
                                            <a href="edit.php?id=<?= $store['id'] ?>" class="btn btn-sm btn-outline-warning" title="تعديل"><i class="bi bi-pencil"></i></a>
                                        </div>
                                    </div>
                                    <h5 class="card-title mb-2"><?= htmlspecialchars($store['store_name']) ?></h5>
                                    <?php if ($has_store_category): ?>
                                    <span class="badge bg-<?= $cat_info[2] ?>" style="font-size: 11px; padding: 4px 10px; border-radius: 8px;">
                                        <i class="bi <?= $cat_info[1] ?>"></i> <?= $cat_info[0] ?>
                                    </span>
                                    <?php endif; ?>
                                    <div class="stats-row">
                                        <div class="stat"><div class="val"><?= number_format($products_count) ?></div><div class="lbl">صنف</div></div>
                                        <div class="stat"><div class="val"><?= number_format($total_stock, 0) ?></div><div class="lbl">كمية</div></div>
                                        <?php if ($has_total_cost): ?>
                                        <div class="stat"><div class="val"><?= number_format($total_value, 0) ?></div><div class="lbl">قيمة</div></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($store['notes'])): ?>
                                        <div class="mt-2 text-muted small"><i class="bi bi-info-circle"></i> <?= htmlspecialchars(mb_substr($store['notes'], 0, 60)) ?><?= mb_strlen($store['notes']) > 60 ? '...' : '' ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
