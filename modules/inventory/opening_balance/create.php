<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إضافة رصيد افتتاحي';

$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

$stores = $db->query("
    SELECT s.*, b.branch_name, b.id as branch_id_real
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 
    ORDER BY b.branch_name, s.store_name
")->fetchAll();

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
            $quantity = floatval($item['quantity']);
            $unit_cost = floatval($item['unit_cost'] ?? 0);
            $sell_price = floatval($item['sell_price'] ?? 0);
            $discount = floatval($item['discount_percent'] ?? 0);
            $vat = floatval($item['vat_percent'] ?? 0);
            $exp_date = !empty($item['exp_date']) ? $item['exp_date'] : null;
            $total_cost = $quantity * $unit_cost;

            // Insert opening balance transaction
            $db->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, reference_type, store_id, product_id, quantity, quantity_base, 
                 unit_cost, unit_price, discount_percent, vat_percent, total_cost, notes, created_by, created_at)
                VALUES ('opening_balance', 'opening_balance', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $store_id, $product_id, $quantity, $quantity,
                $unit_cost, $sell_price, $discount, $vat, $total_cost,
                'رصيد افتتاحي', $user_id
            ]);

            // Update or insert inventory item
            $existing = $db->prepare("
                SELECT id, quantity FROM inventory_items 
                WHERE store_id = ? AND product_id = ?
            ");
            $existing->execute([$store_id, $product_id]);
            $ex = $existing->fetch();

            if ($ex) {
                $db->prepare("
                    UPDATE inventory_items 
                    SET quantity = quantity + ?, unit_cost = ?, sell_price = ?, 
                        discount_percent = ?, vat_percent = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$quantity, $unit_cost, $sell_price, $discount, $vat, $ex['id']]);
            } else {
                $db->prepare("
                    INSERT INTO inventory_items 
                    (store_id, product_id, quantity, unit_cost, sell_price, 
                     discount_percent, vat_percent, reorder_point, max_stock, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW())
                ")->execute([$store_id, $product_id, $quantity, $unit_cost, $sell_price, $discount, $vat]);
            }

            // Insert batch if expiry date provided
            if ($exp_date) {
                $batch_code = 'OB-' . time() . '-' . rand(100, 999);
                $db->prepare("
                    INSERT INTO inventory_batches 
                    (product_id, store_id, batch_number, exp_date, remaining_qty, unit_cost, sell_price, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([$product_id, $store_id, $batch_code, $exp_date, $quantity, $unit_cost, $sell_price]);
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
        .item-row { background: white; border-radius: 10px; padding: 12px; margin-bottom: 10px; border: 1px solid #e9ecef; }
        .item-row:hover { border-color: var(--primary); box-shadow: 0 2px 8px rgba(102,126,234,0.1); }
        .product-display { background: #f8f9fa; border-radius: 8px; padding: 10px; min-height: 44px; display: flex; align-items: center; }
        .product-selected { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%) !important; }
        /* FIX: Search button z-index and positioning */
        .barcode-wrap { position: relative; z-index: 1; }
        .barcode-wrap .btn-f2 { 
            position: absolute; left: 0; top: 0; bottom: 0; 
            border-radius: 8px 0 0 8px; border: 1px solid #dee2e6; 
            background: #f8f9fa; padding: 0 12px; cursor: pointer; 
            z-index: 5; /* Above sidebar */
            width: 40px;
            display: flex; align-items: center; justify-content: center;
        }
        .barcode-wrap .btn-f2:hover { background: #e9ecef; border-color: var(--primary); }
        .barcode-wrap .btn-f2 i { pointer-events: none; } /* Prevent icon from stealing click */
        .barcode-wrap input { padding-left: 45px; }
        .date-field { display: none; }
        .date-field.active { display: block; }
        .summary-bar { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 15px 20px; border-radius: 12px; position: sticky; bottom: 20px; z-index: 100; }
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
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" id="balanceForm">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">الفرع</label>
                                <select id="branch_filter" class="form-select" onchange="filterStores()">
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">المخزن <span class="text-danger">*</span></label>
                                <select name="store_id" id="store_select" class="form-select" required>
                                    <option value="">-- اختر المخزن --</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? 0 ?>">
                                            <?= htmlspecialchars($s['store_name']) ?> (<?= htmlspecialchars($s['branch_name'] ?? 'مركزي') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="addRow()">
                                    <i class="bi bi-plus-lg"></i> إضافة صنف
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Container -->
                <div id="itemsContainer"></div>

                <!-- Summary Bar -->
                <div class="summary-bar mt-3">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <i class="bi bi-boxes"></i> الأصناف: <strong id="sumItems">0</strong>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-cash"></i> إجمالي التكلفة: <strong id="sumCost">0.00</strong> ج
                        </div>
                        <div class="col-md-6 text-start">
                            <button type="submit" class="btn btn-light fw-bold"><i class="bi bi-save"></i> حفظ الرصيد الافتتاحي</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../js/product-search.js"></script>
    <script>
        let itemCounter = 0;
        let activeRowId = null;
        
        function filterStores() {
            const branchId = document.getElementById('branch_filter').value;
            const sel = document.getElementById('store_select');
            let firstVisible = null;
            
            sel.querySelectorAll('option[data-branch]').forEach(opt => {
                const match = !branchId || opt.dataset.branch === branchId;
                opt.style.display = match ? '' : 'none';
                if (match && !firstVisible) firstVisible = opt.value;
            });
            
            if (firstVisible && !sel.value) sel.value = firstVisible;
        }
        
        function addRow() {
            if (!document.getElementById('store_select').value) {
                alert('اختر المخزن أولاً');
                document.getElementById('store_select').focus();
                return;
            }
            
            itemCounter++;
            const id = itemCounter;
            const html = `
                <div class="item-row" id="itemRow_${id}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small">الباركود / F2 للبحث</label>
                            <div class="barcode-wrap">
                                <input type="text" class="form-control" id="barcode_${id}" placeholder="ادخل الباركود أو اضغط F2..."
                                       onkeydown="handleBarcode(event, ${id})" autocomplete="off">
                                <button type="button" class="btn-f2" onclick="openProductSearch(${id})" title="بحث متقدم (F2)">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <input type="hidden" name="items[${id}][product_id]" id="productId_${id}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">الصنف</label>
                            <div class="product-display" id="productDisplay_${id}">
                                <span class="text-muted small">ادخل الباركود واضغط Enter أو اضغط F2</span>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">الكمية *</label>
                            <input type="number" name="items[${id}][quantity]" id="qty_${id}" class="form-control" step="0.001" min="0.001" value="1" onchange="calcRow(${id})" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">التكلفة</label>
                            <input type="number" name="items[${id}][unit_cost]" id="cost_${id}" class="form-control" step="0.01" min="0" onchange="calcRow(${id})">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">البيع</label>
                            <input type="number" name="items[${id}][sell_price]" id="sell_${id}" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small">الخصم %</label>
                            <input type="number" name="items[${id}][discount_percent]" id="disc_${id}" class="form-control" step="0.01" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-1 date-field" id="dateField_${id}">
                            <label class="form-label small">الصلاحية</label>
                            <input type="date" name="items[${id}][exp_date]" id="expDate_${id}" class="form-control">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm mt-4" onclick="document.getElementById('itemRow_${id}').remove(); updateSummary();">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <small class="text-muted" id="productInfo_${id}"></small>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
            setTimeout(() => document.getElementById('barcode_' + id).focus(), 100);
        }
        
        function handleBarcode(e, rowId) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcode = e.target.value.trim();
                if (!barcode) {
                    openProductSearch(rowId);
                    return;
                }
                
                const storeId = document.getElementById('store_select').value;
                if (!storeId) { alert('اختر المخزن أولاً'); return; }
                
                ProductSearch.quickSearch(storeId, barcode, 'barcode')
                    .then(products => {
                        if (products.length === 1) {
                            fillProduct(rowId, products[0]);
                        } else if (products.length > 1) {
                            openProductSearch(rowId, barcode);
                        } else {
                            return ProductSearch.quickSearch(storeId, barcode, 'name');
                        }
                        return null;
                    })
                    .then(products => {
                        if (products && products.length === 1) {
                            fillProduct(rowId, products[0]);
                        } else if (products && products.length > 1) {
                            openProductSearch(rowId, barcode);
                        } else if (products) {
                            alert('الصنف غير موجود');
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        alert('خطأ في البحث');
                    });
            }
            if (e.key === 'F2') {
                e.preventDefault();
                openProductSearch(rowId, e.target.value.trim());
            }
        }
        
        function openProductSearch(rowId, prefillQuery) {
            const storeId = document.getElementById('store_select').value;
            if (!storeId) { 
                alert('اختر المخزن أولاً'); 
                document.getElementById('store_select').focus();
                return;
            }
            
            activeRowId = rowId;
            
            ProductSearch.open({
                storeId: parseInt(storeId),
                onSelect: function(product) {
                    if (activeRowId) {
                        fillProduct(activeRowId, product);
                        activeRowId = null;
                    }
                }
            });
            
            if (prefillQuery) {
                localStorage.setItem('product_search_prefill', prefillQuery);
            }
        }
        
        function fillProduct(rowId, product) {
            document.getElementById('productId_' + rowId).value = product.id;
            
            const disp = document.getElementById('productDisplay_' + rowId);
            disp.innerHTML = `<strong>${product.product_name}</strong>
                <small class="text-muted ms-2">${product.product_code || product.manual_code || product.barcode || ''}</small>`;
            disp.classList.add('product-selected');
            
            document.getElementById('cost_' + rowId).value = product.cost_price || product.unit_cost || 0;
            document.getElementById('sell_' + rowId).value = product.sell_price || 0;
            
            // Show batch info if available
            let infoHtml = '';
            if (product.stock_qty > 0) {
                infoHtml += `<span class="badge bg-info me-1">رصيد: ${product.stock_qty}</span>`;
            }
            if (product.company_name) {
                infoHtml += `<span class="badge bg-secondary me-1">${product.company_name}</span>`;
            }
            if (product.scientific_name) {
                infoHtml += `<span class="text-muted">${product.scientific_name}</span>`;
            }
            
            // Handle expiry
            if (product.has_expire) {
                document.getElementById('dateField_' + rowId).classList.add('active');
                document.getElementById('dateField_' + rowId).querySelector('input').required = true;
                
                if (product.batches && product.batches.length > 0) {
                    const batch = product.batches[0];
                    document.getElementById('expDate_' + rowId).value = batch.exp_date;
                    infoHtml += `<span class="badge bg-warning text-dark me-1">صلاحية: ${batch.exp_date}</span>`;
                }
            }
            
            document.getElementById('productInfo_' + rowId).innerHTML = infoHtml;
            
            // Auto add next row
            setTimeout(() => {
                const lastRow = document.querySelector('.item-row:last-child');
                if (lastRow && lastRow.id === 'itemRow_' + rowId) {
                    addRow();
                }
            }, 200);
            
            calcRow(rowId);
            updateSummary();
        }
        
        function calcRow(rowId) {
            const qty = parseFloat(document.getElementById('qty_' + rowId).value) || 0;
            const cost = parseFloat(document.getElementById('cost_' + rowId).value) || 0;
            // Visual feedback only - no total field in this form
        }
        
        function updateSummary() {
            let count = 0;
            let totalCost = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const id = row.id.replace('itemRow_', '');
                const q = parseFloat(document.getElementById('qty_' + id)?.value) || 0;
                const c = parseFloat(document.getElementById('cost_' + id)?.value) || 0;
                const pid = document.getElementById('productId_' + id)?.value;
                if (pid && q > 0) {
                    count++;
                    totalCost += q * c;
                }
            });
            document.getElementById('sumItems').textContent = count;
            document.getElementById('sumCost').textContent = totalCost.toFixed(2);
        }
        
        // Validate store selected before submit
        document.getElementById('balanceForm').addEventListener('submit', function(e) {
            if (!document.getElementById('store_select').value) {
                e.preventDefault();
                alert('اختر المخزن');
                return;
            }
            const validItems = document.querySelectorAll('.item-row').length;
            if (validItems === 0) {
                e.preventDefault();
                alert('أضف صنف واحد على الأقل');
            }
        });
    </script>
</body>
</html>
