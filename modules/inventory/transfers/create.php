<?php
require_once __DIR__ . '/../../../core/config.php';
require_once __DIR__ . '/../../../core/auth.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: ../../../index.php');
    exit;
}

$db = getDB(); // ← FIXED: Added getDB()

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

// Get branches for dropdown
$branches = $db->query("SELECT id, branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();

// Get stores with branch info
$stores = $db->query("
    SELECT s.*, b.branch_name 
    FROM stores s 
    LEFT JOIN branches b ON s.branch_id = b.id 
    WHERE s.is_active = 1 
    ORDER BY b.branch_name, s.store_name
")->fetchAll();

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

            // Check batch stock if batch selected
            if ($batchId) {
                $stmt = $db->prepare("SELECT remaining_qty FROM inventory_batches WHERE id = ? AND product_id = ? AND store_id = ?");
                $stmt->execute([$batchId, $productId, $fromStoreId]);
                $batch = $stmt->fetch();

                if (!$batch || $batch['remaining_qty'] < $requestedQty) {
                    throw new Exception('الكمية المطلوبة (' . $requestedQty . ') أكبر من رصيد الدفعة المتاح (' . ($batch['remaining_qty'] ?? 0) . ') للصنف: ' . $product['product_name']);
                }
            } else {
                // Check total stock availability in source store
                $stmt = $db->prepare("SELECT SUM(quantity) as total_qty, unit_cost 
                                       FROM inventory_items 
                                       WHERE store_id = ? AND product_id = ? AND is_active = 1");
                $stmt->execute([$fromStoreId, $productId]);
                $stock = $stmt->fetch();

                $availableQty = floatval($stock['total_qty'] ?? 0);

                if ($availableQty < $requestedQty) {
                    throw new Exception('الكمية المطلوبة (' . $requestedQty . ') أكبر من الرصيد المتاح (' . $availableQty . ') للصنف: ' . $product['product_name']);
                }
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
        body { font-family: 'Cairo', sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; }
        .main-content { 
            margin-right: 260px; 
            padding: 20px;
            min-height: 100vh;
        }
        @media (max-width: 768px) { 
            .main-content { margin-right: 0; padding: 10px; } 
        }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-label { font-weight: 600; color: #495057; font-size: 0.9rem; }
        .btn-primary { background: #0d6efd; border: none; border-radius: 8px; padding: 10px 24px; }
        .btn-secondary { border-radius: 8px; padding: 10px 24px; }
        .item-row { 
            background: #f8f9fa; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 15px; 
            border: 1px solid #e9ecef;
        }
        .item-row .form-control, .item-row .form-select { font-size: 0.9rem; }
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
        .batch-info { background: #e7f3ff; border-radius: 5px; padding: 5px 10px; font-size: 0.85rem; margin-top: 5px; }
        .exp-badge { font-size: 0.75rem; }
        .summary-card { 
            background: white; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 15px;
            border-right: 4px solid #0d6efd;
        }
        .summary-card .label { font-size: 0.85rem; color: #6c757d; }
        .summary-card .value { font-size: 1.2rem; font-weight: bold; color: #0d6efd; }
    </style>
</head>
<body>
    <?php include '../../../includes/sidebar.php'; ?>

    <div class="main-content" style="margin-right: 260px; padding: 20px;">
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
                                    <!-- From Branch & Store -->
                                    <div class="col-md-6">
                                        <label class="form-label">الفرع المصدر</label>
                                        <select id="fromBranch" class="form-select" onchange="filterStores('from')">
                                            <option value="">اختر الفرع</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo $branch['id']; ?>">
                                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="0">مركزي (بدون فرع)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">المخزن المصدر *</label>
                                        <select name="from_store_id" id="fromStore" class="form-select" required onchange="updateProductStock()">
                                            <option value="">اختر المخزن المصدر</option>
                                            <?php foreach ($stores as $store): ?>
                                                <option value="<?php echo $store['id']; ?>" 
                                                        data-branch="<?php echo $store['branch_id'] ?? '0'; ?>"
                                                        data-type="<?php echo $store['store_type']; ?>"
                                                        style="display:none;">
                                                    <?php echo htmlspecialchars($store['store_name']); ?> 
                                                    (<?php echo $store['branch_name'] ?? 'مركزي'; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- To Branch & Store -->
                                    <div class="col-md-6">
                                        <label class="form-label">الفرع الهدف</label>
                                        <select id="toBranch" class="form-select" onchange="filterStores('to')">
                                            <option value="">اختر الفرع</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo $branch['id']; ?>">
                                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="0">مركزي (بدون فرع)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">المخزن الهدف *</label>
                                        <select name="to_store_id" id="toStore" class="form-select" required>
                                            <option value="">اختر المخزن الهدف</option>
                                            <?php foreach ($stores as $store): ?>
                                                <option value="<?php echo $store['id']; ?>" 
                                                        data-branch="<?php echo $store['branch_id'] ?? '0'; ?>"
                                                        data-type="<?php echo $store['store_type']; ?>"
                                                        style="display:none;">
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
                                    <li><i class="bi bi-check2 text-success"></i> يتم اختيار أقرب تاريخ صلاحية تلقائياً</li>
                                    <li><i class="bi bi-check2 text-success"></i> التكلفة والسعر يتم استخراجهم تلقائياً من المخزن</li>
                                    <li><i class="bi bi-check2 text-success"></i> لا يمكن تحويل كمية أكبر من الرصيد المتاح للدفعة</li>
                                    <li><i class="bi bi-check2 text-success"></i> التاريخ يظهر تلقائياً</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php 
    $api_base_path = '../../../api';
    include '../../../includes/product-search-popup.php'; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemCounter = 0;
        let productsData = [];
        let stockData = {};

        // Filter stores by branch
        function filterStores(direction) {
            const branchId = document.getElementById(direction + 'Branch').value;
            const storeSelect = document.getElementById(direction + 'Store');
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

            if (direction === 'from') {
                updateProductStock();
            }
        }

        function addItemRow() {
            itemCounter++;
            const fromStoreId = document.getElementById('fromStore').value;

            const html = `
                <div class="item-row" id="itemRow_${itemCounter}">
                    <div class="row g-3">
                        <div class="col-md-3 product-search-container">
                            <label class="form-label">الصنف *</label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control product-search" 
                                       id="productSearch_${itemCounter}"
                                       placeholder="اضغط F2 للبحث..."
                                       readonly
                                       onclick="openProductSearchModal(${itemCounter})">
                                <button type="button" class="btn btn-outline-primary" onclick="openProductSearchModal(${itemCounter})">
                                    <i class="bi bi-search"></i> F2
                                </button>
                            </div>
                            <input type="hidden" name="items[${itemCounter}][product_id]" id="productId_${itemCounter}">
                            <div class="batch-info mt-1 d-none" id="batchInfo_${itemCounter}">
                                <i class="bi bi-calendar-check"></i> 
                                <span id="expDate_${itemCounter}"></span>
                                <span class="badge bg-success ms-1" id="batchQty_${itemCounter}"></span>
                            </div>
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
                                   onchange="validateItemQty(${itemCounter}); calculateItemTotal(${itemCounter}); updateTotals();">
                            <input type="hidden" id="maxQty_${itemCounter}" value="0">
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
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-success w-100" onclick="changeBatch(${itemCounter})">
                                <i class="bi bi-calendar"></i>
                            </button>
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

            setTimeout(() => {
                openProductSearchModal(itemCounter);
            }, 100);
        }

        function openProductSearchModal(rowIndex) {
            currentRowIndex = rowIndex;
            const fromStoreId = document.getElementById('fromStore').value;

            if (!fromStoreId) {
                alert('يرجى اختيار المخزن المصدر أولاً');
                document.getElementById('fromStore').focus();
                return;
            }

            openProductSearch('product_' + rowIndex, fromStoreId);
            const modal = new bootstrap.Modal(document.getElementById('productSearchModal'));
            modal.show();
        }

        // Listen for product selection
        document.addEventListener('productSelected', function(e) {
            const product = e.detail;
            fillProductData(currentRowIndex, product);
        });

        function fillProductData(index, product) {
            document.getElementById('productId_' + index).value = product.id;
            document.getElementById('productSearch_' + index).value = product.name;

            if (product.batch) {
                // Batch selected
                document.getElementById('batchId_' + index).value = product.batch.id;
                document.getElementById('cost_' + index).value = product.batch.unit_cost;
                document.getElementById('sell_' + index).value = product.batch.sell_price;
                document.getElementById('maxQty_' + index).value = product.batch.remaining_qty;

                // Show batch info
                document.getElementById('batchInfo_' + index).classList.remove('d-none');
                document.getElementById('expDate_' + index).textContent = product.batch.exp_date;
                document.getElementById('batchQty_' + index).textContent = 'رصيد: ' + product.batch.remaining_qty;
            } else {
                // No batch - use product defaults
                document.getElementById('cost_' + index).value = product.cost_price || 0;
                document.getElementById('sell_' + index).value = product.sell_price || 0;
                document.getElementById('batchInfo_' + index).classList.add('d-none');
            }

            document.getElementById('unitId_' + index).value = product.unit_id || '';

            calculateItemTotal(index);
            document.getElementById('qty_' + index).focus();
        }

        function changeBatch(index) {
            const productId = document.getElementById('productId_' + index).value;
            const fromStoreId = document.getElementById('fromStore').value;

            if (!productId) {
                alert('اختر صنف أولاً');
                return;
            }

            // Fetch all batches for this product
            fetch(`../../../api/get-product-batches.php?product_id=${productId}&store_id=${fromStoreId}`)
                .then(r => r.json())
                .then(batches => {
                    if (batches.length === 0) {
                        alert('لا توجد دفعات متاحة');
                        return;
                    }

                    // Show batch selection
                    let html = '<div class="list-group">';
                    batches.forEach(batch => {
                        html += `
                            <button type="button" class="list-group-item list-group-item-action" 
                                    onclick="selectBatchForRow(${index}, ${batch.id}, '${batch.exp_date}', ${batch.remaining_qty}, ${batch.unit_cost}, ${batch.sell_price})">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>تاريخ الصلاحية:</strong> ${batch.exp_date}
                                    </div>
                                    <div>
                                        <span class="badge bg-success">رصيد: ${batch.remaining_qty}</span>
                                        <span class="badge bg-info">تكلفة: ${batch.unit_cost}</span>
                                    </div>
                                </div>
                            </button>
                        `;
                    });
                    html += '</div>';

                    // Simple alert for now - can be replaced with a modal
                    const selected = prompt('اختر تاريخ الصلاحية (أدخل رقم):\n' + 
                        batches.map((b, i) => `${i+1}. ${b.exp_date} (رصيد: ${b.remaining_qty})`).join('\n'));

                    if (selected && batches[selected-1]) {
                        const batch = batches[selected-1];
                        selectBatchForRow(index, batch.id, batch.exp_date, batch.remaining_qty, batch.unit_cost, batch.sell_price);
                    }
                });
        }

        function selectBatchForRow(index, batchId, expDate, qty, cost, sell) {
            document.getElementById('batchId_' + index).value = batchId;
            document.getElementById('cost_' + index).value = cost;
            document.getElementById('sell_' + index).value = sell;
            document.getElementById('maxQty_' + index).value = qty;

            document.getElementById('batchInfo_' + index).classList.remove('d-none');
            document.getElementById('expDate_' + index).textContent = expDate;
            document.getElementById('batchQty_' + index).textContent = 'رصيد: ' + qty;

            calculateItemTotal(index);
        }

        function validateItemQty(index) {
            const qty = parseFloat(document.getElementById('qty_' + index).value) || 0;
            const maxQty = parseFloat(document.getElementById('maxQty_' + index).value) || 0;
            const productName = document.getElementById('productSearch_' + index).value;

            if (maxQty > 0 && qty > maxQty) {
                const fromStoreId = document.getElementById('fromStore').value;
                const productId = document.getElementById('productId_' + index).value;

                // Check if there are other batches
                fetch(`../../../api/get-product-batches.php?product_id=${productId}&store_id=${fromStoreId}`)
                    .then(r => r.json())
                    .then(batches => {
                        const currentBatchId = document.getElementById('batchId_' + index).value;
                        const otherBatches = batches.filter(b => b.id != currentBatchId && b.remaining_qty > 0);

                        if (otherBatches.length > 0) {
                            const nextBatch = otherBatches[0];
                            const remainingNeeded = qty - maxQty;

                            if (confirm(`الرصيد المتاح من التاريخ المختار هو ${maxQty} فقط.\n\n` +
                                       `عايز تكمل ${remainingNeeded} من تاريخ ${nextBatch.exp_date} (رصيد: ${nextBatch.remaining_qty})؟`)) {
                                // Set current row to max qty
                                document.getElementById('qty_' + index).value = maxQty;
                                calculateItemTotal(index);

                                // Add new row with remaining qty and next batch
                                addItemRow();
                                const newIndex = itemCounter;

                                // Copy product data
                                document.getElementById('productId_' + newIndex).value = productId;
                                document.getElementById('productSearch_' + newIndex).value = productName;
                                document.getElementById('batchId_' + newIndex).value = nextBatch.id;
                                document.getElementById('cost_' + newIndex).value = nextBatch.unit_cost;
                                document.getElementById('sell_' + newIndex).value = nextBatch.sell_price;
                                document.getElementById('maxQty_' + newIndex).value = nextBatch.remaining_qty;
                                document.getElementById('qty_' + newIndex).value = remainingNeeded;

                                document.getElementById('batchInfo_' + newIndex).classList.remove('d-none');
                                document.getElementById('expDate_' + newIndex).textContent = nextBatch.exp_date;
                                document.getElementById('batchQty_' + newIndex).textContent = 'رصيد: ' + nextBatch.remaining_qty;

                                calculateItemTotal(newIndex);
                                updateTotals();
                            } else {
                                document.getElementById('qty_' + index).value = maxQty;
                                calculateItemTotal(index);
                            }
                        } else {
                            alert(`الرصيد المتاح هو ${maxQty} فقط.\nلا يوجد رصيد آخر متاح.`);
                            document.getElementById('qty_' + index).value = maxQty;
                            calculateItemTotal(index);
                        }
                    });

                return false;
            }
            return true;
        }

        function removeItemRow(id) {
            document.getElementById('itemRow_' + id).remove();
            updateTotals();
        }

        function calculateItemTotal(index) {
            const qty = parseFloat(document.getElementById('qty_' + index).value) || 0;
            const cost = parseFloat(document.getElementById('cost_' + index).value) || 0;
            const total = qty * cost;
            document.getElementById('total_' + index).value = total.toFixed(2);
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

        function updateProductStock() {
            // Clear all items when store changes
            document.getElementById('itemsContainer').innerHTML = '';
            itemCounter = 0;
            updateTotals();
        }

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
