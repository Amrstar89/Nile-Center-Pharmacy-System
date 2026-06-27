<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'الأرصدة الافتتاحية';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$store_filter = intval($_GET['store'] ?? 0);

$where = "WHERE t.transaction_type = 'opening_balance'";
$params = [];

if ($store_filter > 0) {
    $where .= " AND t.store_id = ?";
    $params[] = $store_filter;
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM inventory_transactions t {$where}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Get opening balances
$sql = "SELECT 
    t.*,
    s.store_name, s.store_code,
    b.branch_name,
    p.product_name, p.product_code, p.manual_code,
    u.full_name as created_by_name
FROM inventory_transactions t
LEFT JOIN stores s ON t.store_id = s.id
LEFT JOIN branches b ON s.branch_id = b.id
LEFT JOIN products p ON t.product_id = p.id
LEFT JOIN users u ON t.created_by = u.id
{$where}
ORDER BY t.created_at DESC
LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$balances = $stmt->fetchAll();

// Get stores for filter
$stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();

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
                <h2><i class="bi bi-journal-plus"></i> <?= $page_title ?></h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> رصيد افتتاحي جديد
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-8">
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

            <!-- Balances Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>المخزن</th>
                                    <th>الصنف</th>
                                    <th>الكمية</th>
                                    <th>التكلفة</th>
                                    <th>القيمة</th>
                                    <th>التاريخ</th>
                                    <th>بواسطة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($balances as $bal): ?>
                                <tr>
                                    <td><?= $bal['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($bal['store_name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($bal['store_code']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($bal['product_name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($bal['product_code'] ?? $bal['manual_code'] ?? 'N/A') ?></small>
                                    </td>
                                    <td><?= number_format($bal['quantity'], 3) ?></td>
                                    <td><?= number_format($bal['unit_cost'], 2) ?> ج</td>
                                    <td><?= number_format($bal['total_cost'], 2) ?> ج</td>
                                    <td><?= date('Y-m-d', strtotime($bal['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($bal['created_by_name'] ?? 'النظام') ?></td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($balances)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="bi bi-journal" style="font-size: 48px; color: #ddd;"></i>
                                        <h5 class="mt-3 text-muted">لا توجد أرصدة افتتاحية</h5>
                                        <a href="create.php" class="btn btn-primary mt-3">
                                            <i class="bi bi-plus-lg"></i> إضافة رصيد افتتاحي
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?><?= $store_filter > 0 ? '&store=' . $store_filter : '' ?>">السابق</a></li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?><?= $store_filter > 0 ? '&store=' . $store_filter : '' ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?><?= $store_filter > 0 ? '&store=' . $store_filter : '' ?>">التالي</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
