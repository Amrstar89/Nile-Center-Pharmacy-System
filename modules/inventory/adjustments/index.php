<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'الجرد والتسويات';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = trim($_GET['status'] ?? '');
$store_filter = intval($_GET['store'] ?? 0);

$where = "WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $where .= " AND a.status = ?";
    $params[] = $status_filter;
}

if ($store_filter > 0) {
    $where .= " AND a.store_id = ?";
    $params[] = $store_filter;
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM stock_adjustments a {$where}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Get adjustments
$sql = "SELECT 
    a.*,
    s.store_name, s.store_code,
    b.branch_name,
    u1.full_name as counted_by_name,
    u2.full_name as approved_by_name
FROM stock_adjustments a
LEFT JOIN stores s ON a.store_id = s.id
LEFT JOIN branches b ON s.branch_id = b.id
LEFT JOIN users u1 ON a.counted_by = u1.id
LEFT JOIN users u2 ON a.approved_by = u2.id
{$where}
ORDER BY a.created_at DESC
LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$adjustments = $stmt->fetchAll();

// Get stores for filter
$stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();

// Status labels
$status_labels = [
    'draft' => ['مسودة', 'bg-secondary', 'bi-pencil'],
    'counting' => ['جاري العد', 'bg-warning text-dark', 'bi-clipboard-data'],
    'completed' => ['مكتمل', 'bg-success', 'bi-check-circle'],
    'cancelled' => ['ملغي', 'bg-dark', 'bi-x-octagon']
];

// Type labels
$type_labels = [
    'periodic' => 'جرد دوري',
    'spot' => 'جرد مفاجئ',
    'year_end' => 'جرد نهاية سنة',
    'damage' => 'جرد تالف',
    'expired' => 'جرد هالك'
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
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .adjustment-card { transition: all 0.3s; border-right: 4px solid transparent; }
        .adjustment-card:hover { transform: translateY(-2px); }
        .adjustment-card.status-draft { border-right-color: #6c757d; }
        .adjustment-card.status-counting { border-right-color: var(--warning); }
        .adjustment-card.status-completed { border-right-color: var(--success); }
        .adjustment-card.status-cancelled { border-right-color: var(--danger); }
        .filter-select { border-radius: 25px; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-clipboard-check"></i> <?= $page_title ?></h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> جرد جديد
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <select name="status" class="form-select filter-select">
                                <option value="">-- كل الحالات --</option>
                                <?php foreach ($status_labels as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $label[0] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="store" class="form-select filter-select">
                                <option value="0">-- كل المخازن --</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $store_filter == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Adjustments List -->
            <div class="row">
                <?php foreach ($adjustments as $adj): ?>
                <?php $status_info = $status_labels[$adj['status']] ?? ['غير معروف', 'bg-secondary', 'bi-question']; ?>
                <div class="col-md-6 mb-4">
                    <div class="card adjustment-card status-<?= $adj['status'] ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($adj['adjustment_code']) ?></span>
                                    <span class="badge <?= $status_info[1] ?> ms-2">
                                        <i class="bi <?= $status_info[2] ?>"></i> <?= $status_info[0] ?>
                                    </span>
                                </div>
                                <div>
                                    <a href="view.php?id=<?= $adj['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> عرض
                                    </a>
                                </div>
                            </div>

                            <h5 class="card-title"><?= htmlspecialchars($adj['store_name']) ?></h5>
                            <div class="mb-2">
                                <span class="badge bg-light text-dark"><?= $type_labels[$adj['adjustment_type']] ?? 'غير معروف' ?></span>
                                <?php if ($adj['branch_name']): ?>
                                    <span class="badge bg-light text-dark"><?= htmlspecialchars($adj['branch_name']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="bi bi-boxes"></i> <?= $adj['total_items'] ?> صنف</span>
                                <span><i class="bi bi-cash"></i> <?= number_format($adj['total_variance_cost'], 2) ?> ج</span>
                            </div>

                            <div class="mt-2 text-muted small">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($adj['counted_by_name'] ?? 'غير معروف') ?> | 
                                <i class="bi bi-calendar"></i> <?= date('Y-m-d', strtotime($adj['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($adjustments)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-clipboard" style="font-size: 48px; color: #ddd;"></i>
                            <h5 class="mt-3 text-muted">لا توجد عمليات جرد</h5>
                            <a href="create.php" class="btn btn-primary mt-3">
                                <i class="bi bi-plus-lg"></i> إنشاء جرد جديد
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
                        <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= $store_filter > 0 ? '&store=' . $store_filter : '' ?>">السابق</a></li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= $store_filter > 0 ? '&store=' . $store_filter : '' ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= $store_filter > 0 ? '&store=' . $store_filter : '' ?>">التالي</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
