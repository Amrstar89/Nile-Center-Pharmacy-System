<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

// ============================================
// Handle mark as purchased
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_purchased') {
    header('Content-Type: application/json');
    $item_id = $_POST['item_id'] ?? 0;
    $supplier_id = $_POST['supplier_id'] ?? 0;
    $supplier_price = $_POST['supplier_price'] ?? 0;
    $sell_price = $_POST['sell_price'] ?? 0;
    $profit_margin = $_POST['profit_margin'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    $notes = $_POST['notes'] ?? '';

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM order_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'الصنف غير موجود']);
            exit;
        }

        $total_cost = $supplier_price * $quantity;
        $total_sell = $sell_price * $quantity;
        $profit = $total_sell - $total_cost;

        $stmt = $db->prepare("
            INSERT INTO purchased_items 
            (order_item_id, supplier_id, supplier_price, sell_price, profit_margin, quantity, total_cost, total_sell, profit, notes, purchased_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $item_id, $supplier_id, $supplier_price, $sell_price, $profit_margin,
            $quantity, $total_cost, $total_sell, $profit, $notes, $_SESSION['user_id']
        ]);

        $db->prepare("UPDATE order_items SET needs_purchase = 0, unit_price = ?, final_price = ? * quantity, purchased_at = NOW() WHERE id = ?")
           ->execute([$sell_price, $sell_price, $item_id]);

        $stmt = $db->prepare("SELECT order_id FROM order_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $order_id = $stmt->fetch()['order_id'];

        $stmt = $db->prepare("SELECT SUM(final_price) as total FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $total = $stmt->fetch()['total'] ?? 0;

        $db->prepare("UPDATE orders SET final_total = ? WHERE id = ?")->execute([$total, $order_id]);

        $db->commit();

        echo json_encode(['success' => true, 'message' => 'تم تسجيل الشراء بنجاح']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// Handle add new supplier
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_new_supplier') {
    header('Content-Type: application/json');
    $supplier_name = $_POST['supplier_name'] ?? '';
    $supplier_phone = $_POST['supplier_phone'] ?? '';
    $supplier_code = 'SUP' . time();

    try {
        $stmt = $db->prepare("INSERT INTO suppliers (supplier_code, supplier_name, phone, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$supplier_code, $supplier_name, $supplier_phone]);
        $new_id = $db->lastInsertId();

        echo json_encode(['success' => true, 'id' => $new_id, 'name' => $supplier_name, 'code' => $supplier_code]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// Handle add/update supplier price - UNIQUE per product_code+supplier
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier_price') {
    header('Content-Type: application/json');
    $item_id = $_POST['item_id'] ?? 0;
    $supplier_id = $_POST['supplier_id'] ?? 0;
    $supplier_price = $_POST['supplier_price'] ?? 0;
    $sell_price = $_POST['sell_price'] ?? 0;
    $profit_margin = $_POST['profit_margin'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    $delivery_time_id = $_POST['delivery_time_id'] ?? null;

    try {
        $stmt = $db->prepare("SELECT product_id, product_code, product_name FROM order_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'الصنف غير موجود']);
            exit;
        }

        // Get product_code from products table if product_id exists
        $product_code = $item['product_code'];
        $product_id = $item['product_id'];

        if ($product_id) {
            $stmt = $db->prepare("SELECT product_code FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if ($product) {
                $product_code = $product['product_code'];
            }
        }

        if (!$product_code) {
            echo json_encode(['success' => false, 'message' => 'لا يمكن إضافة سعر لصنف بدون كود']);
            exit;
        }

        // Delete old price for same product_code + supplier (UNIQUE constraint)
        $stmt = $db->prepare("DELETE FROM supplier_prices WHERE product_code = ? AND supplier_id = ?");
        $stmt->execute([$product_code, $supplier_id]);

        // Insert new price (only one per product_code+supplier)
        $stmt = $db->prepare("
            INSERT INTO supplier_prices 
            (product_id, product_code, supplier_id, supplier_price, sell_price, profit_margin, notes, delivery_time_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $product_id, $product_code, $supplier_id, 
            $supplier_price, $sell_price, $profit_margin, $notes, $delivery_time_id
        ]);

        echo json_encode(['success' => true, 'message' => 'تم حفظ السعر بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// Handle set price for order (بدون شراء - للصيدلي)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_order_price') {
    header('Content-Type: application/json');
    $item_id = $_POST['item_id'] ?? 0;
    $sell_price = $_POST['sell_price'] ?? 0;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM order_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'الصنف غير موجود']);
            exit;
        }

        $final_price = $sell_price * $item['quantity'];

        // Update price only (no updated_at column)
        $db->prepare("UPDATE order_items SET 
            unit_price = ?, 
            final_price = ?,
            discount_type = NULL,
            discount_value = 0
            WHERE id = ?")
           ->execute([$sell_price, $final_price, $item_id]);

        // Recalculate order total
        $order_id = $item['order_id'];
        $stmt = $db->prepare("SELECT SUM(final_price) as total FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $total = $stmt->fetch()['total'] ?? 0;

        $db->prepare("UPDATE orders SET final_total = ? WHERE id = ?")->execute([$total, $order_id]);

        $db->commit();

        echo json_encode(['success' => true, 'message' => 'تم تحديث السعر في الطلب بنجاح']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// Get items need purchase
// ============================================
$stmt = $db->query("
    SELECT oi.*, o.order_number, o.customer_name, o.customer_phone, o.branch_code, o.priority,
           os.status_name, os.status_color
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN order_statuses os ON o.status_id = os.id
    WHERE oi.needs_purchase = 1
    ORDER BY o.priority DESC, o.created_at ASC
");
$need_purchase_items = $stmt->fetchAll();

// ============================================
// Get supplier prices - UNIQUE per product_code+supplier
// ============================================
$supplierPrices = [];
$stmt = $db->query("
    SELECT sp.*, s.supplier_name, s.supplier_code, s.phone, dt.time_name as delivery_time
    FROM supplier_prices sp
    JOIN suppliers s ON sp.supplier_id = s.id
    LEFT JOIN delivery_times dt ON sp.delivery_time_id = dt.id
    WHERE sp.is_active = 1
    ORDER BY sp.created_at DESC
");
$allPrices = $stmt->fetchAll();

// Group by product_code for display
foreach ($allPrices as $price) {
    $supplierPrices[$price['product_code']][] = $price;
}

// ============================================
// Get purchased items history
// ============================================
$stmt = $db->query("
    SELECT pi.*, oi.product_name, oi.product_code, s.supplier_name, s.supplier_code, s.phone,
           o.order_number, o.customer_name
    FROM purchased_items pi
    JOIN order_items oi ON pi.order_item_id = oi.id
    JOIN suppliers s ON pi.supplier_id = s.id
    JOIN orders o ON oi.order_id = o.id
    ORDER BY pi.purchased_at DESC
    LIMIT 50
");
$purchased_history = $stmt->fetchAll();

// ============================================
// Get suppliers
// ============================================
$stmt = $db->query("SELECT s.*, dt.time_name as delivery_time 
                    FROM suppliers s 
                    LEFT JOIN delivery_times dt ON s.delivery_time_id = dt.id
                    WHERE s.is_active = 1 ORDER BY s.supplier_name");
$suppliers = $stmt->fetchAll();

// ============================================
// Get delivery times
// ============================================
$delivery_times = $db->query("SELECT * FROM delivery_times WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$page_title = 'شاشة المشتريات';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root { --primary: #667eea; --secondary: #764ba2; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h4 { color: white; margin: 0; font-weight: 700; }
        .nav-menu { padding: 15px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 3px solid var(--primary); }
        .nav-link i { width: 25px; margin-left: 10px; font-size: 18px; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .priority-badge { padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; }
        .priority-normal { background: #e9ecef; color: #495057; }
        .priority-urgent { background: #fff3cd; color: #856404; }
        .priority-critical { background: #f8d7da; color: #721c24; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; }
        .item-card { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; border: 2px solid transparent; transition: all 0.3s; }
        .item-card:hover { border-color: var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .supplier-price { background: #e3f2fd; border-radius: 8px; padding: 10px; margin: 5px 0; border: 2px solid transparent; }
        .supplier-price.selected { border-color: #4caf50; background: #e8f5e9; }
        .purchased-item { background: #e8f5e9; border-radius: 8px; padding: 10px; margin: 5px 0; }
        .btn-action { padding: 8px 15px; border-radius: 8px; font-size: 13px; }
        .section-title { border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px; }
        .delivery-time { background: #fff3cd; padding: 4px 8px; border-radius: 10px; font-size: 12px; color: #856404; }
        .profit-badge { background: #e8f5e9; color: #2e7d32; padding: 4px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
        @media (max-width: 768px) { .sidebar { width: 0; } .main-content { margin-right: 0; } }
        @media print { .sidebar, .topbar, .btn-action, .no-print { display: none !important; } .main-content { margin-right: 0 !important; } }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">إدارة المشتريات والموردين</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?><?= showAlert($_SESSION['success'], 'success') ?><?php unset($_SESSION['success']); ?><?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?><?= showAlert($_SESSION['error'], 'danger') ?><?php unset($_SESSION['error']); ?><?php endif; ?>

        <!-- Need Purchase Section -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-title"><i class="bi bi-cart-plus me-2"></i>أصناف تحتاج شراء (<?= count($need_purchase_items) ?>)</h5>
                <button class="btn btn-primary no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i> طباعة</button>
            </div>

            <?php if (count($need_purchase_items) > 0): ?>
                <?php foreach ($need_purchase_items as $item): 
                    // Get prices by product_code (or product_id if exists)
                    $lookup_code = $item['product_code'];
                    if (!$lookup_code && $item['product_id']) {
                        $stmt = $db->prepare("SELECT product_code FROM products WHERE id = ?");
                        $stmt->execute([$item['product_id']]);
                        $prod = $stmt->fetch();
                        if ($prod) $lookup_code = $prod['product_code'];
                    }
                    $prices = $lookup_code ? ($supplierPrices[$lookup_code] ?? []) : [];
                ?>
                <div class="item-card">
                    <div class="row">
                        <div class="col-md-3">
                            <h6 class="mb-1"><?= htmlspecialchars($item['product_name']) ?></h6>
                            <?php if ($item['product_code']): ?><small class="text-muted">كود: <?= htmlspecialchars($item['product_code']) ?></small><?php endif; ?>
                            <div class="mt-2">
                                <span class="priority-badge priority-<?= $item['priority'] ?>"><?= $item['priority'] === 'normal' ? 'عادي' : ($item['priority'] === 'urgent' ? 'عاجل' : 'حرج') ?></span>
                                <span class="status-badge ms-2" style="background: <?= $item['status_color'] ?>"><?= $item['status_name'] ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>الكمية:</strong> <?= $item['quantity'] ?></div>
                            <div><strong>السعر الحالي:</strong> <?= number_format($item['unit_price'], 2) ?> ج</div>
                            <?php if ($item['discount_value'] > 0): ?><small class="text-success">خصم: <?= $item['discount_value'] ?><?= $item['discount_type'] === 'percentage' ? '%' : ' ج' ?></small><?php endif; ?>
                            <div><strong>الإجمالي:</strong> <?= number_format($item['final_price'], 2) ?> ج</div>
                        </div>
                        <div class="col-md-3">
                            <div><strong>الطلب:</strong> <?= $item['order_number'] ?></div>
                            <div><strong>العميل:</strong> <?= $item['customer_name'] ?></div>
                            <?php if ($item['customer_phone']): ?><div><small class="text-muted"><i class="bi bi-telephone"></i> <?= $item['customer_phone'] ?></small></div><?php endif; ?>
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-info btn-action" onclick="showPriceModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['product_name']) ?>', <?= $item['quantity'] ?>, '<?= htmlspecialchars($lookup_code ?? '') ?>')">
                                <i class="bi bi-tag"></i> إضافة/تعديل سعر
                            </button>
                        </div>
                    </div>

                    <!-- Supplier Prices (by product_code) -->
                    <?php if (count($prices) > 0): ?>
                    <div class="mt-3">
                        <h6 class="text-muted"><i class="bi bi-shop"></i> أسعار الموردين المتاحة:</h6>
                        <?php foreach ($prices as $price): ?>
                        <div class="supplier-price" id="price-<?= $price['supplier_id'] ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong><?= htmlspecialchars($price['supplier_name']) ?></strong>
                                    <small class="text-muted d-block"><?= htmlspecialchars($price['supplier_code']) ?></small>
                                    <?php if ($price['phone']): ?><small class="text-muted"><i class="bi bi-telephone"></i> <?= $price['phone'] ?></small><?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <div class="price-label">سعر المورد</div>
                                    <div class="price-value text-primary"><?= number_format($price['supplier_price'], 2) ?> ج</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="price-label">سعر البيع</div>
                                    <div class="price-value text-success"><?= number_format($price['sell_price'], 2) ?> ج</div>
                                    <span class="profit-badge"><i class="bi bi-graph-up"></i> ربح <?= $price['profit_margin'] ?>%</span>
                                    <?php if ($price['delivery_time']): ?><span class="delivery-time ms-2"><i class="bi bi-clock"></i> <?= $price['delivery_time'] ?></span><?php endif; ?>
                                </div>
                                <div class="col-md-2 text-end">
                                    <!-- 3 Buttons: شراء, تعديل, تسجيل السعر -->
                                    <button class="btn btn-success btn-sm w-100 mb-1" onclick="purchaseFromSupplier(<?= $item['id'] ?>, <?= $price['supplier_id'] ?>, <?= $price['supplier_price'] ?>, <?= $price['sell_price'] ?>, <?= $price['profit_margin'] ?>, <?= $item['quantity'] ?>)">
                                        <i class="bi bi-check-lg"></i> شراء
                                    </button>
                                    <button class="btn btn-warning btn-sm w-100 mb-1" onclick="editPrice(<?= $item['id'] ?>, <?= $price['id'] ?>, '<?= htmlspecialchars($price['supplier_name']) ?>', <?= $price['supplier_id'] ?>, <?= $price['supplier_price'] ?>, <?= $price['sell_price'] ?>, <?= $price['profit_margin'] ?>, <?= $price['delivery_time_id'] ?? 'null' ?>, '<?= htmlspecialchars($price['notes'] ?? '') ?>')">
                                        <i class="bi bi-pencil"></i> تعديل
                                    </button>
                                    <button class="btn btn-info btn-sm w-100" onclick="setOrderPrice(<?= $item['id'] ?>, <?= $price['supplier_id'] ?>, <?= $price['supplier_price'] ?>, <?= $price['sell_price'] ?>, <?= $price['profit_margin'] ?>)">
                                        <i class="bi bi-cart-check"></i> تسجيل السعر
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning mt-2">
                        <i class="bi bi-exclamation-triangle"></i> لا يوجد أسعار موردين مسجلة. اضغط "إضافة/تعديل سعر" لإضافة سعر.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5"><i class="bi bi-check-circle text-success" style="font-size: 48px;"></i><h5 class="mt-3">لا يوجد أصناف تحتاج شراء</h5></div>
            <?php endif; ?>
        </div>

        <!-- Purchased History -->
        <?php if (count($purchased_history) > 0): ?>
        <div class="content-card">
            <h5 class="section-title"><i class="bi bi-clock-history me-2"></i>تاريخ المشتريات (آخر 50)</h5>
            <?php foreach ($purchased_history as $ph): ?>
            <div class="purchased-item">
                <div class="row">
                    <div class="col-md-3">
                        <strong><?= htmlspecialchars($ph['product_name']) ?></strong>
                        <small class="text-muted d-block">كود: <?= htmlspecialchars($ph['product_code'] ?? 'N/A') ?></small>
                    </div>
                    <div class="col-md-3">
                        <div><strong>المورد:</strong> <?= htmlspecialchars($ph['supplier_name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($ph['supplier_code']) ?> | <?= $ph['phone'] ?: 'لا يوجد تليفون' ?></small>
                    </div>
                    <div class="col-md-3">
                        <div><strong>سعر المورد:</strong> <?= number_format($ph['supplier_price'], 2) ?> ج × <?= $ph['quantity'] ?> = <?= number_format($ph['total_cost'], 2) ?> ج</div>
                        <div><strong>سعر البيع:</strong> <?= number_format($ph['sell_price'], 2) ?> ج × <?= $ph['quantity'] ?> = <?= number_format($ph['total_sell'], 2) ?> ج</div>
                        <span class="profit-badge"><i class="bi bi-cash-coin"></i> ربح <?= number_format($ph['profit'], 2) ?> ج</span>
                    </div>
                    <div class="col-md-3">
                        <div><strong>الطلب:</strong> <?= $ph['order_number'] ?></div>
                        <div><strong>العميل:</strong> <?= $ph['customer_name'] ?></div>
                        <small class="text-muted"><i class="bi bi-calendar"></i> <?= date('Y-m-d h:i A', strtotime($ph['purchased_at'])) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Price Modal -->
    <div class="modal fade" id="priceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">إضافة/تعديل سعر مورد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalItemId">
                    <input type="hidden" id="modalQuantity">
                    <input type="hidden" id="modalPriceId" value="0">
                    <input type="hidden" id="modalProductCode">

                    <div class="mb-3">
                        <label class="form-label">الصنف</label>
                        <input type="text" class="form-control" id="modalProductName" readonly>
                    </div>

                    <!-- New Supplier Section -->
                    <div class="alert alert-info">
                        <h6><i class="bi bi-person-plus"></i> مورد جديد؟</h6>
                        <div class="row">
                            <div class="col-md-5">
                                <input type="text" class="form-control" id="newSupplierName" placeholder="اسم المورد">
                            </div>
                            <div class="col-md-5">
                                <input type="text" class="form-control" id="newSupplierPhone" placeholder="التليفون">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-success w-100" onclick="addNewSupplier()"><i class="bi bi-plus-lg"></i> إضافة</button>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-shop"></i> سعر المورد</h6>
                            <div class="mb-3">
                                <label>المورد</label>
                                <select class="form-select" id="modalSupplier">
                                    <option value="">-- اختر مورد --</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>">
                                        <?= htmlspecialchars($sup['supplier_name']) ?> (<?= htmlspecialchars($sup['supplier_code']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>سعر المورد</label>
                                <input type="number" class="form-control" id="modalSupplierPrice" step="0.01" oninput="calculateSellPrice()">
                            </div>
                            <div class="mb-3">
                                <label>وقت التوفير</label>
                                <select class="form-select" id="modalDeliveryTime">
                                    <?php foreach ($delivery_times as $dt): ?>
                                    <option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['time_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success"><i class="bi bi-cash-coin"></i> سعر البيع</h6>
                            <div class="mb-3">
                                <label>هامش الربح (%)</label>
                                <input type="number" class="form-control" id="modalProfitMargin" value="20" step="0.01" oninput="calculateSellPrice()">
                            </div>
                            <div class="mb-3">
                                <label>سعر البيع</label>
                                <input type="number" class="form-control" id="modalSellPrice" step="0.01" oninput="calculateMargin()">
                            </div>
                            <div class="alert alert-info">
                                <small>سعر البيع = سعر المورد + (سعر المورد × هامش الربح / 100)</small>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>ملاحظات</label>
                        <textarea class="form-control" id="modalNotes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="savePrice()">حفظ السعر</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        const priceModal = new bootstrap.Modal(document.getElementById('priceModal'));

        function showPriceModal(itemId, productName, quantity, productCode) {
            document.getElementById('modalTitle').textContent = 'إضافة سعر مورد';
            document.getElementById('modalItemId').value = itemId;
            document.getElementById('modalQuantity').value = quantity;
            document.getElementById('modalPriceId').value = 0;
            document.getElementById('modalProductName').value = productName;
            document.getElementById('modalProductCode').value = productCode;
            document.getElementById('modalSupplier').value = '';
            document.getElementById('modalSupplierPrice').value = '';
            document.getElementById('modalSellPrice').value = '';
            document.getElementById('modalProfitMargin').value = 20;
            document.getElementById('modalDeliveryTime').value = '';
            document.getElementById('modalNotes').value = '';
            document.getElementById('newSupplierName').value = '';
            document.getElementById('newSupplierPhone').value = '';
            priceModal.show();
        }

        function editPrice(itemId, priceId, supplierName, supplierId, supplierPrice, sellPrice, profitMargin, deliveryTimeId, notes) {
            document.getElementById('modalTitle').textContent = 'تعديل سعر: ' + supplierName;
            document.getElementById('modalItemId').value = itemId;
            document.getElementById('modalPriceId').value = priceId;
            document.getElementById('modalSupplier').value = supplierId;
            document.getElementById('modalSupplierPrice').value = supplierPrice;
            document.getElementById('modalSellPrice').value = sellPrice;
            document.getElementById('modalProfitMargin').value = profitMargin;
            document.getElementById('modalDeliveryTime').value = deliveryTimeId || '';
            document.getElementById('modalNotes').value = notes;
            priceModal.show();
        }

        function calculateSellPrice() {
            const supplierPrice = parseFloat(document.getElementById('modalSupplierPrice').value) || 0;
            const margin = parseFloat(document.getElementById('modalProfitMargin').value) || 0;
            const sellPrice = supplierPrice + (supplierPrice * margin / 100);
            document.getElementById('modalSellPrice').value = sellPrice.toFixed(2);
        }

        function calculateMargin() {
            const supplierPrice = parseFloat(document.getElementById('modalSupplierPrice').value) || 0;
            const sellPrice = parseFloat(document.getElementById('modalSellPrice').value) || 0;
            if (supplierPrice > 0) {
                const margin = ((sellPrice - supplierPrice) / supplierPrice) * 100;
                document.getElementById('modalProfitMargin').value = margin.toFixed(2);
            }
        }

        function addNewSupplier() {
            const name = document.getElementById('newSupplierName').value.trim();
            const phone = document.getElementById('newSupplierPhone').value.trim();

            if (!name) {
                alert('يرجى إدخال اسم المورد');
                return;
            }

            $.ajax({
                url: 'purchases.php',
                method: 'POST',
                data: { action: 'add_new_supplier', supplier_name: name, supplier_phone: phone },
                success: function(response) {
                    if (response.success) {
                        const select = document.getElementById('modalSupplier');
                        const option = document.createElement('option');
                        option.value = response.id;
                        option.text = response.name + ' (' + response.code + ')';
                        select.add(option);
                        select.value = response.id;

                        document.getElementById('newSupplierName').value = '';
                        document.getElementById('newSupplierPhone').value = '';
                        alert('تم إضافة المورد بنجاح');
                    } else {
                        alert('خطأ: ' + response.message);
                    }
                }
            });
        }

        function savePrice() {
            const itemId = document.getElementById('modalItemId').value;
            const supplierId = document.getElementById('modalSupplier').value;
            const supplierPrice = document.getElementById('modalSupplierPrice').value;
            const sellPrice = document.getElementById('modalSellPrice').value;
            const profitMargin = document.getElementById('modalProfitMargin').value;
            const deliveryTimeId = document.getElementById('modalDeliveryTime').value;
            const notes = document.getElementById('modalNotes').value;

            if (!supplierId || !supplierPrice || !sellPrice) {
                alert('يرجى ملء جميع البيانات المطلوبة');
                return;
            }

            $.ajax({
                url: 'purchases.php',
                method: 'POST',
                data: {
                    action: 'add_supplier_price',
                    item_id: itemId,
                    supplier_id: supplierId,
                    supplier_price: supplierPrice,
                    sell_price: sellPrice,
                    profit_margin: profitMargin,
                    delivery_time_id: deliveryTimeId,
                    notes: notes
                },
                success: function(response) {
                    if (response.success) {
                        alert('تم حفظ السعر بنجاح');
                        location.reload();
                    } else {
                        alert('خطأ: ' + response.message);
                    }
                }
            });
        }

        function purchaseFromSupplier(itemId, supplierId, supplierPrice, sellPrice, profitMargin, quantity) {
            if (!confirm('هل تريد شراء هذا الصنف من المورد بسعر ' + supplierPrice + ' ج وبيعه بـ ' + sellPrice + ' ج؟')) return;

            $.ajax({
                url: 'purchases.php',
                method: 'POST',
                data: {
                    action: 'mark_purchased',
                    item_id: itemId,
                    supplier_id: supplierId,
                    supplier_price: supplierPrice,
                    sell_price: sellPrice,
                    profit_margin: profitMargin,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        alert('تم تسجيل الشراء بنجاح');
                        location.reload();
                    } else {
                        alert('خطأ: ' + response.message);
                    }
                }
            });
        }

        function setOrderPrice(itemId, supplierId, supplierPrice, sellPrice, profitMargin) {
            if (!confirm('هل تريد تسجيل هذا السعر (' + sellPrice + ' ج) في الطلب للعميل؟\n\nملاحظة: لن يتم تسجيل شراء فعلي.')) return;

            $.ajax({
                url: 'purchases.php',
                method: 'POST',
                data: {
                    action: 'set_order_price',
                    item_id: itemId,
                    supplier_id: supplierId,
                    supplier_price: supplierPrice,
                    sell_price: sellPrice,
                    profit_margin: profitMargin
                },
                success: function(response) {
                    if (response.success) {
                        alert('✅ ' + response.message);
                        location.reload();
                    } else {
                        alert('❌ خطأ: ' + response.message);
                    }
                }
            });
        }
    </script>
</body>
</html>
