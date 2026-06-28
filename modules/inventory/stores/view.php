<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$store_id = intval($_GET['id'] ?? 0);
if ($store_id <= 0) {
    header("Location: index.php");
    exit;
}

// Get store with branch and parent info
$stmt = $db->prepare("
    SELECT s.*, b.branch_name, p.store_name as parent_store_name, p.store_type as parent_type
    FROM stores s
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN stores p ON s.parent_store_id = p.id
    WHERE s.id = ?
");
$stmt->execute([$store_id]);
$store = $stmt->fetch();

if (!$store) {
    header("Location: index.php");
    exit;
}

// Get sub-stores (children)
$children = $db->prepare("SELECT * FROM stores WHERE parent_store_id = ? AND is_active = 1 ORDER BY store_name");
$children->execute([$store_id]);
$children = $children->fetchAll();

// Get inventory items in this store - FIXED: use ii.unit_cost not p.cost_price
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$items_sql = "SELECT 
    ii.*, p.product_name, p.product_code, p.manual_code, p.sell_price,
    b.exp_date, b.batch_number,
    (ii.quantity * ii.unit_cost) as stock_value
FROM inventory_items ii
JOIN products p ON ii.product_id = p.id
LEFT JOIN inventory_batches b ON ii.batch_id = b.id
WHERE ii.store_id = ? AND ii.is_active = 1
ORDER BY p.product_name
LIMIT {$per_page} OFFSET {$offset}";

$items_stmt = $db->prepare($items_sql);
$items_stmt->execute([$store_id]);
$items = $items_stmt->fetchAll();

// Count total items
$count_stmt = $db->prepare("SELECT COUNT(*) as total FROM inventory_items WHERE store_id = ? AND is_active = 1");
$count_stmt->execute([$store_id]);
$total_items = $count_stmt->fetch()['total'];
$total_pages = ceil($total_items / $per_page);

// Get stock summary - FIXED: use ii.unit_cost
$summary = $db->prepare("
    SELECT 
        COUNT(DISTINCT product_id) as products_count,
        SUM(quantity) as total_quantity,
        SUM(quantity * unit_cost) as total_value
    FROM inventory_items 
    WHERE store_id = ? AND is_active = 1
");
$summary->execute([$store_id]);
$summary = $summary->fetch();

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
$type_info = $type_labels[$store['store_type']] ?? ['مخزن', 'bi-box', 'secondary'];

$page_title = 'عرض المخزن - ' . $store['store_name'];
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
        .summary-card { text-align: center; padding: 20px; border-radius: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
        .summary-card .icon { font-size: 32px; margin-bottom: 10px; color: var(--primary); }
        .summary-card .value { font-size: 24px; font-weight: bold; color: var(--primary); }
        .summary-card .label { font-size: 13px; color: #666; }
        .info-card { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; }
        .info-label { color: #666; font-size: 12px; margin-bottom: 3px; }
        .info-value { font-weight: 600; font-size: 14px; }
        .store-header { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; }
        .nav-tabs .nav-link { border: none; color: #666; font-weight: 500; padding: 12px 20px; }
        .nav-tabs .nav-link.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: transparent; }
        .child-store { border-left: 3px solid var(--primary); padding: 10px 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; }
        .exp-badge { font-size: 0.75rem; }
        .exp-soon { background: #fff3cd; color: #856404; }
        .exp-ok { background: #d4edda; color: #155724; }
        .exp-danger { background: #f8d7da; color: #721c24; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body style="margin: 0; padding: 0;">
    <?= $sidebar ?? '' ?>
    <div class="main-content" style="margin-right: 260px; padding: 20px; min-height: 100vh;">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-eye"></i> <?= $page_title ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">المخازن</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($store['store_name']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="edit.php?id=<?= $store_id ?>" class="btn btn-warning"><i class="bi bi-pencil"></i> تعديل</a>
                    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> رجوع</a>
                </div>
            </div>

            <!-- Store Header -->
            <div class="store-header mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3><i class="bi <?= $type_info[1] ?>"></i> <?= htmlspecialchars($store['store_name']) ?></h3>
                        <div class="mt-2">
                            <span class="badge bg-light text-dark me-2"><?= htmlspecialchars($store['store_code']) ?></span>
                            <span class="badge bg-<?= $type_info[2] ?> me-2"><?= $type_info[0] ?></span>
                            <?php if ($store['is_main']): ?><span class="badge bg-success">رئيسي</span><?php endif; ?>
                            <?php if (!$store['is_active']): ?><span class="badge bg-danger">موقوف</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-start">
                        <?php if (!empty($store['branch_name'])): ?>
                            <div><i class="bi bi-building"></i> <?= htmlspecialchars($store['branch_name']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($store['parent_store_name'])): ?>
                            <div><i class="bi bi-arrow-up"></i> تابع لـ: <?= htmlspecialchars($store['parent_store_name']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card summary-card">
                        <div class="icon"><i class="bi bi-boxes"></i></div>
                        <div class="value"><?= number_format(intval($summary['products_count'] ?? 0)) ?></div>
                        <div class="label">عدد الأصناف</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card summary-card">
                        <div class="icon"><i class="bi bi-stack"></i></div>
                        <div class="value"><?= number_format(floatval($summary['total_quantity'] ?? 0), 0) ?></div>
                        <div class="label">إجمالي الكمية</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card summary-card">
                        <div class="icon"><i class="bi bi-cash-stack"></i></div>
                        <div class="value"><?= number_format(floatval($summary['total_value'] ?? 0), 2) ?> ج</div>
                        <div class="label">قيمة المخزون</div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="storeTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button">
                        <i class="bi bi-boxes"></i> رصيد المخزن (<?= $total_items ?>)
                    </button>
                </li>
                <?php if (!empty($children)): ?>
                <li class="nav-item">
                    <button class="nav-link" id="children-tab" data-bs-toggle="tab" data-bs-target="#children" type="button">
                        <i class="bi bi-diagram-3"></i> المخازن الفرعية (<?= count($children) ?>)
                    </button>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                        <i class="bi bi-info-circle"></i> المعلومات
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="storeTabsContent">
                <!-- Stock Tab -->
                <div class="tab-pane fade show active" id="stock" role="tabpanel">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>كود الصنف</th>
                                            <th>اسم الصنف</th>
                                            <th>الكمية</th>
                                            <th>تكلفة الوحدة</th>
                                            <th>سعر البيع</th>
                                            <th>قيمة المخزون</th>
                                            <th>تاريخ الصلاحية</th>
                                            <th>الحد الأدنى</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): 
                                            $exp_class = 'exp-ok';
                                            if ($item['exp_date']) {
                                                $exp_date = strtotime($item['exp_date']);
                                                $now = time();
                                                $diff_days = ($exp_date - $now) / 86400;
                                                if ($diff_days < 0) $exp_class = 'exp-danger';
                                                elseif ($diff_days < 90) $exp_class = 'exp-soon';
                                            }
                                        ?>
                                        <tr>
                                            <td><?= $item['id'] ?></td>
                                            <td><code><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? 'N/A') ?></code></td>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td><?= number_format($item['quantity'], 3) ?></td>
                                            <td><?= number_format($item['unit_cost'], 2) ?> ج</td>
                                            <td><?= number_format($item['sell_price'], 2) ?> ج</td>
                                            <td><?= number_format($item['stock_value'], 2) ?> ج</td>
                                            <td>
                                                <?php if ($item['exp_date']): ?>
                                                    <span class="badge <?= $exp_class ?> exp-badge">
                                                        <?= $item['exp_date'] ?> 
                                                        <?php if ($item['batch_number']): ?>(<?= $item['batch_number'] ?>)<?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($item['quantity'] <= $item['reorder_point']): ?>
                                                    <span class="badge bg-danger"><?= number_format($item['reorder_point'], 3) ?> ⚠️</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark"><?= number_format($item['reorder_point'], 3) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>

                                        <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <i class="bi bi-box" style="font-size: 48px; color: #ddd;"></i>
                                                <h5 class="mt-3 text-muted">لا توجد أصناف في هذا المخزن</h5>
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
                                <li class="page-item"><a class="page-link" href="?id=<?= $store_id ?>&page=<?= $page - 1 ?>">السابق</a></li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?id=<?= $store_id ?>&page=<?= $i ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?id=<?= $store_id ?>&page=<?= $page + 1 ?>">التالي</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>

                <!-- Children Tab -->
                <?php if (!empty($children)): ?>
                <div class="tab-pane fade" id="children" role="tabpanel">
                    <div class="row">
                        <?php foreach ($children as $child): ?>
                        <?php $child_type = $type_labels[$child['store_type']] ?? ['مخزن', 'bi-box', 'secondary']; ?>
                        <div class="col-md-6 mb-3">
                            <div class="child-store">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-<?= $child_type[2] ?> type-badge me-2">
                                            <i class="bi <?= $child_type[1] ?>"></i> <?= $child_type[0] ?>
                                        </span>
                                        <strong><?= htmlspecialchars($child['store_name']) ?></strong>
                                        <small class="text-muted">(<?= htmlspecialchars($child['store_code']) ?>)</small>
                                    </div>
                                    <a href="view.php?id=<?= $child['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> عرض
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Info Tab -->
                <div class="tab-pane fade" id="info" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-label">كود المخزن</div>
                                <div class="info-value"><?= htmlspecialchars($store['store_code']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-label">نوع المخزن</div>
                                <div class="info-value"><?= $type_info[0] ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-label">الفرع</div>
                                <div class="info-value"><?= htmlspecialchars($store['branch_name'] ?? 'غير تابع لفرع (مركزي)') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-label">المخزن الأب</div>
                                <div class="info-value"><?= htmlspecialchars($store['parent_store_name'] ?? 'لا يوجد') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-label">الحالة</div>
                                <div class="info-value">
                                    <?= $store['is_active'] ? '<span class="badge bg-success">نشط</span>' : '<span class="badge bg-danger">موقوف</span>' ?>
                                    <?= $store['is_main'] ? '<span class="badge bg-primary ms-1">رئيسي</span>' : '' ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="info-label">تاريخ الإنشاء</div>
                                <div class="info-value"><?= date('Y-m-d', strtotime($store['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php if (!empty($store['notes'])): ?>
                        <div class="col-md-12">
                            <div class="info-card">
                                <div class="info-label">ملاحظات</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($store['notes'])) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
