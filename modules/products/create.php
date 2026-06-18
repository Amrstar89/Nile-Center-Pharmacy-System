<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إضافة صنف جديد';

// Get lookup data
$categories = $db->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY category_name_ar")->fetchAll();
$companies = $db->query("SELECT * FROM product_companies WHERE is_active = 1 ORDER BY company_name_ar")->fetchAll();
$units = $db->query("SELECT * FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();
$product_types = $db->query("SELECT * FROM product_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Generate product code
        $prefix = 'PRD';
        $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(product_code, 4) AS UNSIGNED)) as max_num FROM products WHERE product_code LIKE 'PRD%'");
        $max_num = $stmt->fetch()['max_num'] ?? 0;
        $product_code = $prefix . str_pad($max_num + 1, 5, '0', STR_PAD_LEFT);

        // Insert product
        $stmt = $db->prepare("
            INSERT INTO products (
                product_code, product_name, product_name_en, scientific_name,
                product_type_id, is_service, hide_in_receipt, is_shortage,
                max_stock, min_stock, reorder_point,
                category_id, company_id, group_id,
                sell_price, cost_price, unit2_sell_price, unit3_sell_price,
                unit1_id, unit2_id, unit3_id, unit1_to_unit2, unit1_to_unit3, default_sale_unit,
                has_expire, is_drug, is_imported, can_be_negative, is_made,
                print_barcode, barcode_type, print_internal_barcode,
                allow_discount, max_discount,
                notes, source, manual_code
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $product_code,
            $_POST['product_name'] ?? '',
            $_POST['product_name_en'] ?? null,
            $_POST['scientific_name'] ?? null,
            $_POST['product_type_id'] ?? 1,
            $_POST['is_service'] ?? 0,
            $_POST['hide_in_receipt'] ?? 0,
            $_POST['is_shortage'] ?? 0,
            $_POST['max_stock'] ?? 0,
            $_POST['min_stock'] ?? 0,
            $_POST['reorder_point'] ?? 0,
            $_POST['category_id'] ?? null,
            $_POST['company_id'] ?? null,
            $_POST['group_id'] ?? null,
            $_POST['sell_price'] ?? 0,
            $_POST['cost_price'] ?? 0,
            $_POST['unit2_sell_price'] ?? 0,
            $_POST['unit3_sell_price'] ?? 0,
            $_POST['unit1_id'] ?? null,
            $_POST['unit2_id'] ?? null,
            $_POST['unit3_id'] ?? null,
            $_POST['unit1_to_unit2'] ?? null,
            $_POST['unit1_to_unit3'] ?? null,
            $_POST['default_sale_unit'] ?? 1,
            $_POST['has_expire'] ?? 0,
            $_POST['is_drug'] ?? 0,
            $_POST['is_imported'] ?? 0,
            $_POST['can_be_negative'] ?? 0,
            $_POST['is_made'] ?? 0,
            $_POST['print_barcode'] ?? 0,
            $_POST['barcode_type'] ?? null,
            $_POST['print_internal_barcode'] ?? 0,
            $_POST['allow_discount'] ?? 1,
            $_POST['max_discount'] ?? 0,
            $_POST['notes'] ?? null,
            'manual',
            'M-' . str_pad($max_num + 1, 5, '0', STR_PAD_LEFT)
        ]);

        $product_id = $db->lastInsertId();

        // Insert barcodes
        if (!empty($_POST['barcodes'])) {
            $barcode_stmt = $db->prepare("INSERT INTO product_barcodes (product_id, barcode, unit_id, is_primary) VALUES (?, ?, ?, ?)");
            foreach ($_POST['barcodes'] as $index => $barcode) {
                if (!empty($barcode)) {
                    $barcode_stmt->execute([
                        $product_id,
                        $barcode,
                        $_POST['barcode_units'][$index] ?? 1,
                        $index === 0 ? 1 : 0
                    ]);
                }
            }
        }

        // Insert locations
        if (!empty($_POST['locations'])) {
            $location_stmt = $db->prepare("INSERT INTO product_locations (product_id, location_code, location_name, shelf_number, row_number) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['locations'] as $index => $location) {
                if (!empty($location)) {
                    $location_stmt->execute([
                        $product_id,
                        $location,
                        $_POST['location_names'][$index] ?? $location,
                        $_POST['shelf_numbers'][$index] ?? null,
                        $_POST['row_numbers'][$index] ?? null
                    ]);
                }
            }
        }

        // Insert alerts
        if (!empty($_POST['alerts'])) {
            $alert_stmt = $db->prepare("INSERT INTO product_alerts (product_id, alert_type, alert_message) VALUES (?, ?, ?)");
            foreach ($_POST['alerts'] as $index => $alert_type) {
                if (!empty($alert_type) && !empty($_POST['alert_messages'][$index])) {
                    $alert_stmt->execute([
                        $product_id,
                        $alert_type,
                        $_POST['alert_messages'][$index]
                    ]);
                }
            }
        }

        // Insert supplier pricing
        if (!empty($_POST['supplier_ids'])) {
            $supplier_stmt = $db->prepare("INSERT INTO supplier_product_pricing (product_id, supplier_id, purchase_price, sell_price, discount_percent, vat_percent, is_default, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['supplier_ids'] as $index => $supplier_id) {
                if (!empty($supplier_id)) {
                    $supplier_stmt->execute([
                        $product_id,
                        $supplier_id,
                        $_POST['supplier_prices'][$index] ?? 0,
                        $_POST['supplier_sell_prices'][$index] ?? 0,
                        $_POST['supplier_discounts'][$index] ?? 0,
                        $_POST['supplier_vats'][$index] ?? 0,
                        $index === 0 ? 1 : 0,
                        $_POST['supplier_notes'][$index] ?? null
                    ]);
                }
            }
        }

        $db->commit();

        // Redirect to view page
        header("Location: view.php?id=" . $product_id);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// Include sidebar
require_once __DIR__ . '/../../includes/sidebar.php';
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
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #0dcaf0;
        }
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            position: fixed;
            right: 0;
            top: 0;
            width: 260px;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand h4 {
            color: white;
            margin: 0;
            font-weight: 700;
        }
        .sidebar-brand small {
            color: rgba(255,255,255,0.6);
        }
        .nav-menu {
            padding: 15px 0;
        }
        .nav-item {
            margin: 2px 0;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            text-decoration: none;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-right: 3px solid var(--primary);
        }
        .nav-link i {
            width: 25px;
            margin-left: 10px;
            font-size: 18px;
        }
        .main-content {
            margin-right: 260px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .nav-tabs .nav-link {
            color: #666;
            border: none;
            padding: 15px 20px;
        }
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
        }
        .sidebar-heading {
            color: rgba(255,255,255,0.5);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 20px 5px;
            font-weight: 600;
        }
        .form-label {
            font-weight: 600;
            color: #555;
        }
        .unit-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .unit-card h6 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; }
            .main-content { margin-right: 0; }
        }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-plus-circle"></i> <?= $page_title ?></h2>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-right"></i> العودة للقائمة
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="productForm">

                <!-- Tabs Navigation -->
                <div class="card">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs" id="productTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="basic-tab" data-bs-toggle="tab" href="#basic" role="tab">
                                    <i class="bi bi-info-circle"></i> البيانات الأساسية
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="barcodes-tab" data-bs-toggle="tab" href="#barcodes" role="tab">
                                    <i class="bi bi-upc"></i> الباركود
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="units-tab" data-bs-toggle="tab" href="#units" role="tab">
                                    <i class="bi bi-balance-scale"></i> الوحدات
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pricing-tab" data-bs-toggle="tab" href="#pricing" role="tab">
                                    <i class="bi bi-tag"></i> التسعير
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="stock-tab" data-bs-toggle="tab" href="#stock" role="tab">
                                    <i class="bi bi-warehouse"></i> المخزون
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="suppliers-tab" data-bs-toggle="tab" href="#suppliers" role="tab">
                                    <i class="bi bi-truck"></i> الموردين
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="alerts-tab" data-bs-toggle="tab" href="#alerts" role="tab">
                                    <i class="bi bi-exclamation-triangle"></i> التحذيرات
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body">
                        <div class="tab-content" id="productTabContent">

                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم الصنف <span class="text-danger">*</span></label>
                                            <input type="text" name="product_name" class="form-control" required 
                                                   placeholder="اسم الصنف بالعربي">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم الصنف بالإنجليزي</label>
                                            <input type="text" name="product_name_en" class="form-control" 
                                                   placeholder="Product Name in English">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">المادة الفعالة (الاسم العلمي)</label>
                                            <input type="text" name="scientific_name" class="form-control" 
                                                   placeholder="Active Ingredient">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نوع الصنف <span class="text-danger">*</span></label>
                                            <select name="product_type_id" class="form-select" required>
                                                <?php foreach ($product_types as $type): ?>
                                                    <option value="<?= $type['id'] ?>"><?= $type['type_name_ar'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">القسم</label>
                                            <select name="category_id" class="form-select">
                                                <option value="">-- اختر القسم --</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat['id'] ?>"><?= $cat['category_name_ar'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الشركة المنتجة</label>
                                            <select name="company_id" class="form-select">
                                                <option value="">-- اختر الشركة --</option>
                                                <?php foreach ($companies as $comp): ?>
                                                    <option value="<?= $comp['id'] ?>"><?= $comp['company_name_ar'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">ملاحظات</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات عامة..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_service" value="1" class="form-check-input" id="is_service">
                                            <label class="form-check-label" for="is_service">صنف خدمي</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="hide_in_receipt" value="1" class="form-check-input" id="hide_in_receipt">
                                            <label class="form-check-label" for="hide_in_receipt">إخفاء في الريسيت</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_shortage" value="1" class="form-check-input" id="is_shortage">
                                            <label class="form-check-label" for="is_shortage">نواقص عامة</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="has_expire" value="1" class="form-check-input" id="has_expire">
                                            <label class="form-check-label" for="has_expire">له تاريخ صلاحية</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Barcodes Tab -->
                            <div class="tab-pane fade" id="barcodes" role="tabpanel">
                                <div id="barcodesContainer">
                                    <div class="barcode-row row mb-2">
                                        <div class="col-md-4">
                                            <input type="text" name="barcodes[]" class="form-control" placeholder="باركود دولي 1">
                                        </div>
                                        <div class="col-md-3">
                                            <select name="barcode_units[]" class="form-select">
                                                <option value="1">الوحدة الكبرى</option>
                                                <option value="2">الوحدة الوسطى</option>
                                                <option value="3">الوحدة الصغرى</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-check mt-2">
                                                <input type="radio" name="primary_barcode" value="0" class="form-check-input" checked>
                                                <label class="form-check-label">رئيسي</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-success btn-sm add-barcode">
                                                <i class="bi bi-plus-lg"></i> إضافة
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="form-check">
                                            <input type="checkbox" name="print_barcode" value="1" class="form-check-input" id="print_barcode">
                                            <label class="form-check-label" for="print_barcode">طباعة باركود داخلي</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Units Tab -->
                            <div class="tab-pane fade" id="units" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="unit-card">
                                            <h6><i class="bi bi-box"></i> الوحدة الكبرى</h6>
                                            <div class="mb-3">
                                                <label class="form-label">نوع الوحدة</label>
                                                <select name="unit1_id" class="form-select">
                                                    <option value="">-- اختر --</option>
                                                    <?php foreach ($units as $unit): ?>
                                                        <option value="<?= $unit['id'] ?>"><?= $unit['unit_name_ar'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="sell_price" class="form-control" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="unit-card">
                                            <h6><i class="bi bi-box-seam"></i> الوحدة الوسطى</h6>
                                            <div class="mb-3">
                                                <label class="form-label">نوع الوحدة</label>
                                                <select name="unit2_id" class="form-select">
                                                    <option value="">-- اختر --</option>
                                                    <?php foreach ($units as $unit): ?>
                                                        <option value="<?= $unit['id'] ?>"><?= $unit['unit_name_ar'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">عدد الوحدات</label>
                                                <input type="number" name="unit1_to_unit2" class="form-control" placeholder="مثال: 10">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="unit2_sell_price" class="form-control" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="unit-card">
                                            <h6><i class="bi bi-box2"></i> الوحدة الصغرى</h6>
                                            <div class="mb-3">
                                                <label class="form-label">نوع الوحدة</label>
                                                <select name="unit3_id" class="form-select">
                                                    <option value="">-- اختر --</option>
                                                    <?php foreach ($units as $unit): ?>
                                                        <option value="<?= $unit['id'] ?>"><?= $unit['unit_name_ar'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">عدد الوحدات</label>
                                                <input type="number" name="unit1_to_unit3" class="form-control" placeholder="مثال: 100">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="unit3_sell_price" class="form-control" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">وحدة البيع الافتراضية</label>
                                            <select name="default_sale_unit" class="form-select">
                                                <option value="1">الوحدة الكبرى</option>
                                                <option value="2">الوحدة الوسطى</option>
                                                <option value="3">الوحدة الصغرى</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pricing Tab -->
                            <div class="tab-pane fade" id="pricing" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سعر التكلفة</label>
                                            <input type="number" name="cost_price" class="form-control" step="0.01" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سعر البيع (الكبرى)</label>
                                            <input type="number" name="sell_price" class="form-control" step="0.01" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نسبة الربح</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="profitPercent" readonly>
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نسبة الخصم العامة</label>
                                            <div class="input-group">
                                                <input type="number" name="max_discount" class="form-control" step="0.01" placeholder="0.00">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">ضريبة القيمة المضافة</label>
                                            <div class="input-group">
                                                <input type="number" name="vat_percent" class="form-control" step="0.01" value="14">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-check">
                                            <input type="checkbox" name="allow_discount" value="1" class="form-check-input" id="allow_discount" checked>
                                            <label class="form-check-label" for="allow_discount">مسموح بالخصم</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stock Tab -->
                            <div class="tab-pane fade" id="stock" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">الحد الأقصى للمخزون</label>
                                            <input type="number" name="max_stock" class="form-control" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">الحد الأدنى للمخزون</label>
                                            <input type="number" name="min_stock" class="form-control" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">حد الطلب (Reorder Point)</label>
                                            <input type="number" name="reorder_point" class="form-control" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <h5 class="mt-3"><i class="bi bi-geo-alt"></i> مواقع التخزين</h5>
                                        <div id="locationsContainer">
                                            <div class="location-row row mb-2">
                                                <div class="col-md-3">
                                                    <input type="text" name="locations[]" class="form-control" placeholder="كود الموقع">
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" name="location_names[]" class="form-control" placeholder="اسم الموقع">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="text" name="shelf_numbers[]" class="form-control" placeholder="رف">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="text" name="row_numbers[]" class="form-control" placeholder="صف">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-success btn-sm add-location">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Suppliers Tab -->
                            <div class="tab-pane fade" id="suppliers" role="tabpanel">
                                <div id="suppliersContainer">
                                    <div class="supplier-row row mb-3 border p-3 rounded">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">المورد</label>
                                                <select name="supplier_ids[]" class="form-select">
                                                    <option value="">-- اختر المورد --</option>
                                                    <?php foreach ($suppliers as $sup): ?>
                                                        <option value="<?= $sup['id'] ?>"><?= $sup['supplier_name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">سعر الشراء</label>
                                                <input type="number" name="supplier_prices[]" class="form-control" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="supplier_sell_prices[]" class="form-control" step="0.01" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">خصم %</label>
                                                <input type="number" name="supplier_discounts[]" class="form-control" step="0.01" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="mb-3">
                                                <label class="form-label">ضريبة %</label>
                                                <input type="number" name="supplier_vats[]" class="form-control" step="0.01" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="button" class="btn btn-success btn-sm w-100 add-supplier">
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label class="form-label">ملاحظات</label>
                                                <input type="text" name="supplier_notes[]" class="form-control" placeholder="ملاحظات...">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Alerts Tab -->
                            <div class="tab-pane fade" id="alerts" role="tabpanel">
                                <div id="alertsContainer">
                                    <div class="alert-row row mb-2">
                                        <div class="col-md-4">
                                            <select name="alerts[]" class="form-select">
                                                <option value="">-- اختر نوع التحذير --</option>
                                                <option value="HIGH_ALERT">HIGH ALERT</option>
                                                <option value="TOXIC">TOXIC</option>
                                                <option value="SOUNDALIKE">SOUND ALIKE</option>
                                                <option value="LOOKALIKE">LOOK ALIKE</option>
                                                <option value="CONTRAINDICATION">CONTRAINDICATION</option>
                                                <option value="PREGNANCY">PREGNANCY</option>
                                                <option value="OTHER">أخرى</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" name="alert_messages[]" class="form-control" placeholder="رسالة التحذير...">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-success btn-sm add-alert">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ الصنف
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
    document.addEventListener('DOMContentLoaded', function() {
        // Add barcode row
        document.querySelectorAll('.add-barcode').forEach(btn => {
            btn.addEventListener('click', function() {
                var container = document.getElementById('barcodesContainer');
                var newRow = document.createElement('div');
                newRow.className = 'barcode-row row mb-2';
                newRow.innerHTML = `
                    <div class="col-md-4">
                        <input type="text" name="barcodes[]" class="form-control" placeholder="باركود">
                    </div>
                    <div class="col-md-3">
                        <select name="barcode_units[]" class="form-select">
                            <option value="1">الوحدة الكبرى</option>
                            <option value="2">الوحدة الوسطى</option>
                            <option value="3">الوحدة الصغرى</option>
                        </select>
                    </div>
                    <div class="col-md-2"></div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-danger btn-sm remove-row">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(newRow);
            });
        });

        // Add location row
        document.querySelectorAll('.add-location').forEach(btn => {
            btn.addEventListener('click', function() {
                var container = document.getElementById('locationsContainer');
                var newRow = document.createElement('div');
                newRow.className = 'location-row row mb-2';
                newRow.innerHTML = `
                    <div class="col-md-3">
                        <input type="text" name="locations[]" class="form-control" placeholder="كود الموقع">
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="location_names[]" class="form-control" placeholder="اسم الموقع">
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="shelf_numbers[]" class="form-control" placeholder="رف">
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="row_numbers[]" class="form-control" placeholder="صف">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm remove-row">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(newRow);
            });
        });

        // Add supplier row
        document.querySelectorAll('.add-supplier').forEach(btn => {
            btn.addEventListener('click', function() {
                var container = document.getElementById('suppliersContainer');
                var newRow = document.createElement('div');
                newRow.className = 'supplier-row row mb-3 border p-3 rounded';
                newRow.innerHTML = `
                    <div class="col-md-3">
                        <select name="supplier_ids[]" class="form-select">
                            <option value="">-- اختر المورد --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= $sup['supplier_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="supplier_prices[]" class="form-control" step="0.01" placeholder="سعر الشراء">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="supplier_sell_prices[]" class="form-control" step="0.01" placeholder="سعر البيع">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="supplier_discounts[]" class="form-control" step="0.01" placeholder="خصم %">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="supplier_vats[]" class="form-control" step="0.01" placeholder="ضريبة %">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm w-100 remove-row">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="col-md-12 mt-2">
                        <input type="text" name="supplier_notes[]" class="form-control" placeholder="ملاحظات...">
                    </div>
                `;
                container.appendChild(newRow);
            });
        });

        // Add alert row
        document.querySelectorAll('.add-alert').forEach(btn => {
            btn.addEventListener('click', function() {
                var container = document.getElementById('alertsContainer');
                var newRow = document.createElement('div');
                newRow.className = 'alert-row row mb-2';
                newRow.innerHTML = `
                    <div class="col-md-4">
                        <select name="alerts[]" class="form-select">
                            <option value="">-- اختر نوع التحذير --</option>
                            <option value="HIGH_ALERT">HIGH ALERT</option>
                            <option value="TOXIC">TOXIC</option>
                            <option value="SOUNDALIKE">SOUND ALIKE</option>
                            <option value="LOOKALIKE">LOOK ALIKE</option>
                            <option value="CONTRAINDICATION">CONTRAINDICATION</option>
                            <option value="PREGNANCY">PREGNANCY</option>
                            <option value="OTHER">أخرى</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="alert_messages[]" class="form-control" placeholder="رسالة التحذير...">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm remove-row">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(newRow);
            });
        });

        // Remove row
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                e.target.closest('.barcode-row, .location-row, .supplier-row, .alert-row').remove();
            }
        });

        // Calculate profit
        function calculateProfit() {
            var cost = parseFloat(document.querySelector('input[name="cost_price"]').value) || 0;
            var sell = parseFloat(document.querySelector('input[name="sell_price"]').value) || 0;
            if (cost > 0) {
                var profit = ((sell - cost) / cost * 100).toFixed(2);
                document.getElementById('profitPercent').value = profit;
            }
        }

        document.querySelector('input[name="cost_price"]').addEventListener('input', calculateProfit);
        document.querySelector('input[name="sell_price"]').addEventListener('input', calculateProfit);
    });
    </script>
</body>
</html>