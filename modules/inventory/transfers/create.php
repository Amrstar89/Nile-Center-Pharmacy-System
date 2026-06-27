<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إنشاء تحويل جديد';

// Get active stores with branch info
$stores = $db->query("
    SELECT s.*, b.branch_name 
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 
    ORDER BY s.store_type, s.store_name
")->fetchAll();

// Get products for autocomplete
$products = $db->query("
    SELECT id, product_code, product_name, manual_code, cost_price, sell_price, unit1_id 
    FROM products 
    WHERE is_active = 1 
    ORDER BY product_name
")->fetchAll();

// Get product units
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1")->fetchAll();
$units_map = [];
foreach ($units as $u) {
    $units_map[$u['id']] = $u['unit_name_ar'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $errors = [];

        $from_store_id = intval($_POST['from_store_id'] ?? 0);
        $to_store_id = intval($_POST['to_store_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($from_store_id <= 0) {
            $errors[] = 'يجب اختيار مخزن الصادر';
        }
        if ($to_store_id <= 0) {
            $errors[] = 'يجب اختيار مخزن الوارد';
        }
        if ($from_store_id === $to_store_id) {
            $errors[] = 'لا يمكن التحويل لنفس المخزن';
        }

        if (empty($_POST['items']) || !is_array($_POST['items'])) {
            $errors[] = 'يجب إضافة أصناف للتحويل';
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        // Generate transfer code
        $last_code = $db->query("SELECT MAX(CAST(SUBSTRING(transfer_code, 4) AS UNSIGNED)) as max_code FROM inventory_transfers WHERE transfer_code LIKE 'TR-%'")->fetch();
        $max_code = intval($last_code['max_code'] ?? 0);
        $transfer_code = 'TR-' . str_pad($max_code + 1, 5, '0', STR_PAD_LEFT);

        // Get store branch info
        $from_store = $db->prepare("SELECT branch_id FROM stores WHERE id = ?");
        $from_store->execute([$from_store_id]);
        $from_branch_id = $from_store->fetch()['branch_id'] ?? null;

        $to_store = $db->prepare("SELECT branch_id FROM stores WHERE id = ?");
        $to_store->execute([$to_store_id]);
        $to_branch_id = $to_store->fetch()['branch_id'] ?? null;

        // Determine transfer type
        $transfer_type = 'internal';
        if ($from_branch_id && $to_branch_id && $from_branch_id != $to_branch_id) {
            $transfer_type = 'branch_to_branch';
        } elseif (!$from_branch_id && $to_branch_id) {
            $transfer_type = 'central_to_branch';
        } elseif ($from_branch_id && !$to_branch_id) {
            $transfer_type = 'branch_to_central';
        }

        // Calculate totals
        $total_items = 0;
        $total_quantity = 0;
        $total_cost = 0;

        foreach ($_POST['items'] as $item) {
            if (!empty($item['product_id']) && !empty($item['quantity']) && $item['quantity'] > 0) {
                $total_items++;
                $total_quantity += floatval($item['quantity']);
                $total_cost += floatval($item['quantity']) * floatval($item['unit_cost'] ?? 0);
            }
        }

        // Insert transfer
        $stmt = $db->prepare("
            INSERT INTO inventory_transfers 
            (transfer_code, from_store_id, to_store_id, from_branch_id, to_branch_id, transfer_type, status, total_items, total_quantity, total_cost, notes, requested_by, requested_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $transfer_code,
            $from_store_id,
            $to_store_id,
            $from_branch_id,
            $to_branch_id,
            $transfer_type,
            $total_items,
            $total_quantity,
            $total_cost,
            $notes ?: null,
            $_SESSION['user_id'] ?? 1
        ]);

        $transfer_id = $db->lastInsertId();

        // Insert items
        $item_stmt = $db->prepare("
            INSERT INTO inventory_transfer_items 
            (transfer_id, product_id, batch_id, requested_qty, unit_id, unit_conversion, unit_cost, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($_POST['items'] as $item) {
            if (!empty($item['product_id']) && !empty($item['quantity']) && $item['quantity'] > 0) {
                $item_stmt->execute([
                    $transfer_id,
                    intval($item['product_id']),
                    !empty($item['batch_id']) ? intval($item['batch_id']) : null,
                    floatval($item['quantity']),
                    !empty($item['unit_id']) ? intval($item['unit_id']) : null,
                    !empty($item['unit_conversion']) ? floatval($item['unit_conversion']) : 1,
                    floatval($item['unit_cost'] ?? 0),
                    !empty($item['notes']) ? trim($item['notes']) : null
                ]);
            }
        }

        $db->commit();
        header("Location: view.php?id={$transfer_id}&created=1");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin-bottom: 20px; }
        .item-row { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; position: relative; }
        .remove-btn { position: absolute; left: 10px; top: 10px; color: var(--danger); cursor: pointer; }
        .store-select { border-radius: 10px; padding: 15px; border: 2px solid #e9ecef; transition: all 0.3s; }
        .store-select:hover { border-color: var(--primary); }
        .store-select.selected { border-color: var(--primary); background: linear-gradient(135deg, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%); }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-plus-lg"></i> <?= $page_title ?></h2>
                <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="transferForm">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="section-title">المخازن</h5>
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">من مخزن <span class="text-danger">*</span></label>
                                <select name="from_store_id" id="from_store" class="form-select store-select" required onchange="updateToStores()">
                                    <option value="">-- اختر مخزن الصادر --</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? '' ?>">
                                            <?= htmlspecialchars($s['store_name']) ?> <?= $s['branch_name'] ? '(' . htmlspecialchars($s['branch_name']) . ')' : '(مركزي)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                                <i class="bi bi-arrow-left" style="font-size: 32px; color: var(--primary);"></i>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">إلى مخزن <span class="text-danger">*</span></label>
                                <select name="to_store_id" id="to_store" class="form-select store-select" required>
                                    <option value="">-- اختر مخزن الوارد --</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? '' ?>">
                                            <?= htmlspecialchars($s['store_name']) ?> <?= $s['branch_name'] ? '(' . htmlspecialchars($s['branch_name']) . ')' : '(مركزي)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="section-title">الأصناف</h5>
                        <div id="itemsContainer">
                            <div class="item-row">
                                <span class="remove-btn" onclick="this.closest('.item-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">الصنف <span class="text-danger">*</span></label>
                                        <select name="items[0][product_id]" class="form-select product-select" required onchange="updateProductInfo(this, 0)">
                                            <option value="">-- اختر الصنف --</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" data-cost="<?= $p['cost_price'] ?>" data-sell="<?= $p['sell_price'] ?>" data-unit="<?= $p['unit1_id'] ?>">
                                                    <?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['product_code'] ?? $p['manual_code'] ?? 'N/A') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">الكمية <span class="text-danger">*</span></label>
                                        <input type="number" name="items[0][quantity]" class="form-control" step="0.001" min="0.001" required placeholder="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">الوحدة</label>
                                        <select name="items[0][unit_id]" class="form-select" id="unit_0">
                                            <option value="">-- الوحدة --</option>
                                            <?php foreach ($units as $u): ?>
                                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['unit_name_ar']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">التكلفة</label>
                                        <input type="number" name="items[0][unit_cost]" class="form-control" step="0.01" id="cost_0" placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">ملاحظات</label>
                                        <input type="text" name="items[0][notes]" class="form-control" placeholder="ملاحظات">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-sm" onclick="addItem()">
                            <i class="bi bi-plus-lg"></i> إضافة صنف
                        </button>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">ملاحظات التحويل</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات عامة عن التحويل..."></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ التحويل
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemIndex = 1;

        function updateToStores() {
            const fromStore = document.getElementById('from_store');
            const toStore = document.getElementById('to_store');
            const fromId = fromStore.value;

            // Disable same store in to_store
            for (let opt of toStore.options) {
                if (opt.value === fromId) {
                    opt.disabled = true;
                } else {
                    opt.disabled = false;
                }
            }
        }

        function updateProductInfo(select, index) {
            const option = select.options[select.selectedIndex];
            const cost = option.dataset.cost || 0;
            document.getElementById('cost_' + index).value = cost;
        }

        function addItem() {
            const container = document.getElementById('itemsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <span class="remove-btn" onclick="this.closest('.item-row').remove()"><i class="bi bi-trash"></i></span>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">الصنف <span class="text-danger">*</span></label>
                        <select name="items[${itemIndex}][product_id]" class="form-select product-select" required onchange="updateProductInfo(this, ${itemIndex})">
                            <option value="">-- اختر الصنف --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" data-cost="<?= $p['cost_price'] ?>">
                                    <?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['product_code'] ?? $p['manual_code'] ?? 'N/A') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الكمية <span class="text-danger">*</span></label>
                        <input type="number" name="items[${itemIndex}][quantity]" class="form-control" step="0.001" min="0.001" required placeholder="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الوحدة</label>
                        <select name="items[${itemIndex}][unit_id]" class="form-select" id="unit_${itemIndex}">
                            <option value="">-- الوحدة --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['unit_name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">التكلفة</label>
                        <input type="number" name="items[${itemIndex}][unit_cost]" class="form-control" step="0.01" id="cost_${itemIndex}" placeholder="0.00">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">ملاحظات</label>
                        <input type="text" name="items[${itemIndex}][notes]" class="form-control" placeholder="ملاحظات">
                    </div>
                </div>
            `;
            container.appendChild(newRow);
            itemIndex++;
        }
    </script>
</body>
</html>
