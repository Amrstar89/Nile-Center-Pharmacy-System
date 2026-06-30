<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إضافة رصيد افتتاحي';

$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

$stores = $db->query("
    SELECT s.*, b.branch_name 
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 
    ORDER BY b.branch_name, s.store_name
")->fetchAll();

$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $errors = [];
        $store_id = intval($_POST['store_id'] ?? 0);

        if ($store_id <= 0) $errors[] = 'يجب اختيار المخزن';
        if (empty($_POST['items']) || !is_array($_POST['items'])) $errors[] = 'يجب إضافة أصناف';

        if (!empty($errors)) throw new Exception(implode('<br>', $errors));

        $user_id = $_SESSION['user_id'] ?? 1;

        foreach ($_POST['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) continue;

            $product_id = intval($item['product_id']);
            $batch_id = !empty($item['batch_id']) ? intval($item['batch_id']) : null;
            $quantity = floatval($item['quantity']);
            $unit_cost = floatval($item['unit_cost'] ?? 0);
            $sell_price = floatval($item['sell_price'] ?? 0);
            $discount = floatval($item['discount_percent'] ?? 0);
            $vat = floatval($item['vat_percent'] ?? 0);
            $total_cost = $quantity * $unit_cost;

            $db->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, reference_type, store_id, product_id, batch_id, quantity, quantity_base, unit_cost, unit_price, discount_percent, vat_percent, total_cost, notes, created_by, created_at)
                VALUES ('opening_balance', 'opening_balance', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $store_id, $product_id, $batch_id, $quantity, $quantity,
                $unit_cost, $sell_price, $discount, $vat, $total_cost,
                'رصيد افتتاحي', $user_id
            ]);

            $existing = $db->prepare("SELECT id FROM inventory_items WHERE store_id = ? AND product_id = ? AND (batch_id = ? OR (batch_id IS NULL AND ? IS NULL))");
            $existing->execute([$store_id, $product_id, $batch_id, $batch_id]);

            if ($existing->fetch()) {
                $db->prepare("
                    UPDATE inventory_items 
                    SET quantity = quantity + ?, unit_cost = ?, sell_price = ?, discount_percent = ?, vat_percent = ?, updated_at = NOW()
                    WHERE store_id = ? AND product_id = ? AND (batch_id = ? OR (batch_id IS NULL AND ? IS NULL))
                ")->execute([$quantity, $unit_cost, $sell_price, $discount, $vat, $store_id, $product_id, $batch_id, $batch_id]);
            } else {
                $db->prepare("
                    INSERT INTO inventory_items 
                    (store_id, product_id, batch_id, quantity, unit_cost, sell_price, discount_percent, vat_percent, reorder_point, max_stock, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW())
                ")->execute([$store_id, $product_id, $batch_id, $quantity, $unit_cost, $sell_price, $discount, $vat]);
            }

            if ($batch_id) {
                $db->prepare("UPDATE inventory_batches SET remaining_qty = remaining_qty + ? WHERE id = ?")
                    ->execute([$quantity, $batch_id]);
            }
        }

        $db->commit();
        header("Location: index.php?success=1");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
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
        .item-row { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; border: 1px solid #e9ecef; }
        .totals-bar { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .totals-bar .total-value { font-size: 24px; font-weight: bold; }
        .product-info { background: white; border-radius: 8px; padding: 10px; border: 1px solid #dee2e6; }
        .date-field { display: none; }
        .date-field.active { display: block; }
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

            <form method="POST" id="balanceForm">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">الفرع</label>
                                <select id="branch_filter" class="form-select" onchange="filterStoresByBranch()">
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="0">مركزي</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المخزن <span class="text-danger">*</span></label>
                                <select name="store_id" id="store_select" class="form-select" required onchange="onStoreChange()">
                                    <option value="">-- اختر المخزن --</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? '0' ?>" style="display:none;">
                                            <?= htmlspecialchars($s['store_name']) ?> <?= $s['branch_name'] ? '(' . htmlspecialchars($s['branch_name']) . ')' : '(مركزي)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-check"></i> الأصناف</h5>
                        <button type="button" class="btn btn-success" onclick="addItemRow()">
                            <i class="bi bi-plus-lg"></i> إضافة صنف
                        </button>
                    </div>
                    <div class="card-body" id="itemsContainer"></div>
                </div>

                <div class="card-footer mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> حفظ الرصيد الافتتاحي
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../../../includes/product-search-popup.php'; ?>

    <script>
        let itemCounter = 0;
        let currentRow = 0;

        function filterStoresByBranch() {
            const branchId = document.getElementById('branch_filter').value;
            const storeSelect = document.getElementById('store_select');
            const options = storeSelect.querySelectorAll('option[data-branch]');
            options.forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                opt.style.display = (!branchId || opt.getAttribute('data-branch') === branchId) ? '' : 'none';
            });
            storeSelect.value = '';
        }

        function onStoreChange() {
            document.getElementById('itemsContainer').innerHTML = '';
            itemCounter = 0;
            addItemRow();
        }

        function addItemRow() {
            itemCounter++;
            const html = `
                <div class="item-row" id="itemRow_${itemCounter}">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">الباركود</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="barcode_${itemCounter}" placeholder="ادخل الباركود..."
                                       onkeydown="handleBarcode(event, ${itemCounter})" autocomplete="off">
                                <button type="button" class="btn btn-outline-primary" onclick="openProductSearchModal(${itemCounter})">
                                    <i class="bi bi-search"></i> F2
                                </button>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">الصنف</label>
                            <div class="product-info">
                                <div id="productName_${itemCounter}" class="fw-bold text-muted">اختر الصنف...</div>
                                <input type="hidden" name="items[${itemCounter}][product_id]" id="productId_${itemCounter}">
                                <input type="hidden" name="items[${itemCounter}][batch_id]" id="batchId_${itemCounter}">
                                <div class="row mt-2 small">
                                    <div class="col-4">تكلفة: <span id="costDisplay_${itemCounter}">-</span></div>
                                    <div class="col-4">بيع: <span id="sellDisplay_${itemCounter}">-</span></div>
                                    <div class="col-4">ربح: <span id="profitDisplay_${itemCounter}">-</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الكمية <span class="text-danger">*</span></label>
                            <input type="number" name="items[${itemCounter}][quantity]" class="form-control" step="0.001" min="0.001" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-danger w-100" onclick="document.getElementById('itemRow_${itemCounter}').remove()">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="col-md-3 date-field" id="dateField_${itemCounter}">
                            <label class="form-label">تاريخ الصلاحية</label>
                            <input type="date" name="items[${itemCounter}][exp_date]" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">تكلفة الوحدة</label>
                            <input type="number" name="items[${itemCounter}][unit_cost]" id="unitCost_${itemCounter}" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">سعر البيع</label>
                            <input type="number" name="items[${itemCounter}][sell_price]" id="sellPrice_${itemCounter}" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الخصم %</label>
                            <input type="number" name="items[${itemCounter}][discount_percent]" class="form-control" step="0.01" min="0" max="100" value="0">
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
            setTimeout(() => document.getElementById('barcode_' + itemCounter).focus(), 100);
        }

        function handleBarcode(event, index) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const barcode = event.target.value.trim();
                if (!barcode) return;
                fetch(`../../../api/product-search.php?barcode=${encodeURIComponent(barcode)}`)
                    .then(r => r.json()).then(data => {
                        if (data.length > 0) fillProductData(index, data[0]);
                        else alert('الصنف غير موجود');
                    });
            }
            if (event.key === 'F2') {
                event.preventDefault();
                currentRow = index;
                const modal = new bootstrap.Modal(document.getElementById('productSearchModal'));
                modal.show();
            }
        }

        function fillProductData(index, product) {
            document.getElementById('productId_' + index).value = product.id;
            document.getElementById('productName_' + index).innerHTML = `<strong>${product.product_name}</strong><small class="d-block text-muted">${product.product_code || product.manual_code || ''}</small>`;
            document.getElementById('costDisplay_' + index).textContent = product.cost_price || 0;
            document.getElementById('sellDisplay_' + index).textContent = product.sell_price || 0;
            const profit = product.sell_price > 0 ? ((product.sell_price - product.cost_price) / product.sell_price * 100).toFixed(1) : 0;
            document.getElementById('profitDisplay_' + index).textContent = profit + '%';
            document.getElementById('unitCost_' + index).value = product.cost_price || 0;
            document.getElementById('sellPrice_' + index).value = product.sell_price || 0;
            
            if (product.has_expire) {
                document.getElementById('dateField_' + index).classList.add('active');
            }
        }

        function openProductSearchModal(index) {
            currentRow = index;
            const modal = new bootstrap.Modal(document.getElementById('productSearchModal'));
            modal.show();
            loadProducts();
        }

        function loadProducts() {
            const tbody = document.getElementById('searchResultsBody');
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
            fetch('../../../api/product-search.php')
                .then(r => r.json()).then(data => {
                    tbody.innerHTML = '';
                    data.forEach(p => {
                        const row = document.createElement('tr');
                        row.style.cursor = 'pointer';
                        row.onclick = () => { fillProductData(currentRow, p); bootstrap.Modal.getInstance(document.getElementById('productSearchModal')).hide(); };
                        row.innerHTML = `<td><code>${p.product_code || p.manual_code || 'N/A'}</code></td><td><strong>${p.product_name}</strong></td><td>${p.company_name || '-'}</td><td>${p.sell_price || 0}</td><td>${p.cost_price || 0}</td><td><button class="btn btn-sm btn-primary"><i class="bi bi-check"></i></button></td>`;
                        tbody.appendChild(row);
                    });
                });
        }
    </script>
</body>
</html>