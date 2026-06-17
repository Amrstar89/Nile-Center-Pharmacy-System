<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

// Get active customers
$stmt = $db->query("SELECT * FROM customers WHERE is_active = 1 ORDER BY customer_name");
$customers = $stmt->fetchAll();

// Get branches
$stmt = $db->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name");
$branches = $stmt->fetchAll();

// Get products from ESTOCK or MySQL
require_once __DIR__ . '/../../core/estock-bridge.php';

$products = [];
$search_query = $_GET['search'] ?? '';

if (!empty($search_query)) {
    $products = searchESTOCKProducts($search_query, 100);
} else {
    $products = getESTOCKProducts(1, 100);
}

if (empty($products)) {
    $stmt = $db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY product_name LIMIT 100");
    $products = $stmt->fetchAll();
}

// Get active statuses
$stmt = $db->query("SELECT * FROM order_statuses WHERE is_active = 1 ORDER BY sort_order");
$statuses = $stmt->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Customer handling
        $customer_id = $_POST['customer_id'] ?? null;
        $customer_name = $_POST['customer_name'] ?? '';
        $customer_phone = $_POST['customer_phone'] ?? '';
        $customer_phone2 = $_POST['customer_phone2'] ?? '';
        $customer_address = $_POST['customer_address'] ?? '';
        $customer_code = $_POST['customer_code'] ?? null;
        $branch_code = $_POST['branch_code'] ?? null;
        
        // If new customer, add to database
        if (empty($customer_id) && !empty($customer_name)) {
            $stmt = $db->prepare("INSERT INTO customers (customer_code, customer_name, phone, phone2, address, branch_code, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $new_code = 'CUST' . time();
            $stmt->execute([$new_code, $customer_name, $customer_phone, $customer_phone2, $customer_address, $branch_code]);
            $customer_id = $db->lastInsertId();
            $customer_code = $new_code;
        }

        $priority = $_POST['priority'] ?? 'normal';
        $status_id = $_POST['status_id'] ?? 1;
        $notes = $_POST['notes'] ?? '';
        
        // Total discount
        $total_discount_type = $_POST['total_discount_type'] ?? null;
        $total_discount_value = $_POST['total_discount_value'] ?? 0;

        $order_number = generateOrderNumber();

        // Calculate total first
        $items = $_POST['items'] ?? [];
        $calculated_total = 0;
        
        foreach ($items as $item) {
            if (!empty($item['product_name'])) {
                $unit_price = (float)($item['unit_price'] ?? 0);
                $quantity = (int)($item['quantity'] ?? 1);
                $item_discount_type = $item['discount_type'] ?? null;
                $item_discount_value = (float)($item['discount_value'] ?? 0);
                
                $item_total = $unit_price * $quantity;
                
                if ($item_discount_type === 'percentage') {
                    $item_total -= ($item_total * $item_discount_value / 100);
                } elseif ($item_discount_type === 'fixed') {
                    $item_total -= $item_discount_value;
                }
                
                $calculated_total += max(0, $item_total);
            }
        }
        
        // Apply total discount
        $final_total = $calculated_total;
        if ($total_discount_type === 'percentage') {
            $final_total -= ($calculated_total * $total_discount_value / 100);
        } elseif ($total_discount_type === 'fixed') {
            $final_total -= $total_discount_value;
        }
        $final_total = max(0, $final_total);

        // Insert order
        $stmt = $db->prepare("
            INSERT INTO orders (order_number, customer_id, customer_code, customer_name, customer_phone, customer_phone2, customer_address, branch_code, 
                              priority, status_id, total_discount_type, total_discount_value, final_total, notes, created_by, order_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $order_number, $customer_id, $customer_code, $customer_name, $customer_phone, $customer_phone2, $customer_address, $branch_code,
            $priority, $status_id, $total_discount_type, $total_discount_value, $final_total, $notes, $_SESSION['user_id']
        ]);

        $order_id = $db->lastInsertId();

        // Insert order items
        $total_items = 0;
        foreach ($items as $item) {
            if (!empty($item['product_name'])) {
                $product_id = !empty($item['product_id']) ? (int)$item['product_id'] : null;
                $product_code = !empty($item['product_code']) ? $item['product_code'] : null;
                $product_name = $item['product_name'];
                $quantity = (int)($item['quantity'] ?? 1);
                $unit_price = (float)($item['unit_price'] ?? 0);
                $item_discount_type = $item['discount_type'] ?? null;
                $item_discount_value = (float)($item['discount_value'] ?? 0);
                $item_notes = $item['notes'] ?? '';
                $is_manual = !empty($item['is_manual']) ? 1 : 0;
                $needs_purchase = !empty($item['needs_purchase']) ? 1 : 0;
                
                // Calculate final price
                $item_total = $unit_price * $quantity;
                $final_price = $item_total;
                
                if ($item_discount_type === 'percentage') {
                    $final_price -= ($item_total * $item_discount_value / 100);
                } elseif ($item_discount_type === 'fixed') {
                    $final_price -= $item_discount_value;
                }
                $final_price = max(0, $final_price);

                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, product_code, product_name, quantity, unit_price, 
                                           discount_type, discount_value, final_price, is_manual, needs_purchase, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id, $product_id, $product_code, $product_name, $quantity, $unit_price,
                    $item_discount_type, $item_discount_value, $final_price, $is_manual, $needs_purchase, $item_notes
                ]);
                $total_items++;
            }
        }

        $db->prepare("UPDATE orders SET total_items = ? WHERE id = ?")
           ->execute([$total_items, $order_id]);

        logActivity('create', 'orders', $order_id, null, ['order_number' => $order_number]);

        $db->commit();

        $_SESSION['success'] = "تم إنشاء الطلب رقم $order_number بنجاح";
redirect(APP_URL . '/modules/customer-requests/orders.php');

    } catch (Exception $e) {
        $db->rollBack();
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

$page_title = 'طلب جديد';
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
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000;
        }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h4 { color: white; margin: 0; font-weight: 700; }
        .nav-menu { padding: 15px 0; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; text-decoration: none; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; border-right: 3px solid var(--primary); }
        .nav-link i { width: 25px; margin-left: 10px; font-size: 18px; }
        .main-content { margin-right: 260px; padding: 20px; }
        .topbar { background: white; border-radius: 15px; padding: 15px 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .content-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #495057; }
        .form-control, .form-select { border-radius: 10px; padding: 12px 15px; border: 2px solid #e0e0e0; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn-submit { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 10px; padding: 12px 30px; color: white; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-2px); color: white; }
        .item-row { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; border: 2px solid transparent; }
        .item-row.manual { border-color: #ffc107; }
        .item-row.purchase-needed { border-color: #dc3545; }
        .btn-add-item { background: #e9ecef; border: 2px dashed #adb5bd; border-radius: 10px; padding: 15px; width: 100%; color: #6c757d; font-weight: 600; }
        .btn-add-item:hover { background: #dee2e6; }
        .btn-remove-item { color: #dc3545; border: none; background: transparent; }
        .select2-container { width: 100% !important; }
        .select2-selection { border: 2px solid #e0e0e0 !important; border-radius: 10px !important; padding: 8px !important; height: auto !important; }
        .select2-selection:focus { border-color: var(--primary) !important; }
        .priority-radio { display: none; }
        .priority-label { padding: 10px 20px; border-radius: 10px; cursor: pointer; transition: all 0.3s; border: 2px solid #e0e0e0; text-align: center; }
        .priority-radio:checked + .priority-label { border-color: var(--primary); background: rgba(102, 126, 234, 0.1); }
        .priority-normal:checked + .priority-label { border-color: #198754; background: rgba(25, 135, 84, 0.1); }
        .priority-urgent:checked + .priority-label { border-color: #ffc107; background: rgba(255, 193, 7, 0.1); }
        .priority-critical:checked + .priority-label { border-color: #dc3545; background: rgba(220, 53, 69, 0.1); }
        .discount-row { background: #fff3cd; border-radius: 8px; padding: 10px; margin-top: 10px; }
        .total-row { background: #d1ecf1; border-radius: 8px; padding: 15px; margin-top: 15px; font-weight: bold; }
        @media (max-width: 768px) { .sidebar { width: 0; overflow: hidden; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
  <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div><h5 class="mb-0"><?= $page_title ?></h5><small class="text-muted">إنشاء طلب جديد للعميل</small></div>
            <div class="d-flex align-items-center">
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg, var(--primary), var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;margin-left:10px;"><?= mb_substr($_SESSION['user_name'], 0, 1) ?></div>
                <div><div class="fw-bold"><?= $_SESSION['user_name'] ?></div><small class="text-muted"><?= $_SESSION['user_role'] === 'admin' ? 'مدير النظام' : 'صيدلي' ?></small></div>
            </div>
        </div>

        <?php if ($error): ?><?= showAlert($error, 'danger') ?><?php endif; ?>

        <div class="content-card">
            <form method="POST" action="" id="orderForm">
                <!-- Customer Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-person me-2"></i>العميل (مختار)</label>
                        <select class="form-select select2-customer" name="customer_id" id="customerSelect">
                            <option value="">-- اختر عميل موجود --</option>
                            <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['id'] ?>" data-code="<?= $cust['customer_code'] ?>" data-branch="<?= $cust['branch_code'] ?>" data-phone="<?= $cust['phone'] ?>" data-phone2="<?= $cust['phone2'] ?>" data-address="<?= $cust['address'] ?>">
                                <?= $cust['customer_name'] ?> (<?= $cust['customer_code'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-person-plus me-2"></i>أو عميل جديد</label>
                        <input type="text" class="form-control" name="customer_name" id="customerName" placeholder="اسم العميل الجديد">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-telephone me-2"></i>تليفون 1</label>
                        <input type="text" class="form-control" name="customer_phone" id="customerPhone" placeholder="رقم التليفون">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label"><i class="bi bi-telephone-plus me-2"></i>تليفون 2</label>
                        <input type="text" class="form-control" name="customer_phone2" id="customerPhone2" placeholder="رقم تاني (واتساب/أجنبي)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-geo-alt me-2"></i>عنوان العميل</label>
                        <input type="text" class="form-control" name="customer_address" id="customerAddress" placeholder="عنوان العميل للتوصيل">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">كود العميل (للربط المستقبلي)</label>
                        <input type="text" class="form-control" name="customer_code" id="customerCode" placeholder="كود العميل من ESTOCK">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-building me-2"></i>الفرع</label>
                        <select class="form-select" name="branch_code" id="branchCode">
                            <option value="">-- اختر الفرع --</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['branch_code'] ?>"><?= $branch['branch_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Priority -->
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-flag me-2"></i>أولوية الطلب</label>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="radio" name="priority" id="priorityNormal" value="normal" class="priority-radio priority-normal" checked>
                            <label for="priorityNormal" class="priority-label w-100">
                                <i class="bi bi-circle text-success d-block mb-1"></i>
                                <strong>عادي</strong>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input type="radio" name="priority" id="priorityUrgent" value="urgent" class="priority-radio priority-urgent">
                            <label for="priorityUrgent" class="priority-label w-100">
                                <i class="bi bi-exclamation-circle text-warning d-block mb-1"></i>
                                <strong>عاجل</strong>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input type="radio" name="priority" id="priorityCritical" value="critical" class="priority-radio priority-critical">
                            <label for="priorityCritical" class="priority-label w-100">
                                <i class="bi bi-exclamation-triangle text-danger d-block mb-1"></i>
                                <strong>حرج</strong>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-tag me-2"></i>الحالة الابتدائية</label>
                    <select class="form-select" name="status_id">
                        <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status['id'] ?>" <?= $status['is_default'] ? 'selected' : '' ?> style="color: <?= $status['status_color'] ?>">
                            <?= $status['status_name'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Order Items -->
                <div class="mb-4">
                    <label class="form-label"><i class="bi bi-box-seam me-2"></i>الأصناف المطلوبة</label>
                    <div id="itemsContainer">
                        <div class="item-row" data-index="0">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <select class="form-select product-select" name="items[0][product_id]" onchange="updateProductInfo(this, 0)">
                                        <option value="">-- اختر صنف من السيستم --</option>
                                        <?php foreach ($products as $prod): ?>
                                        <option value="<?= $prod['product_id'] ?>" data-code="<?= $prod['product_code'] ?>" data-price="<?= $prod['sell_price'] ?? 0 ?>">
                                            <?= htmlspecialchars($prod['product_name_en'] ?? $prod['product_name_ar'] ?? $prod['product_code']) ?> (<?= $prod['product_code'] ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="items[0][product_code]" id="productCode0">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="items[0][is_manual]" id="isManual0" value="1" onchange="toggleManual(0)">
                                        <label class="form-check-label" for="isManual0">صنف يدوي (غير موجود في السيستم)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="items[0][needs_purchase]" id="needsPurchase0" value="1">
                                        <label class="form-check-label text-danger" for="needsPurchase0"><i class="bi bi-cart-plus"></i> يحتاج شراء</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="items[0][product_name]" id="productName0" placeholder="اسم الصنف" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">الكمية</label>
                                    <input type="number" class="form-control" name="items[0][quantity]" id="quantity0" placeholder="الكمية" value="1" min="1" oninput="calculateItem(0)">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">السعر</label>
                                    <input type="number" class="form-control" name="items[0][unit_price]" id="unitPrice0" placeholder="السعر" step="0.01" oninput="calculateItem(0)">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-remove-item" onclick="removeItem(this)"><i class="bi bi-trash"></i></button>
                                </div>
                                <!-- Discount -->
                                <div class="col-12 discount-row">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <select class="form-select form-select-sm" name="items[0][discount_type]" id="discountType0" onchange="calculateItem(0)">
                                                <option value="">بدون خصم</option>
                                                <option value="percentage">خصم %</option>
                                                <option value="fixed">خصم قيمة</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" class="form-control form-control-sm" name="items[0][discount_value]" id="discountValue0" placeholder="قيمة الخصم" step="0.01" oninput="calculateItem(0)">
                                        </div>
                                        <div class="col-md-4">
                                            <span class="form-control form-control-sm bg-light" id="finalPrice0">0.00 ج</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <input type="text" class="form-control" name="items[0][notes]" placeholder="ملاحظات على الصنف">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-add-item mt-2" onclick="addItem()">
                        <i class="bi bi-plus-lg me-2"></i>إضافة صنف آخر
                    </button>
                </div>

                <!-- Total Discount -->
                <div class="total-row">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">خصم على الإجمالي</label>
                            <select class="form-select" name="total_discount_type" id="totalDiscountType" onchange="calculateTotal()">
                                <option value="">بدون خصم</option>
                                <option value="percentage">خصم %</option>
                                <option value="fixed">خصم قيمة</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">قيمة الخصم</label>
                            <input type="number" class="form-control" name="total_discount_value" id="totalDiscountValue" placeholder="0.00" step="0.01" oninput="calculateTotal()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الإجمالي قبل الخصم</label>
                            <div class="form-control bg-light" id="subTotal">0.00 ج</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الإجمالي النهائي</label>
                            <div class="form-control bg-success text-white fw-bold" id="finalTotal">0.00 ج</div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-4 mt-4">
                    <label class="form-label"><i class="bi bi-sticky me-2"></i>ملاحظات عامة</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="أي ملاحظات إضافية على الطلب..."></textarea>
                </div>

                <!-- Submit -->
                <div class="text-center">
                    <button type="submit" class="btn btn-submit btn-lg">
                        <i class="bi bi-check-lg me-2"></i>حفظ الطلب
                    </button>
                    <a href="orders.php" class="btn btn-outline-secondary btn-lg ms-2">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Use base64 to avoid HTML encoding issues
    const productsData = JSON.parse(atob('<?= base64_encode(json_encode($products, JSON_UNESCAPED_UNICODE)) ?>'));

    let itemIndex = 0;

    $(document).ready(function() {
        $('.select2-customer').select2({ placeholder: 'ابحث عن عميل...', allowClear: true });
        
        // Initialize Select2 on first item
        initSelect2($('#itemsContainer .product-select'));

        $('#customerSelect').on('change', function() {
            const selected = $(this).find(':selected');
            $('#customerCode').val(selected.data('code') || '');
            $('#customerPhone').val(selected.data('phone') || '');
            $('#customerPhone2').val(selected.data('phone2') || '');
            $('#customerAddress').val(selected.data('address') || '');
            $('#branchCode').val(selected.data('branch') || '');
            if (selected.val()) {
                $('#customerName').val(selected.text().split('(')[0].trim());
            }
        });
        
        // Calculate on load
        calculateTotal();
    });

    function initSelect2($elements) {
        $elements.each(function() {
            const $select = $(this);
            if (!$select.data('select2')) {
                $select.select2({
                    placeholder: 'ابحث عن صنف...',
                    allowClear: true,
                    width: '100%'
                }).on('change', function() {
                    const index = $(this).closest('.item-row').data('index');
                    updateProductInfo(this, index);
                });
            }
        });
    }

    function toggleManual(index) {
        const isManual = document.getElementById('isManual' + index).checked;
        const row = document.querySelector('.item-row[data-index="' + index + '"]');
        const select = row.querySelector('select[name^="items["]');
        const nameInput = document.getElementById('productName' + index);
        
        if (isManual) {
            row.classList.add('manual');
            if (select) {
                const $select = $(select);
                if ($select.data('select2')) {
                    $select.val(null).trigger('change');
                } else {
                    select.value = '';
                }
            }
            nameInput.placeholder = 'اكتب اسم الصنف يدوياً';
            nameInput.focus();
        } else {
            row.classList.remove('manual');
            nameInput.placeholder = 'اسم الصنف';
        }
    }

    function updateProductInfo(select, index) {
        const selected = select.options[select.selectedIndex];
        if (selected && selected.value) {
            document.getElementById('productCode' + index).value = selected.dataset.code || '';
            document.getElementById('productName' + index).value = selected.text.split('(')[0].trim();
            document.getElementById('unitPrice' + index).value = selected.dataset.price || 0;
            calculateItem(index);
        }
    }

    function calculateItem(index) {
        const qty = document.getElementById('quantity' + index);
        const price = document.getElementById('unitPrice' + index);
        const discType = document.getElementById('discountType' + index);
        const discVal = document.getElementById('discountValue' + index);
        const final = document.getElementById('finalPrice' + index);
        
        if (!qty || !price || !final) {
            console.error('Missing elements for index:', index);
            return;
        }
        
        const quantity = parseFloat(qty.value) || 0;
        const unitPrice = parseFloat(price.value) || 0;
        const discountType = discType ? discType.value : '';
        const discountValue = discVal ? parseFloat(discVal.value) || 0 : 0;
        
        let total = quantity * unitPrice;
        
        if (discountType === 'percentage') {
            total -= (total * discountValue / 100);
        } else if (discountType === 'fixed') {
            total -= discountValue;
        }
        
        total = Math.max(0, total);
        final.textContent = total.toFixed(2) + ' ج';
        
        calculateTotal();
    }

    function calculateTotal() {
        let subTotal = 0;
        let finalTotal = 0;
        
        document.querySelectorAll('.item-row').forEach(row => {
            const index = row.getAttribute('data-index');
            const qty = document.getElementById('quantity' + index);
            const price = document.getElementById('unitPrice' + index);
            const discType = document.getElementById('discountType' + index);
            const discVal = document.getElementById('discountValue' + index);
            
            if (!qty || !price) return;
            
            const quantity = parseFloat(qty.value) || 0;
            const unitPrice = parseFloat(price.value) || 0;
            const discountType = discType ? discType.value : '';
            const discountValue = discVal ? parseFloat(discVal.value) || 0 : 0;
            
            const itemSubTotal = quantity * unitPrice;
            let itemFinal = itemSubTotal;
            
            if (discountType === 'percentage') {
                itemFinal -= (itemSubTotal * discountValue / 100);
            } else if (discountType === 'fixed') {
                itemFinal -= discountValue;
            }
            itemFinal = Math.max(0, itemFinal);
            
            subTotal += itemSubTotal;
            finalTotal += itemFinal;
        });
        
        const totalDiscType = document.getElementById('totalDiscountType');
        const totalDiscVal = document.getElementById('totalDiscountValue');
        
        const totalDiscountType = totalDiscType ? totalDiscType.value : '';
        const totalDiscountValue = totalDiscVal ? parseFloat(totalDiscVal.value) || 0 : 0;
        
        if (totalDiscountType === 'percentage') {
            finalTotal -= (finalTotal * totalDiscountValue / 100);
        } else if (totalDiscountType === 'fixed') {
            finalTotal -= totalDiscountValue;
        }
        finalTotal = Math.max(0, finalTotal);
        
        const subTotalEl = document.getElementById('subTotal');
        const finalTotalEl = document.getElementById('finalTotal');
        
        if (subTotalEl) subTotalEl.textContent = subTotal.toFixed(2) + ' ج';
        if (finalTotalEl) finalTotalEl.textContent = finalTotal.toFixed(2) + ' ج';
    }

    function addItem() {
        itemIndex++;
        const container = document.getElementById('itemsContainer');
        const newRow = document.createElement('div');
        newRow.className = 'item-row';
        newRow.setAttribute('data-index', itemIndex);
        
        // Build options from JSON
        let optionsHtml = '<option value="">-- اختر صنف من السيستم --</option>';
        productsData.forEach(prod => {
            const name = prod.product_name_en || prod.product_name_ar || prod.product_code;
            const price = prod.sell_price || 0;
            optionsHtml += '<option value="' + prod.product_id + '" data-code="' + (prod.product_code || '') + '" data-price="' + price + '">' + escapeHtml(name) + ' (' + prod.product_code + ')</option>';
        });
        
        newRow.innerHTML = `
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select product-select" name="items[${itemIndex}][product_id]">
                        ${optionsHtml}
                    </select>
                    <input type="hidden" name="items[${itemIndex}][product_code]" id="productCode${itemIndex}">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="items[${itemIndex}][is_manual]" id="isManual${itemIndex}" value="1" onchange="toggleManual(${itemIndex})">
                        <label class="form-check-label" for="isManual${itemIndex}">صنف يدوي (غير موجود في السيستم)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="items[${itemIndex}][needs_purchase]" id="needsPurchase${itemIndex}" value="1">
                        <label class="form-check-label text-danger" for="needsPurchase${itemIndex}"><i class="bi bi-cart-plus"></i> يحتاج شراء</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="items[${itemIndex}][product_name]" id="productName${itemIndex}" placeholder="اسم الصنف" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">الكمية</label>
                    <input type="number" class="form-control" name="items[${itemIndex}][quantity]" id="quantity${itemIndex}" placeholder="الكمية" value="1" min="1" oninput="calculateItem(${itemIndex})" onchange="calculateItem(${itemIndex})">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">السعر</label>
                    <input type="number" class="form-control" name="items[${itemIndex}][unit_price]" id="unitPrice${itemIndex}" placeholder="السعر" step="0.01" oninput="calculateItem(${itemIndex})" onchange="calculateItem(${itemIndex})">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-remove-item" onclick="removeItem(this)"><i class="bi bi-trash"></i></button>
                </div>
                <div class="col-12 discount-row">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" name="items[${itemIndex}][discount_type]" id="discountType${itemIndex}" onchange="calculateItem(${itemIndex})">
                                <option value="">بدون خصم</option>
                                <option value="percentage">خصم %</option>
                                <option value="fixed">خصم قيمة</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="number" class="form-control form-control-sm" name="items[${itemIndex}][discount_value]" id="discountValue${itemIndex}" placeholder="قيمة الخصم" step="0.01" oninput="calculateItem(${itemIndex})" onchange="calculateItem(${itemIndex})">
                        </div>
                        <div class="col-md-4">
                            <span class="form-control form-control-sm bg-light" id="finalPrice${itemIndex}">0.00 ج</span>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <input type="text" class="form-control" name="items[${itemIndex}][notes]" placeholder="ملاحظات على الصنف">
                </div>
            </div>
        `;
        
        container.appendChild(newRow);
        
        // Initialize Select2 on new select
        initSelect2($(newRow).find('.product-select'));
    }

    function removeItem(btn) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 1) {
            btn.closest('.item-row').remove();
            calculateTotal();
        } else {
            alert('يجب أن يحتوي الطلب على صنف واحد على الأقل');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>
</body>
</html>