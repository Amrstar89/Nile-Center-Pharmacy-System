<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'تعديل أسعار الأصناف';

$stores = $db->query("SELECT id, store_name FROM stores WHERE is_active = 1 ORDER BY store_name")->fetchAll();

$store_id = intval($_GET['store_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$items = [];
if ($store_id > 0) {
    $sql = "
        SELECT ii.*, p.product_name, p.product_code, p.manual_code, p.has_expire, b.exp_date, b.id as batch_id
        FROM inventory_items ii
        JOIN products p ON ii.product_id = p.id
        LEFT JOIN inventory_batches b ON ii.batch_id = b.id
        WHERE ii.store_id = ? AND ii.is_active = 1
    ";
    $params = [$store_id];
    
    if (!empty($search)) {
        $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.manual_code LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $sql .= " ORDER BY p.product_name LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
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
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .price-input { font-weight: bold; text-align: center; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="bi bi-currency-exchange"></i> <?= $page_title ?></h2>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <select name="store_id" class="form-select" onchange="this.form.submit()">
                                <option value="0">-- اختر المخزن --</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $store_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['store_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="search" class="form-control" placeholder="ابحث باسم الصنف أو الكود..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($store_id > 0): ?>
            <form method="POST" action="save.php">
                <input type="hidden" name="store_id" value="<?= $store_id ?>">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th><th>الصنف</th><th>الدفعة</th><th>الكمية</th>
                                        <th>التكلفة القديمة</th><th>التكلفة الجديدة</th>
                                        <th>سعر البيع القديم</th><th>سعر البيع الجديد</th>
                                        <th>الخصم %</th><th>الضريبة %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $i => $item): ?>
                                    <tr>
                                        <td><?= $i+1 ?></td>
                                        <td>
                                            <?= htmlspecialchars($item['product_name']) ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? 'N/A') ?></small>
                                            <input type="hidden" name="items[<?= $i ?>][inv_id]" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="items[<?= $i ?>][product_id]" value="<?= $item['product_id'] ?>">
                                            <input type="hidden" name="items[<?= $i ?>][batch_id]" value="<?= $item['batch_id'] ?? '' ?>">
                                        </td>
                                        <td><?= $item['exp_date'] ?? '-' ?></td>
                                        <td><?= number_format($item['quantity'], 3) ?></td>
                                        <td><?= number_format($item['unit_cost'], 2) ?></td>
                                        <td><input type="number" name="items[<?= $i ?>][new_cost]" class="form-control price-input" step="0.01" value="<?= $item['unit_cost'] ?>"></td>
                                        <td><?= number_format($item['sell_price'], 2) ?></td>
                                        <td><input type="number" name="items[<?= $i ?>][new_sell]" class="form-control price-input" step="0.01" value="<?= $item['sell_price'] ?>"></td>
                                        <td><input type="number" name="items[<?= $i ?>][discount]" class="form-control price-input" step="0.01" value="<?= $item['discount_percent'] ?? 0 ?>"></td>
                                        <td><input type="number" name="items[<?= $i ?>][vat]" class="form-control price-input" step="0.01" value="<?= $item['vat_percent'] ?? 0 ?>"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> حفظ التعديلات</button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>