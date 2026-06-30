<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();
$page_title = 'تعديل الأرصدة والأسعار';

// Get branches
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

$selected_store = intval($_GET['store'] ?? 0);
$selected_branch = intval($_GET['branch'] ?? 0);
$search = trim($_GET['search'] ?? '');

$items = [];
if ($selected_store > 0) {
    $sql = "
        SELECT ii.*, p.product_name, p.product_code, p.manual_code, p.barcode,
               p.has_expire, p.company_id, c.company_name, p.scientific_name,
               u.unit_name_ar
        FROM inventory_items ii
        JOIN products p ON ii.product_id = p.id
        LEFT JOIN companies c ON p.company_id = c.id
        LEFT JOIN product_units u ON p.unit_id = u.id
        WHERE ii.store_id = ? AND ii.is_active = 1
    ";
    $params = [$selected_store];
    
    if ($search) {
        $sql .= " AND (p.product_name LIKE ? OR p.product_code LIKE ? OR p.manual_code LIKE ? OR p.barcode = ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = $search;
    }
    
    $sql .= " ORDER BY p.product_name LIMIT 200";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
}

// Get stores for selected branch
$stores = [];
if ($selected_branch > 0) {
    $stmt = $db->prepare("SELECT id, store_name, store_code, store_type, store_category FROM stores WHERE branch_id = ? AND is_active = 1 ORDER BY is_main_store DESC, store_name");
    $stmt->execute([$selected_branch]);
    $stores = $stmt->fetchAll();
}

$category_labels = [
    'main_store' => 'رئيسي', 'warehouse' => 'مستودع', 'cold' => 'ثلاجة',
    'narcotics' => 'مخدرات', 'cosmetics' => 'تجميل', 'medical_supply' => 'مستلزمات',
    'surplus' => 'فائض', 'damaged' => 'تالف', 'expired' => 'منتهي'
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
        .item-row { transition: all 0.2s; }
        .item-row:hover { background: #f0f4ff; }
        .item-row input { font-size: 13px; }
        .profit-display { font-size: 11px; padding: 2px 8px; border-radius: 6px; }
        .profit-good { background: #d4edda; color: #155724; }
        .profit-bad { background: #f8d7da; color: #721c24; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="bi bi-pencil-square"></i> <?= $page_title ?></h2>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> اختر الفرع ثم المخزن لتعديل أسعار التكلفة وأسعار البيع ونسب الربح للأصناف. يمكنك التعديل على مجموعة من الأصناف دفعة واحدة.
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select name="branch" class="form-select" onchange="this.form.submit()">
                                <option value="0">اختر الفرع</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $selected_branch == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="store" class="form-select" onchange="this.form.submit()">
                                <option value="0">اختر المخزن</option>
                                <?php foreach ($stores as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $selected_store == $s['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['store_name']) ?> (<?= $category_labels[$s['store_category']] ?? 'مخزن' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="بحث باسم الصنف أو الباركود..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> بحث</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_store > 0): ?>
            <!-- Bulk Edit Form -->
            <form method="POST" action="save.php">
                <input type="hidden" name="store_id" value="<?= $selected_store ?>">
                
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> أصناف المخزن</h5>
                        <div>
                            <span class="badge bg-primary"><?= count($items) ?> صنف</span>
                            <button type="submit" class="btn btn-success btn-sm ms-2"><i class="bi bi-save"></i> حفظ التعديلات</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><input type="checkbox" id="selectAll" onclick="toggleAll()"></th>
                                        <th>الصنف</th>
                                        <th>الكمية</th>
                                        <th>تكلفة الوحدة</th>
                                        <th>سعر البيع</th>
                                        <th>نسبة الربح</th>
                                        <th>الخصم %</th>
                                        <th>الضريبة %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): 
                                        $cost = floatval($item['unit_cost']);
                                        $sell = floatval($item['sell_price']);
                                        $profit = $sell > 0 ? (($sell - $cost) / $sell * 100) : 0;
                                    ?>
                                    <tr class="item-row">
                                        <td><input type="checkbox" name="selected[]" value="<?= $item['id'] ?>" class="row-check"></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($item['product_code'] ?? $item['manual_code'] ?? '') ?></small>
                                            <?php if ($item['has_expire']): ?><span class="badge bg-warning text-dark">له تاريخ صلاحية</span><?php endif; ?>
                                        </td>
                                        <td><?= number_format(floatval($item['quantity']), 3) ?></td>
                                        <td>
                                            <input type="number" name="cost[<?= $item['id'] ?>]" class="form-control" 
                                                   value="<?= $cost > 0 ? number_format($cost, 2, '.', '') : '' ?>" step="0.01" min="0"
                                                   onchange="calcProfit(this, <?= $item['id'] ?>)">
                                        </td>
                                        <td>
                                            <input type="number" name="sell[<?= $item['id'] ?>]" class="form-control" 
                                                   value="<?= $sell > 0 ? number_format($sell, 2, '.', '') : '' ?>" step="0.01" min="0"
                                                   onchange="calcProfit(this, <?= $item['id'] ?>)">
                                        </td>
                                        <td>
                                            <span id="profit_<?= $item['id'] ?>" class="profit-display <?= $profit >= 10 ? 'profit-good' : 'profit-bad' ?>">
                                                <?= number_format($profit, 1) ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" name="discount[<?= $item['id'] ?>]" class="form-control" 
                                                   value="<?= floatval($item['discount_percent']) ?>" step="0.01" min="0" max="100">
                                        </td>
                                        <td>
                                            <input type="number" name="vat[<?= $item['id'] ?>]" class="form-control" 
                                                   value="<?= floatval($item['vat_percent']) ?>" step="0.01" min="0" max="100">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($items)): ?>
                                        <tr><td colspan="8" class="text-center py-4 text-muted">لا توجد أصناف في المخزن</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> حفظ جميع التعديلات</button>
                    </div>
                </div>
            </form>
            <?php elseif ($selected_branch > 0): ?>
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> اختر المخزن لعرض الأصناف</div>
            <?php else: ?>
                <div class="alert alert-info"><i class="bi bi-info-circle"></i> اختر الفرع والمخزن للبدء</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll() {
            const checked = document.getElementById('selectAll').checked;
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = checked);
        }
        
        function calcProfit(el, id) {
            // Find the row
            const row = el.closest('tr');
            const cost = parseFloat(row.querySelector('input[name^="cost["]').value) || 0;
            const sell = parseFloat(row.querySelector('input[name^="sell["]').value) || 0;
            const profit = sell > 0 ? ((sell - cost) / sell * 100) : 0;
            
            const badge = document.getElementById('profit_' + id);
            badge.textContent = profit.toFixed(1) + '%';
            badge.className = 'profit-display ' + (profit >= 10 ? 'profit-good' : 'profit-bad');
            
            // Auto-check the row
            row.querySelector('.row-check').checked = true;
        }
    </script>
</body>
</html>
