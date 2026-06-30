<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'حركة الأصناف';

$stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();
$products = $db->query("SELECT id, product_name, product_code, manual_code FROM products WHERE is_active = 1 ORDER BY product_name LIMIT 100")->fetchAll();

$store_id = intval($_GET['store_id'] ?? 0);
$product_id = intval($_GET['product_id'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$movements = [];
$product_info = null;
$balance = 0;

if ($product_id > 0) {
    $product_info = $db->prepare("SELECT * FROM products WHERE id = ?");
    $product_info->execute([$product_id]);
    $product_info = $product_info->fetch();

    $where = "WHERE product_id = ?";
    $params = [$product_id];
    
    if ($store_id > 0) {
        $where .= " AND store_id = ?";
        $params[] = $store_id;
    }
    if (!empty($date_from)) {
        $where .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $where .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }

    $stmt = $db->prepare("
        SELECT t.*, s.store_name, u.full_name as created_by_name
        FROM inventory_transactions t
        LEFT JOIN stores s ON t.store_id = s.id
        LEFT JOIN users u ON t.created_by = u.id
        {$where}
        ORDER BY t.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $movements = $stmt->fetchAll();

    // Calculate running balance
    $balance_sql = "SELECT SUM(quantity) as total FROM inventory_items WHERE product_id = ?" . ($store_id > 0 ? " AND store_id = ?" : "");
    $balance_stmt = $db->prepare($balance_sql);
    $balance_params = [$product_id];
    if ($store_id > 0) $balance_params[] = $store_id;
    $balance_stmt->execute($balance_params);
    $balance = floatval($balance_stmt->fetch()['total'] ?? 0);
}

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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .movement-in { color: var(--success); }
        .movement-out { color: var(--danger); }
        .balance-card { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 20px; border-radius: 15px; margin-bottom: 20px; }
        .balance-card .balance-value { font-size: 36px; font-weight: bold; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="bi bi-arrow-left-right"></i> <?= $page_title ?></h2>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">المخزن</label>
                            <select name="store_id" class="form-select">
                                <option value="0">كل المخازن</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $store_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الصنف</label>
                            <select name="product_id" class="form-select">
                                <option value="0">-- اختر الصنف --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['product_name']) ?> (<?= $p['product_code'] ?? $p['manual_code'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">من تاريخ</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">إلى تاريخ</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> عرض</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($product_info): ?>
            <div class="balance-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4><?= htmlspecialchars($product_info['product_name']) ?></h4>
                        <p class="mb-0">كود: <?= htmlspecialchars($product_info['product_code'] ?? $product_info['manual_code'] ?? 'N/A') ?></p>
                    </div>
                    <div class="col-md-4 text-start">
                        <div class="balance-value"><?= number_format($balance, 3) ?></div>
                        <small>الرصيد الحالي</small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>التاريخ</th><th>النوع</th><th>المخزن</th><th>الكمية</th><th>الرصيد</th><th>التكلفة</th><th>المرجع</th><th>بواسطة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $running_balance = $balance;
                                foreach ($movements as $m): 
                                    $is_in = in_array($m['transaction_type'], ['transfer_in', 'opening_balance', 'purchase', 'adjustment']) || $m['quantity'] > 0;
                                    $qty = abs(floatval($m['quantity']));
                                    $running_balance += $m['quantity'];
                                ?>
                                <tr>
                                    <td><?= date('Y-m-d H:i', strtotime($m['created_at'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $is_in ? 'success' : 'danger' ?>">
                                            <?= $is_in ? '<i class="bi bi-arrow-down"></i> وارد' : '<i class="bi bi-arrow-up"></i> صادر' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($m['store_name'] ?? '-') ?></td>
                                    <td class="<?= $is_in ? 'movement-in' : 'movement-out' ?> fw-bold"><?= number_format($qty, 3) ?></td>
                                    <td class="fw-bold"><?= number_format($running_balance, 3) ?></td>
                                    <td><?= number_format($m['unit_cost'], 2) ?> ج</td>
                                    <td><?= htmlspecialchars($m['reference_type'] ?? '-') ?> <?= $m['reference_id'] ? '#' . $m['reference_id'] : '' ?></td>
                                    <td><?= htmlspecialchars($m['created_by_name'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-search" style="font-size: 48px; color: #ddd;"></i>
                    <h5 class="mt-3 text-muted">اختر صنفاً لعرض حركته</h5>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>