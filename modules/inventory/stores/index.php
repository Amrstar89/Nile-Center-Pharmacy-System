<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إدارة المخازن';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search & Filter
$search = trim($_GET['search'] ?? '');
$branch_filter = intval($_GET['branch'] ?? 0);
$type_filter = trim($_GET['type'] ?? '');

$where = "WHERE s.is_active = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (s.store_name LIKE ? OR s.store_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($branch_filter > 0) {
    $where .= " AND s.branch_id = ?";
    $params[] = $branch_filter;
}

if (!empty($type_filter)) {
    $where .= " AND s.store_type = ?";
    $params[] = $type_filter;
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM stores s {$where}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Get stores with branch info and parent store
$sql = "SELECT 
    s.*,
    b.branch_name,
    p.store_name as parent_store_name,
    (SELECT COUNT(DISTINCT product_id) FROM inventory_items WHERE store_id = s.id) as products_count,
    (SELECT SUM(quantity) FROM inventory_items WHERE store_id = s.id) as total_stock
FROM stores s
LEFT JOIN branches b ON s.branch_id = b.id
LEFT JOIN stores p ON s.parent_store_id = p.id
{$where}
ORDER BY 
    CASE s.store_type 
        WHEN 'central_main' THEN 1
        WHEN 'branch_main' THEN 2
        ELSE 3
    END,
    s.branch_id,
    s.store_name
LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$stores = $stmt->fetchAll();

// Get branches for filter
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Store type labels
$type_labels = [
    'central_main' => ['مخزن رئيسي مركزي', 'bi-building', 'primary'],
    'branch_main' => ['مخزن رئيسي فرعي', 'bi-shop', 'info'],
    'sub_store' => ['مخزن فرعي', 'bi-box', 'secondary'],
    'pharmacy' => ['صيدلية', 'bi-capsule', 'success'],
    'warehouse' => ['مستودع', 'bi-box-seam', 'warning'],
    'damaged' => ['مخزن تالف', 'bi-trash', 'danger'],
    'expired' => ['مخزن هالك', 'bi-exclamation-triangle', 'dark']
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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; --info: #0dcaf0; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; color: #fff; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .store-code { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 3px 10px; border-radius: 15px; font-size: 12px; font-weight: bold; }
        .type-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; }
        .store-card { transition: all 0.3s; border-left: 4px solid transparent; }
        .store-card:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .store-card.central { border-left-color: var(--primary); }
        .store-card.branch { border-left-color: var(--info); }
        .store-card.sub { border-left-color: var(--secondary); }
        .store-card.pharmacy { border-left-color: var(--success); }
        .store-card.warehouse { border-left-color: var(--warning); }
        .store-card.damaged { border-left-color: var(--danger); }
        .store-card.expired { border-left-color: #6c757d; }
        .stats-row { display: flex; gap: 15px; margin-top: 10px; }
        .stat-item { text-align: center; padding: 8px 15px; background: #f8f9fa; border-radius: 10px; }
        .stat-value { font-size: 18px; font-weight: bold; color: var(--primary); }
        .stat-label { font-size: 11px; color: #666; }
        .search-box { position: relative; }
        .search-box i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; }
        .search-box input { padding-right: 40px; border-radius: 25px; }
        .filter-select { border-radius: 25px; }
        .action-btns .btn { padding: 3px 8px; font-size: 12px; }
        .branch-badge { background: #e9ecef; color: #495057; padding: 2px 8px; border-radius: 8px; font-size: 12px; }
        .parent-link { color: var(--primary); font-size: 12px; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-building"></i> <?= $page_title ?></h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> مخزن جديد
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" name="search" class="form-control" placeholder="ابحث باسم المخزن أو الكود..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="branch" class="form-select filter-select">
                                <option value="0">-- كل الفروع --</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="type" class="form-select filter-select">
                                <option value="">-- كل الأنواع --</option>
                                <?php foreach ($type_labels as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $type_filter === $key ? 'selected' : '' ?>><?= $label[0] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stores Grid -->
            <div class="row">
                <?php foreach ($stores as $store): ?>
                <?php
                    $store_type = $store['store_type'] ?? 'sub_store';
                    $type_info = $type_labels[$store_type] ?? ['مخزن فرعي', 'bi-box', 'secondary'];
                    $card_class = str_replace(['central_main', 'branch_main', 'sub_store', 'pharmacy', 'warehouse', 'damaged', 'expired'], 
                                             ['central', 'branch', 'sub', 'pharmacy', 'warehouse', 'damaged', 'expired'], $store_type);
                    $products_count = intval($store['products_count'] ?? 0);
                    $total_stock = floatval($store['total_stock'] ?? 0);
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card store-card <?= $card_class ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="store-code"><?= htmlspecialchars($store['store_code']) ?></span>
                                    <span class="badge bg-<?= $type_info[2] ?> type-badge ms-2">
                                        <i class="bi <?= $type_info[1] ?>"></i> <?= $type_info[0] ?>
                                    </span>
                                </div>
                                <div class="action-btns">
                                    <a href="view.php?id=<?= $store['id'] ?>" class="btn btn-sm btn-info" title="عرض">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?= $store['id'] ?>" class="btn btn-sm btn-warning" title="تعديل">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </div>

                            <h5 class="card-title mb-2"><?= htmlspecialchars($store['store_name']) ?></h5>

                            <?php if (!empty($store['branch_name'])): ?>
                                <div class="mb-2">
                                    <span class="branch-badge"><i class="bi bi-building"></i> <?= htmlspecialchars($store['branch_name']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($store['parent_store_name'])): ?>
                                <div class="mb-2 parent-link">
                                    <i class="bi bi-arrow-up"></i> تابع لـ: <?= htmlspecialchars($store['parent_store_name']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="stats-row">
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($products_count) ?></div>
                                    <div class="stat-label">صنف</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($total_stock, 0) ?></div>
                                    <div class="stat-label">كمية</div>
                                </div>
                            </div>

                            <?php if (!empty($store['notes'])): ?>
                                <div class="mt-3 text-muted small">
                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars(mb_substr($store['notes'], 0, 50)) ?><?= mb_strlen($store['notes']) > 50 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($stores)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-building" style="font-size: 48px; color: #ddd;"></i>
                            <h5 class="mt-3 text-muted">لا يوجد مخازن</h5>
                            <a href="create.php" class="btn btn-primary mt-3">
                                <i class="bi bi-plus-lg"></i> إضافة مخزن جديد
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $branch_filter > 0 ? '&branch=' . $branch_filter : '' ?><?= !empty($type_filter) ? '&type=' . urlencode($type_filter) : '' ?>">السابق</a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $branch_filter > 0 ? '&branch=' . $branch_filter : '' ?><?= !empty($type_filter) ? '&type=' . urlencode($type_filter) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $branch_filter > 0 ? '&branch=' . $branch_filter : '' ?><?= !empty($type_filter) ? '&type=' . urlencode($type_filter) : '' ?>">التالي</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>