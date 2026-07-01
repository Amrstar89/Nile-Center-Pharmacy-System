<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';

if (!isLoggedIn()) {
    header('Location: ../../../index.php');
    exit;
}

$db = getDB();
$currentUser = getCurrentUser();

// Generate transfer code
$year = date('Y');
$month = date('m');
$stmt = $db->query("SELECT COUNT(*) as cnt FROM inventory_transfers WHERE YEAR(created_at) = $year AND MONTH(created_at) = $month");
$count = $stmt->fetch()['cnt'] + 1;
$transferCode = 'TR-' . $year . $month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

$error = '';

// Get branches
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Get all stores
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

        $from_store_id = intval($_POST['from_store_id'] ?? 0);
        $to_store_id = intval($_POST['to_store_id'] ?? 0);
        $transfer_date = $_POST['transfer_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        $requested_by = $currentUser['id'];

        if ($from_store_id <= 0 || $to_store_id <= 0) {
            throw new Exception('يجب اختيار المخزن المصدر والمخزن الهدف');
        }
        if ($from_store_id == $to_store_id) {
            throw new Exception('المخزن المصدر والمخزن الهدف يجب أن يكونا مختلفين');
        }

        $fromStore = array_values(array_filter($stores, fn($s) => $s['id'] == $from_store_id))[0] ?? null;
        $toStore = array_values(array_filter($stores, fn($s) => $s['id'] == $to_store_id))[0] ?? null;

        if (!$fromStore || !$toStore) {
            throw new Exception('المخزن غير موجود');
        }

        // Insert transfer
        $stmt = $db->prepare("
            INSERT INTO inventory_transfers 
            (transfer_code, from_store_id, to_store_id, from_branch_id, to_branch_id,
             transfer_type, status, notes, requested_by, requested_at, total_items, total_quantity, total_cost, created_at)
            VALUES (?, ?, ?, ?, ?, 'internal', 'pending', ?, ?, NOW(), 0, 0, 0, NOW())
        ");
        $stmt->execute([
            $transferCode, $from_store_id, $to_store_id,
            $fromStore['branch_id'] ?? null, $toStore['branch_id'] ?? null,
            $notes, $requested_by
        ]);
        $transfer_id = $db->lastInsertId();

        // Process items
        $items = $_POST['items'] ?? [];
        $total_items = 0;
        $total_qty = 0;
        $total_cost = 0;

        foreach ($items as $item) {
            $product_id = intval($item['product_id'] ?? 0);
            $batch_id = !empty($item['batch_id']) ? intval($item['batch_id']) : null;
            $qty = floatval($item['qty'] ?? 0);
            $exp_date = !empty($item['exp_date']) ? $item['exp_date'] : null;
            $unit_cost = floatval($item['unit_cost'] ?? 0);

            if ($product_id <= 0 || $qty <= 0) continue;

            // Verify stock availability
            if ($batch_id) {
                $check = $db->prepare("SELECT remaining_qty FROM inventory_batches WHERE id = ? AND store_id = ?");
                $check->execute([$batch_id, $from_store_id]);
                $batch = $check->fetch();
                if (!$batch || floatval($batch['remaining_qty']) < $qty) {
                    throw new Exception('رصيد غير كافٍ للصنف المختار');
                }
            } else {
                $check = $db->prepare("SELECT SUM(quantity) as total FROM inventory_items WHERE product_id = ? AND store_id = ?");
                $check->execute([$product_id, $from_store_id]);
                $stock = $check->fetch();
                if (!$stock || floatval($stock['total']) < $qty) {
                    throw new Exception('رصيد غير كافٍ في المخزن المصدر');
                }
            }

            $item_cost = $qty * $unit_cost;

            $stmt = $db->prepare("
                INSERT INTO inventory_transfer_items
                (transfer_id, product_id, batch_id, requested_qty, unit_cost, total_cost, exp_date, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, '', 'pending')
            ");
            $stmt->execute([$transfer_id, $product_id, $batch_id, $qty, $unit_cost, $item_cost, $exp_date]);

            $total_items++;
            $total_qty += $qty;
            $total_cost += $item_cost;
        }

        if ($total_items == 0) {
            throw new Exception('يجب إضافة صنف واحد على الأقل');
        }

        // Update totals
        $stmt = $db->prepare("UPDATE inventory_transfers SET total_items=?, total_quantity=?, total_cost=? WHERE id=?");
        $stmt->execute([$total_items, $total_qty, $total_cost, $transfer_id]);

        $db->commit();
        header("Location: index.php?success=" . urlencode('تم إنشاء التحويل بنجاح'));
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

$page_title = 'تحويل مخزني جديد';
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
        
        .transfer-row { 
            background: white; border-radius: 12px; padding: 15px; margin-bottom: 12px; 
            border: 1px solid #e9ecef; transition: all 0.2s;
        }
        .transfer-row:hover { border-color: var(--primary); box-shadow: 0 2px 8px rgba(102,126,234,0.1); }
        .transfer-row .row { align-items: end; }
        
        .product-display { 
            background: #f8f9fa; border-radius: 8px; padding: 10px 12px; min-height: 44px;
            display: flex; align-items: center;
        }
        .product-display .prod-name { font-weight: 600; color: #333; }
        .product-display .prod-code { font-size: 11px; color: #888; font-family: monospace; }
        
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
        
        .stock-badge { font-size: 11px; padding: 4px 10px; border-radius: 6px; }
        
        .summary-bar { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); 
            color: white; padding: 15px 20px; border-radius: 12px;
            position: sticky; bottom: 20px; z-index: 100;
        }
        
        /* Product selection display */
        .product-selected {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%) !important;
            border-color: #4caf50 !important;
        }
        .product-selected .prod-name { color: #2e7d32; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .transfer-row { animation: slideIn 0.3s ease; }
        
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><i class="bi bi-arrow-left-right"></i> <?= $page_title ?></h3>
                    <small class="text-muted">رقم التحويل: <span class="badge bg-primary"><?= $transferCode ?></span></small>
                </div>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> رجوع</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" id="transferForm">
                <!-- Header Info -->
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">الفرع المصدر</label>
                                <select id="fromBranch" class="form-select" onchange="filterStores('from')">
                                    <option value="">اختر الفرع</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-primary">المخزن المصدر *</label>
                                <select name="from_store_id" id="fromStore" class="form-select border-primary" required onchange="onSourceStoreChange()">
                                    <option value="">اختر المخزن</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? 0 ?>" style="display:none;">
                                            <?= htmlspecialchars($s['store_name']) ?> (<?= htmlspecialchars($s['branch_name'] ?? 'مركزي') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">الفرع الهدف</label>
                                <select id="toBranch" class="form-select" onchange="filterStores('to')">
                                    <option value="">اختر الفرع</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold text-success">المخزن الهدف *</label>
                                <select name="to_store_id" id="toStore" class="form-select border-success" required>
                                    <option value="">اختر المخزن</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? 0 ?>" style="display:none;">
                                            <?= htmlspecialchars($s['store_name']) ?> (<?= htmlspecialchars($s['branch_name'] ?? 'مركزي') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-3">
                                <label class="form-label">تاريخ التحويل</label>
                                <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">بواسطة</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ملاحظات التحويل</label>
                                <textarea name="notes" class="form-control" rows="1" placeholder="ملاحظات عامة على التحويل..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Header -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-list-check"></i> الأصناف</h5>
                    <button type="button" class="btn btn-success" onclick="addRow()">
                        <i class="bi bi-plus-lg"></i> إضافة صنف
                    </button>
                </div>

                <!-- Column Headers -->
                <div class="row g-2 mb-2 px-3 text-muted small fw-bold d-none d-md-flex">
                    <div class="col-md-3">الصنف (باركود / F2)</div>
                    <div class="col-md-2">تاريخ الصلاحية</div>
                    <div class="col-md-1">الكمية</div>
                    <div class="col-md-1">المخزون</div>
                    <div class="col-md-1">التكلفة</div>
                    <div class="col-md-1">الإجمالي</div>
                    <div class="col-md-1"></div>
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
                            <i class="bi bi-stack"></i> الكمية: <strong id="sumQty">0</strong>
                        </div>
                        <div class="col-md-3">
                            <i class="bi bi-cash"></i> التكلفة: <strong id="sumCost">0.00</strong> ج
                        </div>
                        <div class="col-md-3 text-start">
                            <button type="submit" class="btn btn-light fw-bold"><i class="bi bi-check-lg"></i> حفظ التحويل</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../js/product-search.js"></script>
    <script>
        let rowCount = 0;
        let activeRowId = null;
        
        function filterStores(dir) {
            const branchId = document.getElementById(dir + 'Branch').value;
            const sel = document.getElementById(dir + 'Store');
            sel.querySelectorAll('option[data-branch]').forEach(opt => {
                opt.style.display = (!branchId || opt.dataset.branch === branchId) ? '' : 'none';
            });
            sel.value = '';
            
            if (dir === 'from') {
                document.getElementById('itemsContainer').innerHTML = '';
                rowCount = 0;
                updateSummary();
            }
        }
        
        function onSourceStoreChange() {
            const storeId = document.getElementById('fromStore').value;
            if (!storeId) return;
            
            // Prevent same store selection in target
            const toStore = document.getElementById('toStore');
            const fromVal = document.getElementById('fromStore').value;
            toStore.querySelectorAll('option').forEach(opt => {
                if (opt.value && opt.value === fromVal) opt.disabled = true;
                else opt.disabled = false;
            });
            
            document.getElementById('itemsContainer').innerHTML = '';
            rowCount = 0;
            addRow();
        }
        
        function addRow() {
            if (!document.getElementById('fromStore').value) {
                alert('اختر المخزن المصدر أولاً');
                document.getElementById('fromStore').focus();
                return;
            }
            
            rowCount++;
            const id = rowCount;
            const html = `
                <div class="transfer-row" id="row_${id}">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <div class="barcode-wrap">
                                <input type="text" class="form-control" id="barcode_${id}" placeholder="ادخل الباركود أو اضغط F2..."
                                       onkeydown="handleBarcodeKey(event, ${id})" autocomplete="off">
                                <button type="button" class="btn-f2" onclick="openProductSearch(${id})" title="بحث متقدم (F2)">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <input type="hidden" name="items[${id}][product_id]" id="productId_${id}">
                            <input type="hidden" name="items[${id}][batch_id]" id="batchId_${id}">
                            <div class="product-display mt-2" id="productDisplay_${id}">
                                <span class="text-muted small">ادخل الباركود واضغط Enter أو اضغط F2 للبحث المتقدم</span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="items[${id}][exp_date]" id="expDate_${id}" class="form-control">
                            <small class="text-muted" id="expDisplay_${id}"></small>
                        </div>
                        <div class="col-md-1">
                            <input type="number" name="items[${id}][qty]" id="qty_${id}" class="form-control" 
                                   step="0.001" min="0.001" value="1" onchange="calcRow(${id})" required>
                        </div>
                        <div class="col-md-1">
                            <span class="badge bg-info stock-badge" id="stock_${id}">-</span>
                        </div>
                        <div class="col-md-1">
                            <input type="number" name="items[${id}][unit_cost]" id="cost_${id}" class="form-control" 
                                   step="0.01" min="0" onchange="calcRow(${id})">
                        </div>
                        <div class="col-md-1">
                            <input type="text" id="total_${id}" class="form-control" readonly value="0.00">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(${id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
            setTimeout(() => document.getElementById('barcode_' + id).focus(), 100);
        }
        
        function handleBarcodeKey(e, rowId) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcode = e.target.value.trim();
                if (!barcode) {
                    openProductSearch(rowId);
                    return;
                }
                
                const storeId = document.getElementById('fromStore').value;
                if (!storeId) { alert('اختر المخزن المصدر أولاً'); return; }
                
                // Use quick search API
                ProductSearch.quickSearch(storeId, barcode, 'barcode')
                    .then(products => {
                        if (products.length === 1) {
                            selectProduct(rowId, products[0]);
                        } else if (products.length > 1) {
                            openProductSearch(rowId, barcode);
                        } else {
                            // Try name search as fallback
                            return ProductSearch.quickSearch(storeId, barcode, 'name');
                        }
                        return null;
                    })
                    .then(products => {
                        if (products && products.length === 1) {
                            selectProduct(rowId, products[0]);
                        } else if (products && products.length > 1) {
                            openProductSearch(rowId, barcode);
                        } else if (products) {
                            alert('الصنف غير موجود في المخزن');
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
            const storeId = document.getElementById('fromStore').value;
            if (!storeId) { 
                alert('اختر المخزن المصدر أولاً'); 
                document.getElementById('fromStore').focus();
                return;
            }
            
            activeRowId = rowId;
            
            ProductSearch.open({
                storeId: parseInt(storeId),
                onSelect: function(product) {
                    if (activeRowId) {
                        selectProduct(activeRowId, product);
                        activeRowId = null;
                    }
                }
            });
            
            // If prefill query exists, the popup will handle it via URL params
            if (prefillQuery) {
                // Store the query to pass to popup
                localStorage.setItem('product_search_prefill', prefillQuery);
            }
        }
        
        function selectProduct(rowId, product) {
            document.getElementById('productId_' + rowId).value = product.id;
            
            const disp = document.getElementById('productDisplay_' + rowId);
            disp.innerHTML = `<span class="prod-name">${product.product_name}</span>
                <span class="prod-code ms-2">${product.product_code || product.manual_code || product.barcode || ''}</span>`;
            disp.parentElement.classList.add('product-selected');
            
            // Set cost
            const cost = product.unit_cost || product.cost_price || 0;
            document.getElementById('cost_' + rowId).value = cost > 0 ? cost.toFixed(2) : '';
            
            // Set stock display
            const stock = product.stock_qty || 0;
            const stockBadge = document.getElementById('stock_' + rowId);
            stockBadge.textContent = stock;
            stockBadge.className = 'badge stock-badge ' + (stock > 0 ? 'bg-info' : 'bg-danger');
            
            // Handle expiry date and batches
            if (product.has_expire && product.batches && product.batches.length > 0) {
                const batch = product.batches[0]; // Use first batch
                document.getElementById('expDate_' + rowId).value = batch.exp_date;
                document.getElementById('expDisplay_' + rowId).textContent = 'دفعة: ' + batch.exp_date;
                if (batch.id) {
                    document.getElementById('batchId_' + rowId).value = batch.id;
                }
                if (batch.unit_cost && batch.unit_cost > 0) {
                    document.getElementById('cost_' + rowId).value = batch.unit_cost.toFixed(2);
                }
            } else if (product.exp_date) {
                document.getElementById('expDate_' + rowId).value = product.exp_date;
                document.getElementById('expDisplay_' + rowId).textContent = product.exp_date;
            }
            
            // If product has no cost, prompt
            if (!cost || cost === 0) {
                const manualCost = prompt('أدخل تكلفة الوحدة:');
                if (manualCost) {
                    document.getElementById('cost_' + rowId).value = parseFloat(manualCost).toFixed(2);
                }
            }
            
            calcRow(rowId);
            
            // Auto add new row after selection
            const lastRow = document.querySelector('.transfer-row:last-child');
            if (lastRow && lastRow.id === 'row_' + rowId) {
                setTimeout(() => addRow(), 200);
            }
        }
        
        function calcRow(rowId) {
            const qty = parseFloat(document.getElementById('qty_' + rowId).value) || 0;
            const cost = parseFloat(document.getElementById('cost_' + rowId).value) || 0;
            const total = qty * cost;
            document.getElementById('total_' + rowId).value = total.toFixed(2);
            updateSummary();
        }
        
        function removeRow(rowId) {
            const row = document.getElementById('row_' + rowId);
            if (row) row.remove();
            updateSummary();
        }
        
        function updateSummary() {
            let items = 0, qty = 0, cost = 0;
            document.querySelectorAll('.transfer-row').forEach(row => {
                const q = parseFloat(row.querySelector('[id^="qty_"]')?.value) || 0;
                const c = parseFloat(row.querySelector('[id^="cost_"]')?.value) || 0;
                const pid = row.querySelector('[id^="productId_"]')?.value;
                if (pid && q > 0) {
                    items++;
                    qty += q;
                    cost += q * c;
                }
            });
            document.getElementById('sumItems').textContent = items;
            document.getElementById('sumQty').textContent = qty.toFixed(3);
            document.getElementById('sumCost').textContent = cost.toFixed(2);
        }
        
        // Form validation
        document.getElementById('transferForm').addEventListener('submit', function(e) {
            const fromStore = document.getElementById('fromStore').value;
            const toStore = document.getElementById('toStore').value;
            
            if (!fromStore) { e.preventDefault(); alert('اختر المخزن المصدر'); return; }
            if (!toStore) { e.preventDefault(); alert('اختر المخزن الهدف'); return; }
            if (fromStore === toStore) { e.preventDefault(); alert('المخزنين يجب أن يكونا مختلفين'); return; }
            
            const validItems = document.querySelectorAll('.transfer-row').length;
            if (validItems === 0) { e.preventDefault(); alert('أضف صنف واحد على الأقل'); return; }
        });
    </script>
</body>
</html>
