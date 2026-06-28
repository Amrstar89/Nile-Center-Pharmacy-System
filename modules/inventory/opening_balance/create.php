<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إضافة رصيد افتتاحي';

// Get branches
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Get active stores with branch info
$stores = $db->query("
    SELECT s.*, b.branch_name 
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 
    ORDER BY b.branch_name, s.store_name
")->fetchAll();

// Get products
$products = $db->query("
    SELECT id, product_name, product_code, manual_code, cost_price, sell_price, unit1_id 
    FROM products 
    WHERE is_active = 1 
    ORDER BY product_name
")->fetchAll();

// Get units
$units = $db->query("SELECT id, unit_name_ar FROM product_units WHERE is_active = 1")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $errors = [];
        $store_id = intval($_POST['store_id'] ?? 0);

        if ($store_id <= 0) {
            $errors[] = 'يجب اختيار المخزن';
        }
        if (empty($_POST['items']) || !is_array($_POST['items'])) {
            $errors[] = 'يجب إضافة أصناف';
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        $user_id = $_SESSION['user_id'] ?? 1;

        foreach ($_POST['items'] as $item) {
            if (!empty($item['product_id']) && !empty($item['quantity']) && $item['quantity'] > 0) {
                $product_id = intval($item['product_id']);
                $batch_id = !empty($item['batch_id']) ? intval($item['batch_id']) : null;
                $quantity = floatval($item['quantity']);
                $unit_cost = floatval($item['unit_cost'] ?? 0);
                $sell_price = floatval($item['sell_price'] ?? 0);
                $discount = floatval($item['discount_percent'] ?? 0);
                $vat = floatval($item['vat_percent'] ?? 0);
                $total_cost = $quantity * $unit_cost;

                // Insert inventory transaction (opening balance)
                $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_type, reference_type, store_id, product_id, batch_id, quantity, quantity_base, unit_cost, unit_price, discount_percent, vat_percent, total_cost, notes, created_by, created_at)
                    VALUES ('opening_balance', 'opening_balance', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $store_id,
                    $product_id,
                    $batch_id,
                    $quantity,
                    $quantity * floatval($item['unit_conversion'] ?? 1),
                    $unit_cost,
                    $sell_price,
                    $discount,
                    $vat,
                    $total_cost,
                    'رصيد افتتاحي',
                    $user_id
                ]);

                // Insert or update inventory_items
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

                // Update batch remaining_qty if batch selected
                if ($batch_id) {
                    $db->prepare("UPDATE inventory_batches SET remaining_qty = remaining_qty + ? WHERE id = ?")
                        ->execute([$quantity, $batch_id]);
                }
            }
        }

        $db->commit();
        header("Location: index.php?success=1");
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
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin-bottom: 20px; }
        .item-row { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; position: relative; border: 1px solid #e9ecef; }
        .remove-btn { position: absolute; left: 10px; top: 10px; color: #dc3545; cursor: pointer; }
        .totals-bar { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .totals-bar .total-value { font-size: 24px; font-weight: bold; }
        .batch-info { background: #e7f3ff; border-radius: 5px; padding: 5px 10px; font-size: 0.85rem; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body style="margin: 0; padding: 0;">
    <?= $sidebar ?? '' ?>
    <div class="main-content" style="margin-right: 260px; padding: 20px; min-height: 100vh;">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-plus-lg"></i> <?= $page_title ?></h2>
                <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="balanceForm">
                <!-- Branch & Store Selection -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="section-title">المخزن</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">الفرع</label>
                                <select id="branch_filter" class="form-select" onchange="filterStoresByBranch()">
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                    <option value="0">مركزي (بدون فرع)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <select name="store_id" id="store_id" class="form-select" required onchange="updateStoreInfo()">
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

                <!-- Totals Bar -->
                <div class="totals-bar" id="totalsBar" style="display: none;">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div>إجمالي التكلفة</div>
                            <div class="total-value" id="totalCost">0.00 ج</div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div>إجمالي البيع</div>
                            <div class="total-value" id="totalSell">0.00 ج</div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div>عدد الأصناف</div>
                            <div class="total-value" id="totalItems">0</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="section-title">الأصناف</h5>

                        <!-- Product Search Button -->
                        <button type="button" class="btn btn-primary mb-3" onclick="openProductSearchModal()">
                            <i class="bi bi-search"></i> بحث عن صنف (F2)
                        </button>

                        <div id="itemsContainer">
                            <div class="item-row" id="item_row_0">
                                <span class="remove-btn" onclick="removeItem(0)" style="display:none;"><i class="bi bi-trash"></i></span>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">الصنف <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" id="product_name_0" class="form-control" readonly placeholder="اضغط F2 للبحث" onclick="openProductSearchModal(0)">
                                            <input type="hidden" name="items[0][product_id]" id="product_id_0">
                                            <button type="button" class="btn btn-outline-primary" onclick="openProductSearchModal(0)"><i class="bi bi-search"></i></button>
                                        </div>
                                        <div class="batch-info mt-1 d-none" id="batch_info_0">
                                            <i class="bi bi-calendar-check"></i> 
                                            <span id="exp_date_0"></span>
                                            <span class="badge bg-success ms-1" id="batch_qty_0"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">الكمية <span class="text-danger">*</span></label>
                                        <input type="number" name="items[0][quantity]" id="qty_0" class="form-control" step="0.001" min="0.001" required placeholder="0" onchange="calculateRow(0)">
                                        <input type="hidden" id="max_qty_0" value="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">الوحدة</label>
                                        <select name="items[0][unit_id]" id="unit_0" class="form-select" onchange="calculateRow(0)">
                                            <option value="">-- الوحدة --</option>
                                            <?php foreach ($units as $u): ?>
                                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['unit_name_ar']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">التكلفة</label>
                                        <input type="number" name="items[0][unit_cost]" id="cost_0" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(0)">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">سعر البيع</label>
                                        <input type="number" name="items[0][sell_price]" id="sell_0" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(0)">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">الخصم %</label>
                                        <input type="number" name="items[0][discount_percent]" id="discount_0" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(0)">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-2">
                                        <label class="form-label">الضريبة %</label>
                                        <input type="number" name="items[0][vat_percent]" id="vat_0" class="form-control" step="0.01" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">الباركود</label>
                                        <input type="text" id="barcode_0" class="form-control" placeholder="Scan barcode..." onkeydown="handleBarcode(event, 0)">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">إجمالي التكلفة</label>
                                        <input type="text" id="row_total_cost_0" class="form-control" readonly value="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">إجمالي البيع</label>
                                        <input type="text" id="row_total_sell_0" class="form-control" readonly value="0.00">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-success w-100" onclick="addItem()"><i class="bi bi-plus-lg"></i></button>
                                    </div>
                                </div>
                                <input type="hidden" name="items[0][batch_id]" id="batch_id_0">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ الرصيد
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php 
    $api_base_path = '../../../api';
    include __DIR__ . '/../../../includes/product-search-popup.php'; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemIndex = 1;
        let currentRowIndex = 0;

        function filterStoresByBranch() {
            const branchId = document.getElementById('branch_filter').value;
            const storeSelect = document.getElementById('store_id');
            const options = storeSelect.querySelectorAll('option[data-branch]');

            options.forEach(opt => {
                if (!opt.value) {
                    opt.style.display = '';
                    return;
                }
                const optBranch = opt.getAttribute('data-branch') || '0';
                if (!branchId || optBranch === branchId) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });

            storeSelect.value = '';
            updateStoreInfo();
        }

        function openProductSearchModal(rowIndex = 0) {
            currentRowIndex = rowIndex;
            const storeId = document.getElementById('store_id').value;

            if (!storeId) {
                alert('يرجى اختيار المخزن أولاً');
                document.getElementById('store_id').focus();
                return;
            }

            openProductSearch('product_' + rowIndex, storeId);
            const modal = new bootstrap.Modal(document.getElementById('productSearchModal'));
            modal.show();
        }

        // Listen for product selection from popup
        document.addEventListener('productSelected', function(e) {
            const product = e.detail;
            fillProductData(currentRowIndex, product);
        });

        function fillProductData(index, product) {
            document.getElementById('product_id_' + index).value = product.id;
            document.getElementById('product_name_' + index).value = product.name;

            if (product.batch) {
                document.getElementById('batch_id_' + index).value = product.batch.id;
                document.getElementById('cost_' + index).value = product.batch.unit_cost;
                document.getElementById('sell_' + index).value = product.batch.sell_price;
                document.getElementById('max_qty_' + index).value = product.batch.remaining_qty;

                document.getElementById('batch_info_' + index).classList.remove('d-none');
                document.getElementById('exp_date_' + index).textContent = product.batch.exp_date;
                document.getElementById('batch_qty_' + index).textContent = 'رصيد: ' + product.batch.remaining_qty;
            } else {
                document.getElementById('cost_' + index).value = product.cost_price || 0;
                document.getElementById('sell_' + index).value = product.sell_price || 0;
                document.getElementById('batch_info_' + index).classList.add('d-none');
            }

            calculateRow(index);
            document.getElementById('qty_' + index).focus();
        }

        function handleBarcode(event, index) {
            if (event.key === 'Enter') {
                const barcode = event.target.value;
                if (barcode) {
                    fetch(`../../../api/product-search.php?barcode=${encodeURIComponent(barcode)}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.length > 0) {
                                fillProductData(index, data[0]);
                            } else {
                                alert('الصنف غير موجود');
                            }
                        });
                }
            }
        }

        function calculateRow(index) {
            const qty = parseFloat(document.getElementById('qty_' + index).value) || 0;
            const cost = parseFloat(document.getElementById('cost_' + index).value) || 0;
            const sell = parseFloat(document.getElementById('sell_' + index).value) || 0;
            const discount = parseFloat(document.getElementById('discount_' + index).value) || 0;

            const totalCost = qty * cost;
            const totalSell = qty * sell * (1 - discount / 100);

            document.getElementById('row_total_cost_' + index).value = totalCost.toFixed(2);
            document.getElementById('row_total_sell_' + index).value = totalSell.toFixed(2);

            calculateTotals();
        }

        function calculateTotals() {
            let totalCost = 0;
            let totalSell = 0;
            let itemCount = 0;

            for (let i = 0; i < itemIndex; i++) {
                const row = document.getElementById('item_row_' + i);
                if (row && row.style.display !== 'none') {
                    const qty = parseFloat(document.getElementById('qty_' + i)?.value) || 0;
                    if (qty > 0) {
                        const cost = parseFloat(document.getElementById('cost_' + i)?.value) || 0;
                        const sell = parseFloat(document.getElementById('sell_' + i)?.value) || 0;
                        const discount = parseFloat(document.getElementById('discount_' + i)?.value) || 0;

                        totalCost += qty * cost;
                        totalSell += qty * sell * (1 - discount / 100);
                        itemCount++;
                    }
                }
            }

            document.getElementById('totalCost').textContent = totalCost.toFixed(2) + ' ج';
            document.getElementById('totalSell').textContent = totalSell.toFixed(2) + ' ج';
            document.getElementById('totalItems').textContent = itemCount;

            document.getElementById('totalsBar').style.display = itemCount > 0 ? 'block' : 'none';
        }

        function addItem() {
            const container = document.getElementById('itemsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.id = 'item_row_' + itemIndex;
            newRow.innerHTML = `
                <span class="remove-btn" onclick="removeItem(${itemIndex})"><i class="bi bi-trash"></i></span>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">الصنف <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" id="product_name_${itemIndex}" class="form-control" readonly placeholder="اضغط F2 للبحث" onclick="openProductSearchModal(${itemIndex})">
                            <input type="hidden" name="items[${itemIndex}][product_id]" id="product_id_${itemIndex}">
                            <button type="button" class="btn btn-outline-primary" onclick="openProductSearchModal(${itemIndex})"><i class="bi bi-search"></i></button>
                        </div>
                        <div class="batch-info mt-1 d-none" id="batch_info_${itemIndex}">
                            <i class="bi bi-calendar-check"></i> 
                            <span id="exp_date_${itemIndex}"></span>
                            <span class="badge bg-success ms-1" id="batch_qty_${itemIndex}"></span>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">الكمية <span class="text-danger">*</span></label>
                        <input type="number" name="items[${itemIndex}][quantity]" id="qty_${itemIndex}" class="form-control" step="0.001" min="0.001" required placeholder="0" onchange="calculateRow(${itemIndex})">
                        <input type="hidden" id="max_qty_${itemIndex}" value="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الوحدة</label>
                        <select name="items[${itemIndex}][unit_id]" id="unit_${itemIndex}" class="form-select" onchange="calculateRow(${itemIndex})">
                            <option value="">-- الوحدة --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['unit_name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">التكلفة</label>
                        <input type="number" name="items[${itemIndex}][unit_cost]" id="cost_${itemIndex}" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(${itemIndex})">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">سعر البيع</label>
                        <input type="number" name="items[${itemIndex}][sell_price]" id="sell_${itemIndex}" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(${itemIndex})">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الخصم %</label>
                        <input type="number" name="items[${itemIndex}][discount_percent]" id="discount_${itemIndex}" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(${itemIndex})">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-2">
                        <label class="form-label">الضريبة %</label>
                        <input type="number" name="items[${itemIndex}][vat_percent]" id="vat_${itemIndex}" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">الباركود</label>
                        <input type="text" id="barcode_${itemIndex}" class="form-control" placeholder="Scan barcode..." onkeydown="handleBarcode(event, ${itemIndex})">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">إجمالي التكلفة</label>
                        <input type="text" id="row_total_cost_${itemIndex}" class="form-control" readonly value="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">إجمالي البيع</label>
                        <input type="text" id="row_total_sell_${itemIndex}" class="form-control" readonly value="0.00">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-success w-100" onclick="addItem()"><i class="bi bi-plus-lg"></i></button>
                    </div>
                </div>
                <input type="hidden" name="items[${itemIndex}][batch_id]" id="batch_id_${itemIndex}">
            `;
            container.appendChild(newRow);
            itemIndex++;

            setTimeout(() => {
                document.getElementById('barcode_' + (itemIndex - 1)).focus();
            }, 100);
        }

        function removeItem(index) {
            const row = document.getElementById('item_row_' + index);
            if (row) {
                row.style.display = 'none';
                row.innerHTML = '';
                calculateTotals();
            }
        }

        function updateStoreInfo() {
            // Clear items when store changes
            document.getElementById('itemsContainer').innerHTML = `
                <div class="item-row" id="item_row_0">
                    <span class="remove-btn" onclick="removeItem(0)" style="display:none;"><i class="bi bi-trash"></i></span>
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">الصنف <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" id="product_name_0" class="form-control" readonly placeholder="اضغط F2 للبحث" onclick="openProductSearchModal(0)">
                                <input type="hidden" name="items[0][product_id]" id="product_id_0">
                                <button type="button" class="btn btn-outline-primary" onclick="openProductSearchModal(0)"><i class="bi bi-search"></i></button>
                            </div>
                            <div class="batch-info mt-1 d-none" id="batch_info_0">
                                <i class="bi bi-calendar-check"></i> 
                                <span id="exp_date_0"></span>
                                <span class="badge bg-success ms-1" id="batch_qty_0"></span>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">الكمية <span class="text-danger">*</span></label>
                            <input type="number" name="items[0][quantity]" id="qty_0" class="form-control" step="0.001" min="0.001" required placeholder="0" onchange="calculateRow(0)">
                            <input type="hidden" id="max_qty_0" value="0">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الوحدة</label>
                            <select name="items[0][unit_id]" id="unit_0" class="form-select" onchange="calculateRow(0)">
                                <option value="">-- الوحدة --</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['unit_name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">التكلفة</label>
                            <input type="number" name="items[0][unit_cost]" id="cost_0" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(0)">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">سعر البيع</label>
                            <input type="number" name="items[0][sell_price]" id="sell_0" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(0)">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الخصم %</label>
                            <input type="number" name="items[0][discount_percent]" id="discount_0" class="form-control" step="0.01" placeholder="0.00" onchange="calculateRow(0)">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-2">
                            <label class="form-label">الضريبة %</label>
                            <input type="number" name="items[0][vat_percent]" id="vat_0" class="form-control" step="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الباركود</label>
                            <input type="text" id="barcode_0" class="form-control" placeholder="Scan barcode..." onkeydown="handleBarcode(event, 0)">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">إجمالي التكلفة</label>
                            <input type="text" id="row_total_cost_0" class="form-control" readonly value="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">إجمالي البيع</label>
                            <input type="text" id="row_total_sell_0" class="form-control" readonly value="0.00">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-success w-100" onclick="addItem()"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </div>
                    <input type="hidden" name="items[0][batch_id]" id="batch_id_0">
                </div>
            `;
            itemIndex = 1;
            calculateTotals();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F2') {
                e.preventDefault();
                openProductSearchModal(currentRowIndex);
            }
        });
    </script>
</body>
</html>
