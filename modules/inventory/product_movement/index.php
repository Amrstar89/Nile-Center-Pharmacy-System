<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'حركة الأصناف';

// Get stores for filter
$stores = $db->query("SELECT id, store_name, store_code FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();

// Get product info if searched
$product_id = intval($_GET['product_id'] ?? 0);
$barcode = trim($_GET['barcode'] ?? '');
$store_filter = intval($_GET['store'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$product = null;
$movements = [];

if ($barcode) {
    // Find by barcode
    $stmt = $db->prepare("SELECT * FROM products WHERE barcode = ? OR product_code = ? OR manual_code = ? LIMIT 1");
    $stmt->execute([$barcode, $barcode, $barcode]);
    $product = $stmt->fetch();
    if ($product) $product_id = $product['id'];
}

if ($product_id > 0 && !$product) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
}

if ($product_id > 0) {
    // Build movement query
    $where = "WHERE m.product_id = ? AND m.created_at BETWEEN ? AND ?";
    $params = [$product_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59'];
    
    if ($store_filter > 0) {
        $where .= " AND m.store_id = ?";
        $params[] = $store_filter;
    }
    
    // Get all movements for this product
    $sql = "
        SELECT 
            m.*,
            s.store_name, s.store_code,
            p.product_name, p.product_code, p.manual_code
        FROM product_movement_log m
        LEFT JOIN stores s ON m.store_id = s.id
        LEFT JOIN products p ON m.product_id = p.id
        $where
        ORDER BY m.created_at DESC
        LIMIT 500
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll();
    
    // If no movement_log table data, fallback to inventory_transactions
    if (empty($movements)) {
        $stmt = $db->prepare("
            SELECT 
                t.*,
                s.store_name, s.store_code
            FROM inventory_transactions t
            LEFT JOIN stores s ON t.store_id = s.id
            WHERE t.product_id = ? AND t.created_at BETWEEN ? AND ?
            " . ($store_filter > 0 ? " AND t.store_id = ?" : "") . "
            ORDER BY t.created_at DESC
            LIMIT 500
        ");
        $fallback_params = [$product_id, $date_from . ' 00:00:00', $date_to . ' 23:59:59'];
        if ($store_filter > 0) $fallback_params[] = $store_filter;
        $stmt->execute($fallback_params);
        $movements = $stmt->fetchAll();
    }
}

$movement_types = [
    'purchase' => ['شراء', 'bi-cart-plus', 'text-success'],
    'sale' => ['بيع', 'bi-cart-dash', 'text-danger'],
    'transfer_in' => ['تحويل وارد', 'bi-arrow-down-left', 'text-primary'],
    'transfer_out' => ['تحويل صادر', 'bi-arrow-up-right', 'text-warning'],
    'adjustment' => ['تسوية', 'bi-clipboard-check', 'text-info'],
    'opening_balance' => ['رصيد افتتاحي', 'bi-journal-plus', 'text-secondary'],
    'return_in' => ['مرتجع وارد', 'bi-arrow-return-left', 'text-success'],
    'return_out' => ['مرتجع صادر', 'bi-arrow-return-right', 'text-danger'],
    'price_change' => ['تغيير سعر', 'bi-currency-exchange', 'text-dark']
];

require_once __DIR__ . '/../../../includes/sidebar.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
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
        .timeline { position: relative; padding: 0; }
        .timeline-item { padding: 15px 20px; border-right: 3px solid #e9ecef; margin-right: 15px; position: relative; transition: all 0.2s; }
        .timeline-item:hover { border-right-color: var(--primary); background: #f8f9fa; }
        .timeline-item::before { content: ''; position: absolute; right: -9px; top: 20px; width: 12px; height: 12px; border-radius: 50%; background: #dee2e6; }
        .timeline-item:hover::before { background: var(--primary); }
        .timeline-item.income::before { background: #198754; }
        .timeline-item.outcome::before { background: #dc3545; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="bi bi-graph-up-arrow"></i> <?= $page_title ?></h2>
            
            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">الباركود / الكود</label>
                            <div class="input-group">
                                <input type="text" name="barcode" class="form-control" placeholder="ادخل الباركود..." value="<?= htmlspecialchars($barcode) ?>">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">المخزن</label>
                            <select name="store" class="form-select">
                                <option value="0">كل المخازن</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $store_filter == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">من</label>
                            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">إلى</label>
                            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> عرض</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($product): ?>
            <!-- Product Info -->
            <div class="card mb-4" style="border-right: 4px solid var(--primary);">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4><i class="bi bi-box"></i> <?= htmlspecialchars($product['product_name']) ?></h4>
                            <div class="text-muted">
                                <code><?= htmlspecialchars($product['product_code'] ?? $product['manual_code'] ?? 'N/A') ?></code>
                                <?php if ($product['barcode']): ?>
                                    <span class="ms-2">| باركود: <code><?= htmlspecialchars($product['barcode']) ?></code></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6 text-start">
                            <span class="badge bg-primary fs-6"><?= count($movements) ?> حركة</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Movement Summary -->
            <?php
            $total_in = array_sum(array_map(fn($m) => floatval($m['quantity'] ?? 0) > 0 ? floatval($m['quantity']) : 0, $movements));
            $total_out = abs(array_sum(array_map(fn($m) => floatval($m['quantity'] ?? 0) < 0 ? floatval($m['quantity']) : 0, $movements)));
            ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card text-center p-3 border-success">
                        <h4 class="text-success">+<?= number_format($total_in, 3) ?></h4>
                        <small class="text-muted">إجمالي الوارد</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3 border-danger">
                        <h4 class="text-danger">-<?= number_format($total_out, 3) ?></h4>
                        <small class="text-muted">إجمالي الصادر</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center p-3 border-primary">
                        <h4 class="text-primary"><?= number_format($total_in - $total_out, 3) ?></h4>
                        <small class="text-muted">صافي الحركة</small>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> سجل الحركات</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($movements as $m): 
                            $mtype = $m['movement_type'] ?? $m['transaction_type'] ?? 'unknown';
                            $type_info = $movement_types[$mtype] ?? ['غير معروف', 'bi-question-circle', 'text-muted'];
                            $qty = floatval($m['quantity'] ?? 0);
                            $is_income = $qty > 0;
                        ?>
                        <div class="timeline-item <?= $is_income ? 'income' : 'outcome' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi <?= $type_info[1] ?> <?= $type_info[2] ?>"></i> <?= $type_info[0] ?>
                                    </span>
                                    <?php if ($m['store_name']): ?>
                                        <span class="badge bg-light text-dark ms-1">
                                            <i class="bi bi-building"></i> <?= htmlspecialchars($m['store_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="mt-1">
                                        <strong class="<?= $is_income ? 'text-success' : 'text-danger' ?>">
                                            <?= $is_income ? '+' : '' ?><?= number_format($qty, 3) ?>
                                        </strong>
                                        <?php if ($m['unit_cost']): ?>
                                            <small class="text-muted">| تكلفة: <?= number_format(floatval($m['unit_cost']), 2) ?> ج</small>
                                        <?php endif; ?>
                                        <?php if ($m['unit_price']): ?>
                                            <small class="text-muted">| سعر: <?= number_format(floatval($m['unit_price']), 2) ?> ج</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($m['notes']): ?>
                                        <small class="text-muted"><i class="bi bi-info-circle"></i> <?= htmlspecialchars($m['notes']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small text-nowrap">
                                    <i class="bi bi-calendar"></i> <?= date('Y-m-d', strtotime($m['created_at'])) ?>
                                    <br><i class="bi bi-clock"></i> <?= date('H:i', strtotime($m['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($movements)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 48px;"></i>
                                <h5 class="mt-2">لا توجد حركات في الفترة المحددة</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <?php if ($barcode): ?>
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> الصنف غير موجود</div>
                <?php else: ?>
                    <div class="card text-center py-5">
                        <i class="bi bi-search" style="font-size: 48px; color: #ddd;"></i>
                        <h5 class="mt-3 text-muted">ادخل الباركود لعرض حركة الصنف</h5>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
