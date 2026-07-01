<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إنشاء جرد دوري جديد';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $errors = [];

        $store_id = intval($_POST['store_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($store_id <= 0) {
            $errors[] = 'يجب اختيار المخزن';
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        // Generate adjustment code - fixed to avoid floating point issues
        $year_month = date('Ym');
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM stock_adjustments WHERE adjustment_code LIKE 'ADJ-{$year_month}-%'");
        $count = intval($stmt->fetch()['cnt']) + 1;
        $adjustment_code = 'ADJ-' . $year_month . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Insert adjustment
        $stmt = $db->prepare("
            INSERT INTO stock_adjustments 
            (adjustment_code, store_id, adjustment_type, status, notes, counted_by, counted_at, created_at)
            VALUES (?, ?, 'periodic', 'draft', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $adjustment_code,
            $store_id,
            $notes ?: null,
            $_SESSION['user_id'] ?? 1
        ]);

        $adjustment_id = $db->lastInsertId();

        // Get current stock items and insert them
        $stock_items = $db->prepare("
            SELECT ii.*, p.product_name, p.product_code, p.manual_code, p.cost_price, b.exp_date
            FROM inventory_items ii
            JOIN products p ON ii.product_id = p.id
            LEFT JOIN inventory_batches b ON ii.batch_id = b.id
            WHERE ii.store_id = ? AND ii.is_active = 1
        ");
        $stock_items->execute([$store_id]);
        $items = $stock_items->fetchAll();

        $item_stmt = $db->prepare("
            INSERT INTO stock_adjustment_items 
            (adjustment_id, product_id, batch_id, system_qty, actual_qty, variance_qty, unit_cost, variance_cost)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $total_items = 0;
        foreach ($items as $item) {
            $system_qty = floatval($item['quantity']);
            $unit_cost = floatval($item['unit_cost'] ?? $item['cost_price'] ?? 0);
            $item_stmt->execute([
                $adjustment_id,
                $item['product_id'],
                $item['batch_id'],
                $system_qty,
                0, // actual_qty starts at 0
                -$system_qty, // variance = actual - system
                $unit_cost,
                -($system_qty * $unit_cost)
            ]);
            $total_items++;
        }

        // Update total items
        $db->prepare("UPDATE stock_adjustments SET total_items = ? WHERE id = ?")
            ->execute([$total_items, $adjustment_id]);

        header("Location: view.php?id={$adjustment_id}&success=1");
        exit;

    } catch (Exception $e) {
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
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin: 25px 0 20px; }
        .form-label { font-weight: 600; color: #555; }
        .feature-card { 
            background: white; border-radius: 12px; padding: 25px; 
            border: 1px solid #e9ecef; transition: all 0.3s;
            cursor: pointer; text-align: center;
        }
        .feature-card:hover { 
            border-color: var(--primary); 
            box-shadow: 0 4px 15px rgba(102,126,234,0.15);
            transform: translateY(-2px);
        }
        .feature-card i { font-size: 2.5rem; color: var(--primary); margin-bottom: 15px; }
        .feature-card h5 { color: #333; margin-bottom: 8px; }
        .feature-card p { color: #888; font-size: 13px; margin: 0; }
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
        .product-display { background: #f8f9fa; border-radius: 8px; padding: 8px 12px; min-height: 38px; }
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

            <!-- Method Selection Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="feature-card" onclick="showAutoForm()">
                        <i class="bi bi-magic"></i>
                        <h5>جرد تلقائي - جلب كل الأصناف</h5>
                        <p>يقوم النظام بجلب جميع الأصناف الموجودة في المخزن. تستطيع بعد ذلك إدخال الكميات الفعلية لكل صنف.</p>
                        <span class="badge bg-primary">الطريقة الموصى بها</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card" onclick="showManualForm()">
                        <i class="bi bi-hand-index"></i>
                        <h5>جرد يدوي - إضافة أصناف محددة</h5>
                        <p>تختار الأصناف التي تريد جردها يدوياً باستخدام الباركود أو البحث المتقدم. مناسب للجرد الجزئي.</p>
                        <span class="badge bg-secondary">للجرد الجزئي</span>
                    </div>
                </div>
            </div>

            <!-- Auto Form -->
            <form method="POST" action="" id="autoForm" style="display:none;">
                <div class="card">
                    <div class="card-body">
                        <h5 class="section-title"><i class="bi bi-magic"></i> جرد تلقائي - جلب كل الأصناف</h5>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            سيتم إنشاء عملية جرد دوري وجلب جميع الأصناف الموجودة في المخزن. يمكنك بعد ذلك إدخال الكميات الفعلية في شاشة المراجعة.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">الفرع</label>
                                <select id="branch_filter_auto" class="form-select" onchange="filterStores('auto')">
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المخزن <span class="text-danger">*</span></label>
                                <select name="store_id" id="store_select_auto" class="form-select" required>
                                    <option value="">-- اختر المخزن --</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? '0' ?>" style="display:none;">
                                            <?= htmlspecialchars($s['store_name']) ?> <?= $s['branch_name'] ? '(' . htmlspecialchars($s['branch_name']) . ')' : '(مركزي)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label">ملاحظات</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="ملاحظات عن الجرد..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-play-circle"></i> بدء الجرد وجلب الأصناف
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg">إلغاء</a>
                    </div>
                </div>
            </form>

            <!-- Manual Form -->
            <div id="manualForm" style="display:none;">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="section-title"><i class="bi bi-hand-index"></i> جرد يدوي - إضافة أصناف محددة</h5>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            اختر المخزن ثم أضف الأصناف التي تريد جردها باستخدام الباركود أو البحث المتقدم (F2).
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">الفرع</label>
                                <select id="branch_filter_manual" class="form-select" onchange="filterStores('manual')">
                                    <option value="">-- اختر الفرع --</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">المخزن <span class="text-danger">*</span></label>
                                <select id="store_select_manual" class="form-select" onchange="onStoreChange()">
                                    <option value="">-- اختر المخزن --</option>
                                    <?php foreach ($stores as $s): ?>
                                        <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?? '0' ?>" style="display:none;">
                                            <?= htmlspecialchars($s['store_name']) ?> <?= $s['branch_name'] ? '(' . htmlspecialchars($s['branch_name']) . ')' : '(مركزي)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="addManualItem()">
                                    <i class="bi bi-plus-lg"></i> إضافة صنف للجرد
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="save_manual.php" id="manualItemsForm">
                    <input type="hidden" name="store_id" id="manual_store_id">
                    <div id="manualItemsContainer"></div>
                    
                    <div class="summary-bar" style="display:none; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; padding: 15px 20px; border-radius: 12px; position: sticky; bottom: 20px; z-index: 100;" id="manualSummary">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <i class="bi bi-boxes"></i> الأصناف: <strong id="manualItemCount">0</strong>
                            </div>
                            <div class="col-md-6 text-start">
                                <button type="submit" class="btn btn-light fw-bold">
                                    <i class="bi bi-play-circle"></i> بدء الجرد بالأصناف المختارة
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../js/product-search.js"></script>
    <script>
        let manualItemCounter = 0;
        let activeRowId = null;

        function showAutoForm() {
            document.getElementById('autoForm').style.display = '';
            document.getElementById('manualForm').style.display = 'none';
            // Hide selection cards
            document.querySelector('.row.g-4.mb-4').style.display = 'none';
        }

        function showManualForm() {
            document.getElementById('autoForm').style.display = 'none';
            document.getElementById('manualForm').style.display = '';
            document.querySelector('.row.g-4.mb-4').style.display = 'none';
        }

        function filterStores(mode) {
            const suffix = mode; // 'auto' or 'manual'
            const branchId = document.getElementById('branch_filter_' + suffix).value;
            const storeSelect = document.getElementById('store_select_' + suffix);
            
            storeSelect.querySelectorAll('option[data-branch]').forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                opt.style.display = (!branchId || opt.getAttribute('data-branch') === branchId) ? '' : 'none';
            });
            storeSelect.value = '';
            
            if (mode === 'manual') {
                document.getElementById('manualItemsContainer').innerHTML = '';
                document.getElementById('manualSummary').style.display = 'none';
                manualItemCounter = 0;
            }
        }

        function onStoreChange() {
            const storeId = document.getElementById('store_select_manual').value;
            document.getElementById('manual_store_id').value = storeId;
            document.getElementById('manualItemsContainer').innerHTML = '';
            document.getElementById('manualSummary').style.display = 'none';
            manualItemCounter = 0;
            if (storeId) {
                addManualItem();
            }
        }

        function addManualItem() {
            const storeId = document.getElementById('store_select_manual').value;
            if (!storeId) {
                alert('اختر المخزن أولاً');
                document.getElementById('store_select_manual').focus();
                return;
            }

            manualItemCounter++;
            const id = manualItemCounter;
            const html = `
                <div class="card mb-2" id="manualRow_${id}">
                    <div class="card-body py-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small">الباركود / F2</label>
                                <div class="barcode-wrap">
                                    <input type="text" class="form-control" id="mbarcode_${id}" 
                                           placeholder="ادخل الباركود أو اضغط F2..."
                                           onkeydown="handleManualBarcode(event, ${id})" autocomplete="off">
                                    <button type="button" class="btn-f2" onclick="openManualSearch(${id})" title="بحث متقدم (F2)">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="items[${id}][product_id]" id="mproductId_${id}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">الصنف</label>
                                <div class="product-display" id="mproductDisplay_${id}">
                                    <span class="text-muted small">ادخل الباركود أو اضغط F2</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">الكمية في النظام</label>
                                <input type="number" class="form-control" id="msystem_${id}" readonly style="background:#e9ecef;">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">الكمية الفعلية *</label>
                                <input type="number" name="items[${id}][actual_qty]" id="mactual_${id}" 
                                       class="form-control border-primary" step="0.001" min="0" 
                                       onchange="calcVariance(${id})" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small">الفرق</label>
                                <input type="text" id="mvariance_${id}" class="form-control" readonly style="font-weight:bold;">
                            </div>
                            <div class="col-md-1">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeManualRow(${id})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('manualItemsContainer').insertAdjacentHTML('beforeend', html);
            document.getElementById('manualSummary').style.display = '';
            setTimeout(() => document.getElementById('mbarcode_' + id).focus(), 100);
        }

        function handleManualBarcode(e, rowId) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const barcode = e.target.value.trim();
                if (!barcode) {
                    openManualSearch(rowId);
                    return;
                }
                
                const storeId = document.getElementById('store_select_manual').value;
                
                ProductSearch.quickSearch(storeId, barcode, 'barcode')
                    .then(products => {
                        if (products.length === 1) {
                            fillManualProduct(rowId, products[0]);
                        } else if (products.length > 1) {
                            openManualSearch(rowId, barcode);
                        } else {
                            return ProductSearch.quickSearch(storeId, barcode, 'name');
                        }
                        return null;
                    })
                    .then(products => {
                        if (products && products.length === 1) {
                            fillManualProduct(rowId, products[0]);
                        } else if (products && products.length > 1) {
                            openManualSearch(rowId, barcode);
                        } else if (products) {
                            alert('الصنف غير موجود');
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                    });
            }
            if (e.key === 'F2') {
                e.preventDefault();
                openManualSearch(rowId, e.target.value.trim());
            }
        }

        function openManualSearch(rowId, prefillQuery) {
            const storeId = document.getElementById('store_select_manual').value;
            if (!storeId) { alert('اختر المخزن أولاً'); return; }
            
            activeRowId = rowId;
            
            ProductSearch.open({
                storeId: parseInt(storeId),
                onSelect: function(product) {
                    if (activeRowId) {
                        fillManualProduct(activeRowId, product);
                        activeRowId = null;
                    }
                }
            });
            
            if (prefillQuery) {
                localStorage.setItem('product_search_prefill', prefillQuery);
            }
        }

        function fillManualProduct(rowId, product) {
            document.getElementById('mproductId_' + rowId).value = product.id;
            
            const disp = document.getElementById('mproductDisplay_' + rowId);
            disp.innerHTML = `<strong>${product.product_name}</strong>
                <small class="text-muted ms-2">${product.product_code || product.manual_code || product.barcode || ''}</small>`;
            disp.style.background = 'linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%)';
            
            // Set system quantity (current stock)
            const systemQty = product.stock_qty || 0;
            document.getElementById('msystem_' + rowId).value = systemQty.toFixed(3);
            
            // Set actual to system as default
            document.getElementById('mactual_' + rowId).value = systemQty.toFixed(3);
            
            // Calculate initial variance
            calcVariance(rowId);
            
            // Auto add next row
            setTimeout(() => {
                const lastRow = document.querySelector('#manualItemsContainer > .card:last-child');
                if (lastRow && lastRow.id === 'manualRow_' + rowId) {
                    addManualItem();
                }
            }, 200);
            
            updateManualCount();
        }

        function calcVariance(rowId) {
            const system = parseFloat(document.getElementById('msystem_' + rowId).value) || 0;
            const actual = parseFloat(document.getElementById('mactual_' + rowId).value) || 0;
            const variance = actual - system;
            const el = document.getElementById('mvariance_' + rowId);
            el.value = (variance >= 0 ? '+' : '') + variance.toFixed(3);
            el.style.color = variance === 0 ? '#198754' : (variance > 0 ? '#fd7e14' : '#dc3545');
            el.style.background = variance === 0 ? '#d1e7dd' : (variance > 0 ? '#fff3cd' : '#f8d7da');
        }

        function removeManualRow(rowId) {
            const row = document.getElementById('manualRow_' + rowId);
            if (row) row.remove();
            updateManualCount();
        }

        function updateManualCount() {
            const count = document.querySelectorAll('#manualItemsContainer > .card').length;
            document.getElementById('manualItemCount').textContent = count;
            if (count === 0) {
                document.getElementById('manualSummary').style.display = 'none';
            }
        }
    </script>
</body>
</html>
