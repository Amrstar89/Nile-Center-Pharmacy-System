<?php
require_once '../../core/config.php';
require_once '../../core/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

// Get user info
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? 'pharmacist';
$userBranch = $currentUser['branch_code'] ?? null;

// Check permissions
$canCreate = in_array($userRole, ['admin', 'pharmacist', 'purchaser']);

if (!$canCreate) {
    header('Location: index.php?error=' . urlencode('ليس لديك صلاحية إنشاء تحويل'));
    exit;
}

// Get stores for dropdown
$stmt = $db->query("SELECT s.*, b.branch_name 
                     FROM stores s 
                     LEFT JOIN branches b ON s.branch_id = b.id 
                     WHERE s.is_active = 1 
                     ORDER BY s.store_type, s.store_name");
$stores = $stmt->fetchAll();

// Get products with stock info for search
$stmt = $db->query("SELECT p.id, p.product_code, p.product_name, p.product_name_en, 
                            p.sell_price, p.cost_price, p.unit1_id, u.unit_name_ar,
                            i.quantity as stock_qty, i.unit_cost, i.store_id
                     FROM products p
                     LEFT JOIN product_units u ON p.unit1_id = u.id
                     LEFT JOIN inventory_items i ON p.id = i.product_id AND i.is_active = 1
                     WHERE p.is_active = 1
                     ORDER BY p.product_name");
$products = $stmt->fetchAll();

// Get users for dropdown
$stmt = $db->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
$users = $stmt->fetchAll();

// Generate transfer code
$stmt = $db->query("SELECT MAX(id) as max_id FROM inventory_transfers");
$result = $stmt->fetch();
$nextId = ($result['max_id'] ?? 0) + 1;
$transferCode = 'TR-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $fromStoreId = $_POST['from_store_id'] ?? 0;
        $toStoreId = $_POST['to_store_id'] ?? 0;
        $transferType = $_POST['transfer_type'] ?? 'internal';
        $notes = $_POST['notes'] ?? '';
        $requestedBy = $_POST['requested_by'] ?? $currentUser['id'];

        // Validation
        if (empty($fromStoreId) || empty($toStoreId)) {
            throw new Exception('يجب اختيار المخزن المصدر والمخزن الهدف');
        }

        if ($fromStoreId == $toStoreId) {
            throw new Exception('المخزن المصدر والمخزن الهدف يجب أن يكونا مختلفين');
        }

        // Get store info
        $stmt = $db->prepare("SELECT * FROM stores WHERE id = ?");
        $stmt->execute([$fromStoreId]);
        $fromStore = $stmt->fetch();

        $stmt->execute([$toStoreId]);
        $toStore = $stmt->fetch();

        if (!$fromStore || !$toStore) {
            throw new Exception('المخزن غير موجود');
        }

        // Determine transfer type based on stores
        if ($fromStore['store_type'] == 'central_main' && $toStore['store_type'] == 'branch_main') {
            $transferType = 'central_to_branch';
        } elseif ($fromStore['store_type'] == 'branch_main' && $toStore['store_type'] == 'central_main') {
            $transferType = 'branch_to_central';
        } elseif ($fromStore['branch_id'] != $toStore['branch_id']) {
            $transferType = 'branch_to_branch';
        } else {
            $transferType = 'internal';
        }

        // Insert transfer header
        $stmt = $db->prepare("INSERT INTO inventory_transfers 
            (transfer_code, from_store_id, to_store_id, from_branch_id, to_branch_id, 
             transfer_type, status, notes, requested_by, requested_at, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW(), NOW())");

        $stmt->execute([
            $transferCode, $fromStoreId, $toStoreId, 
            $fromStore['branch_id'], $toStore['branch_id'],
            $transferType, $notes, $requestedBy
        ]);

        $transferId = $db->lastInsertId();

        // Process items
        $items = $_POST['items'] ?? [];
        $totalItems = 0;
        $totalQuantity = 0;
        $totalCost = 0;
        $totalSell = 0;

        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['requested_qty'])) {
                continue;
            }

            $productId = $item['product_id'];
            $batchId = $item['batch_id'] ?? null;
            $requestedQty = floatval($item['requested_qty']);
            $unitId = $item['unit_id'] ?? null;
            $unitCost = floatval($item['unit_cost'] ?? 0);
            $sellPrice = floatval($item['sell_price'] ?? 0);

            // Get product info
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new Exception('الصنف غير موجود: ' . $productId);
            }

            // Check stock availability in source store
            $stmt = $db->prepare("SELECT SUM(quantity) as total_qty, unit_cost 
                                   FROM inventory_items 
                                   WHERE store_id = ? AND product_id = ? AND is_active = 1");
            $stmt->execute([$fromStoreId, $productId]);
            $stock = $stmt->fetch();

            $availableQty = floatval($stock['total_qty'] ?? 0);

            if ($availableQty < $requestedQty) {
                throw new Exception('الكمية المطلوبة (' . $requestedQty . ') أكبر من الرصيد المتاح (' . $availableQty . ') للصنف: ' . $product['product_name']);
            }

            // Use stored unit cost if not provided
            if ($unitCost <= 0) {
                $unitCost = floatval($stock['unit_cost'] ?? $product['cost_price'] ?? 0);
            }

            if ($sellPrice <= 0) {
                $sellPrice = floatval($product['sell_price'] ?? 0);
            }

            $itemCost = $requestedQty * $unitCost;
            $itemSell = $requestedQty * $sellPrice;

            // Insert transfer item
            $stmt = $db->prepare("INSERT INTO inventory_transfer_items 
                (transfer_id, product_id, batch_id, requested_qty, unit_id, unit_conversion, 
                 unit_cost, sell_price, total_cost, total_sell, notes, status) 
                VALUES (?, ?, ?, ?, ?, 1.000, ?, ?, ?, ?, ?, 'pending')");

            $stmt->execute([
                $transferId, $productId, $batchId, $requestedQty, $unitId,
                $unitCost, $sellPrice, $itemCost, $itemSell, $item['notes'] ?? ''
            ]);

            $totalItems++;
            $totalQuantity += $requestedQty;
            $totalCost += $itemCost;
            $totalSell += $itemSell;
        }

        if ($totalItems == 0) {
            throw new Exception('يجب إضافة صنف واحد على الأقل');
        }

        // Update transfer totals
        $stmt = $db->prepare("UPDATE inventory_transfers 
            SET total_items = ?, total_quantity = ?, total_cost = ?, total_sell = ? 
            WHERE id = ?");
        $stmt->execute([$totalItems, $totalQuantity, $totalCost, $totalSell, $transferId]);

        $db->commit();

        header('Location: view.php?id=' . $transferId . '&success=' . urlencode('تم إنشاء التحويل بنجاح'));
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء تحويل مخزني - نايل سنتر</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f8f9fa; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; color: #495057; }
        .btn-primary { background: #0d6efd; border: none; border-radius: 8px; padding: 10px 24px; }
        .btn-secondary { border-radius: 8px; padding: 10px 24px; }
        .item-row { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; }
        .stock-info { font-size: 0.85rem; color: #6c757d; }
        .stock-info .badge { font-size: 0.75rem; }
        .search-popup { 
            position: absolute; 
            z-index: 1000; 
            background: white; 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
            max-height: 300px; 
            overflow-y: auto; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            width: 100%;
        }
        .search-popup .list-group-item { cursor: pointer; }
        .search-popup .list-group-item:hover { background-color: #e9ecef; }
        .product-search-container { position: relative; }
        .date-display { font-weight: 600; color: #0d6efd; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">إنشاء تحويل مخزني جديد</h4>
                    <p class="text-muted mb-0">رقم التحويل: <span class="date-display"><?php echo htmlspecialchars($transferCode); ?></span></p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i> رجوع للقائمة
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="transferForm">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-box-seam"></i> بيانات التحويل</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">المخزن المصدر *</label>
                                        <select name="from_store_id" id="fromStore" class="form-select" required onchange="updateProductStock()">
                                            <option value="">اختر المخزن المصدر</option>
                                            <?php foreach ($stores as $store): ?>
                                                <option value="<?php echo $store['id']; ?>" 
                                                        data-branch="<?php echo $store['branch_id']; ?>"
                                                        data-type="<?php echo $store['store_type']; ?>">
                                                    <?php echo htmlspecialchars($store['store_name']); ?> 
                                                    (<?php echo $store['branch_name'] ?? 'مركزي'; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">المخزن الهدف *</label>
                                        <select name="to_store_id" id="toStore" class="form-select" required>
                                            <option value="">اختر المخزن الهدف</option>
                                            <?php foreach ($stores as $store): ?>
                                                <option value="<?php echo $store['id']; ?>" 
                                                        data-branch="<?php echo $store['branch_id']; ?>"
                                                        data-type="<?php echo $store['store_type']; ?>">
                                                    <?php echo htmlspecialchars($store['store_name']); ?> 
                                                    (<?php echo $store['branch_name'] ?? 'مركزي'; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تاريخ الطلب</label>
                                        <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i'); ?>" readonly>
                                        <input type="hidden" name="requested_at" value="<?php echo date('Y-m-d H:i:s'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">طلب التحويل بواسطة</label>
                                        <select name="requested_by" class="form-select">
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" <?php echo ($user['id'] == $currentUser['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">ملاحظات</label>
                                        <textarea name="notes" class="form-control" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-list-check"></i> أصناف التحويل</h5>
                                <button type="button" class="btn btn-sm btn-success" onclick="addItemRow()">
                                    <i class="bi bi-plus-lg"></i> إضافة صنف
                                </button>
                            </div>
                            <div class="card-body" id="itemsContainer">
                                <!-- Items will be added here dynamically -->
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><i class="bi bi-calculator"></i> ملخص التحويل</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>عدد الأصناف:</span>
                                    <strong id="totalItems">0</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>إجمالي الكمية:</span>
                                    <strong id="totalQuantity">0</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>إجمالي التكلفة:</span>
                                    <strong id="totalCost">0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>إجمالي البيع:</span>
                                    <strong id="totalSell">0.00</strong>
                                </div>
                                <hr>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-check-lg"></i> حفظ التحويل
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">
                                    <i class="bi bi-x-lg"></i> إلغاء
                                </a>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0"><i class="bi bi-info-circle"></i> تعليمات</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0 small">
                                    <li><i class="bi bi-check2 text-success"></i> اضغط F2 أو انقر على حقل البحث للبحث عن الأصناف</li>
                                    <li><i class="bi bi-check2 text-success"></i> التكلفة والسعر يتم استخراجهم تلقائياً من المخزن</li>
                                    <li><i class="bi bi-check2 text-success"></i> لا يمكن تحويل كمية أكبر من الرصيد المتاح</li>
                                    <li><i class="bi bi-check2 text-success"></i> التاريخ يظهر تلقائياً</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemCounter = 0;
        let productsData = <?php echo json_encode($products); ?>;
        let stockData = {};

        // Build stock data by product and store
        productsData.forEach(p => {
            if (!stockData[p.id]) {
                stockData[p.id] = {};
            }
            if (p.store_id) {
                stockData[p.id][p.store_id] = {
                    qty: parseFloat(p.stock_qty || 0),
                    cost: parseFloat(p.unit_cost || p.cost_price || 0),
                    sell: parseFloat(p.sell_price || 0)
                };
            }
        });

        function addItemRow() {
            itemCounter++;
            const fromStoreId = document.getElementById('fromStore').value;

            const html = `
                <div class="item-row" id="itemRow_${itemCounter}">
                    <div class="row g-3">
                        <div class="col-md-4 product-search-container">
                            <label class="form-label">الصنف *</label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control product-search" 
                                       id="productSearch_${itemCounter}"
                                       placeholder="اضغط F2 للبحث أو ابدأ الكتابة..."
                                       autocomplete="off"
                                       onkeydown="handleProductSearch(event, ${itemCounter})"
                                       onfocus="showProductSearch(${itemCounter})"
                                       oninput="filterProducts(${itemCounter})">
                                <button type="button" class="btn btn-outline-secondary" onclick="showProductSearch(${itemCounter})">
                                    <i class="bi bi-search"></i> F2
                                </button>
                            </div>
                            <div class="search-popup list-group" id="productPopup_${itemCounter}"></div>
                            <input type="hidden" name="items[${itemCounter}][product_id]" id="productId_${itemCounter}">
                            <div class="stock-info mt-1" id="stockInfo_${itemCounter}"></div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الكمية *</label>
                            <input type="number" 
                                   name="items[${itemCounter}][requested_qty]" 
                                   class="form-control qty-input" 
                                   id="qty_${itemCounter}"
                                   step="0.001" 
                                   min="0.001" 
                                   required
                                   onchange="calculateItemTotal(${itemCounter}); updateTotals();">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">التكلفة</label>
                            <input type="number" 
                                   name="items[${itemCounter}][unit_cost]" 
                                   class="form-control cost-input" 
                                   id="cost_${itemCounter}"
                                   step="0.01" 
                                   readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">سعر البيع</label>
                            <input type="number" 
                                   name="items[${itemCounter}][sell_price]" 
                                   class="form-control sell-input" 
                                   id="sell_${itemCounter}"
                                   step="0.01" 
                                   readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">الإجمالي</label>
                            <div class="input-group">
                                <input type="text" class="form-control total-display" id="total_${itemCounter}" readonly>
                                <button type="button" class="btn btn-outline-danger" onclick="removeItemRow(${itemCounter})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <input type="hidden" name="items[${itemCounter}][batch_id]" id="batchId_${itemCounter}">
                            <input type="hidden" name="items[${itemCounter}][unit_id]" id="unitId_${itemCounter}">
                            <input type="text" name="items[${itemCounter}][notes]" class="form-control form-control-sm" placeholder="ملاحظات...">
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);

            // Focus on the new search field
            setTimeout(() => {
                document.getElementById(`productSearch_${itemCounter}`).focus();
            }, 100);
        }

        function removeItemRow(id) {
            document.getElementById(`itemRow_${id}`).remove();
            updateTotals();
        }

        function handleProductSearch(event, counter) {
            if (event.key === 'F2') {
                event.preventDefault();
                showProductSearch(counter);
            }
        }

        function showProductSearch(counter) {
            const popup = document.getElementById(`productPopup_${counter}`);
            const fromStoreId = document.getElementById('fromStore').value;

            if (!fromStoreId) {
                alert('يرجى اختيار المخزن المصدر أولاً');
                document.getElementById('fromStore').focus();
                return;
            }

            let html = '';
            productsData.forEach(p => {
                const stock = stockData[p.id] && stockData[p.id][fromStoreId] ? stockData[p.id][fromStoreId] : { qty: 0, cost: 0, sell: 0 };
                const stockBadge = stock.qty > 0 
                    ? `<span class="badge bg-success">رصيد: ${stock.qty}</span>` 
                    : `<span class="badge bg-danger">نفذ الرصيد</span>`;

                html += `
                    <div class="list-group-item" onclick="selectProduct(${counter}, ${p.id}, '${escapeHtml(p.product_name)}', ${stock.qty}, ${stock.cost}, ${stock.sell}, ${p.unit1_id || 'null'})">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(p.product_name)}</strong>
                                <small class="text-muted d-block">${escapeHtml(p.product_name_en || '')} | كود: ${p.product_code}</small>
                            </div>
                            <div>
                                ${stockBadge}
                                <span class="badge bg-info">تكلفة: ${stock.cost.toFixed(2)}</span>
                                <span class="badge bg-primary">بيع: ${stock.sell.toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            popup.innerHTML = html;
            popup.style.display = 'block';
        }

        function filterProducts(counter) {
            const searchTerm = document.getElementById(`productSearch_${counter}`).value.toLowerCase();
            const popup = document.getElementById(`productPopup_${counter}`);
            const fromStoreId = document.getElementById('fromStore').value;

            if (searchTerm.length < 2) {
                popup.style.display = 'none';
                return;
            }

            let html = '';
            productsData.forEach(p => {
                if (p.product_name.toLowerCase().includes(searchTerm) || 
                    (p.product_name_en && p.product_name_en.toLowerCase().includes(searchTerm)) ||
                    p.product_code.toLowerCase().includes(searchTerm)) {

                    const stock = stockData[p.id] && stockData[p.id][fromStoreId] ? stockData[p.id][fromStoreId] : { qty: 0, cost: 0, sell: 0 };
                    const stockBadge = stock.qty > 0 
                        ? `<span class="badge bg-success">رصيد: ${stock.qty}</span>` 
                        : `<span class="badge bg-danger">نفذ الرصيد</span>`;

                    html += `
                        <div class="list-group-item" onclick="selectProduct(${counter}, ${p.id}, '${escapeHtml(p.product_name)}', ${stock.qty}, ${stock.cost}, ${stock.sell}, ${p.unit1_id || 'null'})">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${escapeHtml(p.product_name)}</strong>
                                    <small class="text-muted d-block">${escapeHtml(p.product_name_en || '')} | كود: ${p.product_code}</small>
                                </div>
                                <div>
                                    ${stockBadge}
                                    <span class="badge bg-info">تكلفة: ${stock.cost.toFixed(2)}</span>
                                    <span class="badge bg-primary">بيع: ${stock.sell.toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });

            if (html) {
                popup.innerHTML = html;
                popup.style.display = 'block';
            } else {
                popup.innerHTML = '<div class="list-group-item text-muted">لا توجد نتائج</div>';
                popup.style.display = 'block';
            }
        }

        function selectProduct(counter, productId, productName, stockQty, cost, sell, unitId) {
            document.getElementById(`productSearch_${counter}`).value = productName;
            document.getElementById(`productId_${counter}`).value = productId;
            document.getElementById(`cost_${counter}`).value = cost.toFixed(2);
            document.getElementById(`sell_${counter}`).value = sell.toFixed(2);
            document.getElementById(`unitId_${counter}`).value = unitId || '';

            const stockInfo = document.getElementById(`stockInfo_${counter}`);
            if (stockQty > 0) {
                stockInfo.innerHTML = `<span class="badge bg-success">الرصيد المتاح: ${stockQty}</span> <span class="badge bg-info">التكلفة: ${cost.toFixed(2)}</span> <span class="badge bg-primary">البيع: ${sell.toFixed(2)}</span>`;
                document.getElementById(`qty_${counter}`).max = stockQty;
            } else {
                stockInfo.innerHTML = `<span class="badge bg-danger">نفذ الرصيد في المخزن المصدر</span>`;
                document.getElementById(`qty_${counter}`).disabled = true;
            }

            document.getElementById(`productPopup_${counter}`).style.display = 'none';

            // Focus on quantity field
            document.getElementById(`qty_${counter}`).focus();
        }

        function calculateItemTotal(counter) {
            const qty = parseFloat(document.getElementById(`qty_${counter}`).value) || 0;
            const cost = parseFloat(document.getElementById(`cost_${counter}`).value) || 0;
            const total = qty * cost;
            document.getElementById(`total_${counter}`).value = total.toFixed(2);
        }

        function updateTotals() {
            let totalItems = 0;
            let totalQty = 0;
            let totalCost = 0;
            let totalSell = 0;

            document.querySelectorAll('.item-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
                const sell = parseFloat(row.querySelector('.sell-input').value) || 0;

                if (qty > 0) {
                    totalItems++;
                    totalQty += qty;
                    totalCost += qty * cost;
                    totalSell += qty * sell;
                }
            });

            document.getElementById('totalItems').textContent = totalItems;
            document.getElementById('totalQuantity').textContent = totalQty.toFixed(3);
            document.getElementById('totalCost').textContent = totalCost.toFixed(2);
            document.getElementById('totalSell').textContent = totalSell.toFixed(2);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/'/g, "\'");
        }

        function updateProductStock() {
            // Clear all items when store changes
            document.getElementById('itemsContainer').innerHTML = '';
            itemCounter = 0;
            updateTotals();
        }

        // Close popup when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.product-search-container')) {
                document.querySelectorAll('.search-popup').forEach(p => p.style.display = 'none');
            }
        });

        // Form validation
        document.getElementById('transferForm').addEventListener('submit', function(e) {
            const fromStore = document.getElementById('fromStore').value;
            const toStore = document.getElementById('toStore').value;

            if (!fromStore || !toStore) {
                e.preventDefault();
                alert('يرجى اختيار المخزن المصدر والمخزن الهدف');
                return false;
            }

            if (fromStore === toStore) {
                e.preventDefault();
                alert('المخزن المصدر والمخزن الهدف يجب أن يكونا مختلفين');
                return false;
            }

            const items = document.querySelectorAll('.item-row');
            if (items.length === 0) {
                e.preventDefault();
                alert('يجب إضافة صنف واحد على الأقل');
                return false;
            }

            let hasValidItem = false;
            items.forEach(row => {
                const productId = row.querySelector('[id^="productId_"]').value;
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                if (productId && qty > 0) {
                    hasValidItem = true;
                }
            });

            if (!hasValidItem) {
                e.preventDefault();
                alert('يجب إضافة صنف واحد على الأقل بكمية صحيحة');
                return false;
            }
        });

        // Add first item row on page load
        window.addEventListener('load', function() {
            addItemRow();
        });
    </script>
</body>
</html>
