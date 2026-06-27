<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'التحويلات بين المخازن';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$status_filter = trim($_GET['status'] ?? '');
$from_store = intval($_GET['from_store'] ?? 0);
$to_store = intval($_GET['to_store'] ?? 0);

$where = "WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
    $where .= " AND t.status = ?";
    $params[] = $status_filter;
}

if ($from_store > 0) {
    $where .= " AND t.from_store_id = ?";
    $params[] = $from_store;
}

if ($to_store > 0) {
    $where .= " AND t.to_store_id = ?";
    $params[] = $to_store;
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM inventory_transfers t {$where}";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Get transfers
$sql = "SELECT 
    t.*,
    fs.store_name as from_store_name, fs.store_code as from_store_code,
    ts.store_name as to_store_name, ts.store_code as to_store_code,
    fb.branch_name as from_branch_name,
    tb.branch_name as to_branch_name,
    u1.full_name as requested_by_name,
    u2.full_name as approved_by_name
FROM inventory_transfers t
LEFT JOIN stores fs ON t.from_store_id = fs.id
LEFT JOIN stores ts ON t.to_store_id = ts.id
LEFT JOIN branches fb ON t.from_branch_id = fb.id
LEFT JOIN branches tb ON t.to_branch_id = tb.id
LEFT JOIN users u1 ON t.requested_by = u1.id
LEFT JOIN users u2 ON t.approved_by = u2.id
{$where}
ORDER BY t.created_at DESC
LIMIT {$per_page} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();

// Get stores for filter
$stores = $db->query("SELECT id, store_name, store_code FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();

// Status labels
$status_labels = [
    'draft' => ['مسودة', 'bg-secondary', 'bi-pencil'],
    'pending' => ['معلق', 'bg-warning text-dark', 'bi-clock'],
    'approved' => ['معتمد', 'bg-info', 'bi-check-circle'],
    'shipped' => ['مرسل', 'bg-primary', 'bi-truck'],
    'partial_received' => ['مستلم جزئي', 'bg-warning', 'bi-box-seam'],
    'received' => ['مستلم', 'bg-success', 'bi-check-all'],
    'rejected' => ['مرفوض', 'bg-danger', 'bi-x-circle'],
    'cancelled' => ['ملغي', 'bg-dark', 'bi-x-octagon']
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
        .transfer-card { transition: all 0.3s; border-right: 4px solid transparent; }
        .transfer-card:hover { transform: translateY(-2px); }
        .transfer-card.status-draft { border-right-color: #6c757d; }
        .transfer-card.status-pending { border-right-color: var(--warning); }
        .transfer-card.status-approved { border-right-color: var(--info); }
        .transfer-card.status-shipped { border-right-color: var(--primary); }
        .transfer-card.status-received { border-right-color: var(--success); }
        .transfer-card.status-rejected { border-right-color: var(--danger); }
        .store-arrow { color: var(--primary); font-size: 20px; }
        .action-btns .btn { padding: 3px 8px; font-size: 12px; }
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
                <h2><i class="bi bi-arrow-left-right"></i> <?= $page_title ?></h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> تحويل جديد
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <select name="status" class="form-select filter-select">
                                <option value="">-- كل الحالات --</option>
                                <?php foreach ($status_labels as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $label[0] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="from_store" class="form-select filter-select">
                                <option value="0">-- من مخزن --</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $from_store == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="to_store" class="form-select filter-select">
                                <option value="0">-- إلى مخزن --</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $to_store == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transfers List -->
            <div class="row">
                <?php foreach ($transfers as $transfer): ?>
                <?php $status_info = $status_labels[$transfer['status']] ?? ['غير معروف', 'bg-secondary', 'bi-question']; ?>
                <div class="col-md-6 mb-4">
                    <div class="card transfer-card status-<?= $transfer['status'] ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="store-code"><?= htmlspecialchars($transfer['transfer_code']) ?></span>
                                    <span class="badge <?= $status_info[1] ?> ms-2">
                                        <i class="bi <?= $status_info[2] ?>"></i> <?= $status_info[0] ?>
                                    </span>
                                </div>
                                <div class="action-btns">
                                    <a href="view.php?id=<?= $transfer['id'] ?>" class="btn btn-sm btn-info" title="عرض">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="row align-items-center mb-3">
                                <div class="col-5 text-center">
                                    <div class="fw-bold"><?= htmlspecialchars($transfer['from_store_name'] ?? 'غير معروف') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($transfer['from_store_code'] ?? '') ?></small>
                                    <?php if ($transfer['from_branch_name']): ?>
                                        <div><span class="badge bg-light text-dark"><?= htmlspecialchars($transfer['from_branch_name']) ?></span></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-2 text-center">
                                    <i class="bi bi-arrow-left store-arrow"></i>
                                </div>
                                <div class="col-5 text-center">
                                    <div class="fw-bold"><?= htmlspecialchars($transfer['to_store_name'] ?? 'غير معروف') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($transfer['to_store_code'] ?? '') ?></small>
                                    <?php if ($transfer['to_branch_name']): ?>
                                        <div><span class="badge bg-light text-dark"><?= htmlspecialchars($transfer['to_branch_name']) ?></span></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between text-muted small">
                                <span><i class="bi bi-boxes"></i> <?= $transfer['total_items'] ?> صنف</span>
                                <span><i class="bi bi-stack"></i> <?= number_format($transfer['total_quantity'], 0) ?> كمية</span>
                                <span><i class="bi bi-cash"></i> <?= number_format($transfer['total_cost'], 2) ?> ج</span>
                            </div>

                            <div class="mt-2 text-muted small">
                                <i class="bi bi-person"></i> <?= htmlspecialchars($transfer['requested_by_name'] ?? 'غير معروف') ?> | 
                                <i class="bi bi-calendar"></i> <?= date('Y-m-d', strtotime($transfer['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($transfers)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-arrow-left-right" style="font-size: 48px; color: #ddd;"></i>
                            <h5 class="mt-3 text-muted">لا توجد تحويلات</h5>
                            <a href="create.php" class="btn btn-primary mt-3">
                                <i class="bi bi-plus-lg"></i> إنشاء تحويل جديد
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
                        <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= $from_store > 0 ? '&from_store=' . $from_store : '' ?><?= $to_store > 0 ? '&to_store=' . $to_store : '' ?>">السابق</a></li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= $from_store > 0 ? '&from_store=' . $from_store : '' ?><?= $to_store > 0 ? '&to_store=' . $to_store : '' ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= $from_store > 0 ? '&from_store=' . $from_store : '' ?><?= $to_store > 0 ? '&to_store=' . $to_store : '' ?>">التالي</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
