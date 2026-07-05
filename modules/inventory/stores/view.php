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
    SELECT s.*, b.branch_name, b.branch_code
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

$page_title = 'عرض المخزن: ' . $store['store_name'];

// Get inventory items in this store
$items = $db->prepare("
    SELECT ii.*, p.product_name, p.product_code, p.manual_code,
           p.sell_price, p.cost_price, p.has_expire
    FROM inventory_items ii
    JOIN products p ON ii.product_id = p.id
    WHERE ii.store_id = ? AND ii.is_active = 1
    ORDER BY p.product_name
    LIMIT 100
");
$items->execute([$id]);
$inventory_items = $items->fetchAll();

// Get batches in this store
$batches = $db->prepare("
    SELECT ib.*, p.product_name, p.product_code, p.manual_code
    FROM inventory_batches ib
    JOIN products p ON ib.product_id = p.id
    WHERE ib.store_id = ? AND ib.remaining_qty > 0
    ORDER BY ib.exp_date ASC
    LIMIT 100
");
$batches->execute([$id]);
$batch_items = $batches->fetchAll();

// Get recent transfers FROM this store
$out_transfers = $db->prepare("
    SELECT t.*, ts.store_name as to_store_name
    FROM inventory_transfers t
    LEFT JOIN stores ts ON t.to_store_id = ts.id
    WHERE t.from_store_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$out_transfers->execute([$id]);
$outgoing = $out_transfers->fetchAll();

// Get recent transfers TO this store
$in_transfers = $db->prepare("
    SELECT t.*, fs.store_name as from_store_name
    FROM inventory_transfers t
    LEFT JOIN stores fs ON t.from_store_id = fs.id
    WHERE t.to_store_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$in_transfers->execute([$id]);
$incoming = $in_transfers->fetchAll();

// Summary
$total_products = count($inventory_items);
$total_quantity = array_sum(array_column($inventory_items, 'quantity'));
$total_value = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_cost'], $inventory_items));
$low_stock = count(array_filter($inventory_items, fn($i) => $i['quantity'] <= $i['reorder_point'] && $i['reorder_point'] > 0));

$category_labels = [
    'main_store' => ['المخزن الرئيسي', 'bi-building', 'primary'],
    'warehouse' => ['مستودع', 'bi-box-seam', 'info'],
    'cold' => ['ثلاجة أدوية', 'bi-thermometer-low', 'success'],
    'narcotics' => ['مخدرات ومؤثرات', 'bi-shield-lock', 'danger'],
    'cosmetics' => ['مستحضرات تجميل', 'bi-magic', 'warning'],
    'medical_supply' => ['مستلزمات طبية', 'bi-bandaid', 'secondary'],
    'surplus' => ['مخزون فائض', 'bi-stack', 'dark'],
    'damaged' => ['بضاعة تالفة', 'bi-trash', 'danger'],
    'expired' => ['منتهي الصلاحية', 'bi-calendar-x', 'dark']
];

$cat_info = $category_labels[$store['store_category']] ?? ['مخزن', 'bi-box', 'secondary'];

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
        .store-header { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); 
            color: white; 
            padding: 25px; 
            border-radius: 15px; 
            margin-bottom: 20px;
        }
        .store-code { background: rgba(255,255,255,0.2); color: white; padding: 5px 12px; border-radius: 8px; font-size: 13px; font-family: monospace; }
        .stat-box { background: white; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-box i { font-size: 24px; margin-bottom: 8px; }
        .stat-box .val { font-size: 20px; font-weight: 700; }
        .stat-box .lbl { font-size: 11px; color: #888; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Back -->
            <div class="mb-3">
                <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-right"></i> العودة للمخازن</a>
            </div>

            <!-- Store Header -->
            <div class="store-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-2"><i class="bi bi-building"></i> <?= htmlspecialchars($store['store_name']) ?></h3>
                        <span class="store-code"><?= htmlspecialchars($store['store_code']) ?></span>
                        <span class="badge bg-light text-dark ms-2"><i class="bi bi-building"></i> <?= htmlspecialchars($store['branch_name'] ?? 'مركزي') ?></span>
                        <span class="badge bg-<?= $cat_info[2] ?> ms-2"><i class="bi <?= $cat_info[1] ?>"></i> <?= $cat_info[0] ?></span>
                        <?php if ($store['is_main_store']): ?>
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-star-fill"></i> رئيسي</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="edit.php?id=<?= $store['id'] ?>" class="btn btn-light"><i class="bi bi-pencil"></i> تعديل</a>
                    </div>
                </div>
                <?php if ($store['notes']): ?>
                    <div class="mt-3"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($store['notes']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="stat-box"><i class="bi bi-boxes text-primary"></i><div class="val"><?= number_format($total_products) ?></div><div class="lbl">الأصناف</div></div></div>
                <div class="col-md-3"><div class="stat-box"><i class="bi bi-stack text-success"></i><div class="val"><?= number_format($total_quantity, 0) ?></div><div class="lbl">الكمية</div></div></div>
                <div class="col-md-3"><div class="stat-box"><i class="bi bi-cash text-info"></i><div class="val"><?= number_format($total_value, 2) ?> ج</div><div class="lbl">القيمة</div></div></div>
                <div class="col-md-3"><div class="stat-box"><i class="bi bi-exclamation-triangle text-danger"></i><div class="val"><?= number_format($low_stock) ?></div><div class="lbl">ناقصة</div></div></div>
            </div>

            <!-- Inventory Items -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-boxes"></i> الأصناف في المخزن</h5>
                    <span class="badge bg-primary"><?= $total_products ?> صنف</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>الصنف</th>
                                    <th>الكود</th>
                                    <th>الكمية</th>
                                    <th>التكلفة</th>
                                    <th>سعر البيع</th>
                                    <th>القيمة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_items as $i => $item): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td><code><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? 'N/A') ?></code></td>
                                    <td><?= number_format($item['quantity'], 3) ?></td>
                                    <td><?= number_format($item['unit_cost'], 2) ?> ج</td>
                                    <td><?= number_format($item['sell_price'], 2) ?> ج</td>
                                    <td><?= number_format($item['quantity'] * $item['unit_cost'], 2) ?> ج</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($inventory_items)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">لا توجد أصناف في المخزن</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Batches with expiry -->
            <?php if (!empty($batch_items)): ?>
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-check"></i> الدفعات وتواريخ الصلاحية</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>الصنف</th><th>تاريخ الصلاحية</th><th>الرصيد</th><th>التكلفة</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batch_items as $b): ?>
                                <tr>
                                    <td><?= htmlspecialchars($b['product_name']) ?></td>
                                    <td>
                                        <span class="badge <?= strtotime($b['exp_date']) < strtotime('+3 months') ? 'bg-danger' : 'bg-success' ?>">
                                            <?= $b['exp_date'] ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($b['remaining_qty'], 3) ?></td>
                                    <td><?= number_format($b['unit_cost'], 2) ?> ج</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>