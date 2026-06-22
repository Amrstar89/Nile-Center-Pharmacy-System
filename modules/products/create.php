<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إضافة صنف جديد';

// Helper functions for retaining form data
function old($field, $default = '') {
    return isset($_POST[$field]) ? htmlspecialchars($_POST[$field]) : $default;
}

function oldCheck($field) {
    return isset($_POST[$field]) ? 'checked' : '';
}

function oldSelect($field, $value) {
    return isset($_POST[$field]) && $_POST[$field] == $value ? 'selected' : '';
}

// Get lookup data
$categories = $db->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY category_name_ar")->fetchAll();
$companies = $db->query("SELECT * FROM product_companies WHERE is_active = 1 ORDER BY company_name_ar")->fetchAll();
$units = $db->query("SELECT * FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();
$product_types = $db->query("SELECT * FROM product_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // ===== VALIDATION BEFORE INSERT =====
        $errors = [];
        
        // Check product_name (Arabic)
        $product_name = trim($_POST['product_name'] ?? '');
        if (empty($product_name)) {
            $errors[] = 'اسم الصنف العربي مطلوب';
        } else {
            $stmt = $db->prepare("SELECT id FROM products WHERE product_name = ? AND is_active = 1");
            $stmt->execute([$product_name]);
            if ($stmt->fetch()) {
                $errors[] = 'اسم الصنف العربي مستخدم بالفعل!';
            }
        }
        
        // Check product_name_en (English) - if not empty
        $product_name_en = trim($_POST['product_name_en'] ?? '');
        if (!empty($product_name_en)) {
            $stmt = $db->prepare("SELECT id FROM products WHERE product_name_en = ? AND is_active = 1");
            $stmt->execute([$product_name_en]);
            if ($stmt->fetch()) {
                $errors[] = 'اسم الصنف الإنجليزي مستخدم بالفعل!';
            }
        }
        
        // Check barcodes
        if (!empty($_POST['barcodes'])) {
            foreach ($_POST['barcodes'] as $barcode) {
                if (!empty($barcode)) {
                    $stmt = $db->prepare("SELECT pb.id FROM product_barcodes pb 
                                         JOIN products p ON pb.product_id = p.id 
                                         WHERE pb.barcode = ? AND p.is_active = 1");
                    $stmt->execute([$barcode]);
                    if ($stmt->fetch()) {
                        $errors[] = "الباركود '{$barcode}' مستخدم بالفعل!";
                    }
                }
            }
        }
        
        // Check QR code
        $qr_code = trim($_POST['qr_code'] ?? '');
        if (!empty($qr_code)) {
            $stmt = $db->prepare("SELECT pb.id FROM product_barcodes pb 
                                 JOIN products p ON pb.product_id = p.id 
                                 WHERE pb.barcode = ? AND p.is_active = 1");
            $stmt->execute([$qr_code]);
            if ($stmt->fetch()) {
                $errors[] = 'QR Code مستخدم بالفعل!';
            }
        }
        
        // If errors found, stop
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        // Generate product code
        $prefix = 'PRD';
        $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(product_code, 4) AS UNSIGNED)) as max_num FROM products WHERE product_code LIKE 'PRD%'");
        $max_num = $stmt->fetch()['max_num'] ?? 0;
        $product_code = $prefix . str_pad($max_num + 1, 5, '0', STR_PAD_LEFT);

        // Get prices from form
        $sell_price = floatval($_POST['sell_price'] ?? 0);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $profit_margin = floatval($_POST['profit_margin'] ?? 0);
        $vat_percent = floatval($_POST['vat_percent'] ?? 0);

        // Calculate unit prices
        $unit2_sell_price = 0;
        $unit3_sell_price = 0;

        $unit1_to_unit2 = intval($_POST['unit1_to_unit2'] ?? 1);
        $unit1_to_unit3 = intval($_POST['unit1_to_unit3'] ?? 1);

        if ($unit1_to_unit2 > 0) {
            $unit2_sell_price = $sell_price / $unit1_to_unit2;
        }
        if ($unit1_to_unit3 > 0) {
            $unit3_sell_price = $sell_price / $unit1_to_unit3;
        }

        // Insert product
        $sql = "INSERT INTO products SET
            product_code = :product_code,
            product_name = :product_name,
            product_name_en = :product_name_en,
            scientific_name = :scientific_name,
            product_type_id = :product_type_id,
            is_service = :is_service,
            hide_in_receipt = :hide_in_receipt,
            is_shortage = :is_shortage,
            max_stock = :max_stock,
            min_stock = :min_stock,
            reorder_point = :reorder_point,
            category_id = :category_id,
            company_id = :company_id,
            group_id = :group_id,
            sell_price = :sell_price,
            cost_price = :cost_price,
            unit2_sell_price = :unit2_sell_price,
            unit3_sell_price = :unit3_sell_price,
            unit1_id = :unit1_id,
            unit2_id = :unit2_id,
            unit3_id = :unit3_id,
            unit1_to_unit2 = :unit1_to_unit2,
            unit1_to_unit3 = :unit1_to_unit3,
            default_sale_unit = :default_sale_unit,
            has_expire = :has_expire,
            is_imported = :is_imported,
            can_be_negative = :can_be_negative,
            is_made = :is_made,
            print_barcode = :print_barcode,
            barcode_type = :barcode_type,
            print_internal_barcode = :print_internal_barcode,
            allow_discount = :allow_discount,
            max_discount = :max_discount,
            notes = :notes,
            source = :source,
            manual_code = :manual_code";

        $stmt = $db->prepare($sql);

        $stmt->execute([
            ':product_code' => $product_code,
            ':product_name' => $product_name,
            ':product_name_en' => !empty($product_name_en) ? $product_name_en : null,
            ':scientific_name' => !empty($_POST['scientific_name']) ? $_POST['scientific_name'] : null,
            ':product_type_id' => intval($_POST['product_type_id'] ?? 1),
            ':is_service' => isset($_POST['is_service']) ? 1 : 0,
            ':hide_in_receipt' => isset($_POST['hide_in_receipt']) ? 1 : 0,
            ':is_shortage' => isset($_POST['is_shortage']) ? 1 : 0,
            ':max_stock' => floatval($_POST['max_stock'] ?? 0),
            ':min_stock' => floatval($_POST['min_stock'] ?? 0),
            ':reorder_point' => floatval($_POST['reorder_point'] ?? 0),
            ':category_id' => !empty($_POST['category_id']) ? intval($_POST['category_id']) : null,
            ':company_id' => !empty($_POST['company_id']) ? intval($_POST['company_id']) : null,
            ':group_id' => !empty($_POST['group_id']) ? intval($_POST['group_id']) : null,
            ':sell_price' => $sell_price,
            ':cost_price' => $cost_price,
            ':unit2_sell_price' => $unit2_sell_price,
            ':unit3_sell_price' => $unit3_sell_price,
            ':unit1_id' => !empty($_POST['unit1_id']) ? intval($_POST['unit1_id']) : null,
            ':unit2_id' => !empty($_POST['unit2_id']) ? intval($_POST['unit2_id']) : null,
            ':unit3_id' => !empty($_POST['unit3_id']) ? intval($_POST['unit3_id']) : null,
            ':unit1_to_unit2' => $unit1_to_unit2,
            ':unit1_to_unit3' => $unit1_to_unit3,
            ':default_sale_unit' => intval($_POST['default_sale_unit'] ?? 1),
            ':has_expire' => isset($_POST['has_expire']) ? 1 : 0,
            ':is_imported' => intval($_POST['is_imported'] ?? 0),
            ':can_be_negative' => isset($_POST['can_be_negative']) ? 1 : 0,
            ':is_made' => isset($_POST['is_made']) ? 1 : 0,
            ':print_barcode' => isset($_POST['print_barcode']) ? 1 : 0,
            ':barcode_type' => $_POST['barcode_type'] ?? null,
            ':print_internal_barcode' => isset($_POST['print_internal_barcode']) ? 1 : 0,
            ':allow_discount' => isset($_POST['allow_discount']) ? 1 : 0,
            ':max_discount' => floatval($_POST['max_discount'] ?? 0),
            ':notes' => $_POST['notes'] ?? null,
            ':source' => 'manual',
            ':manual_code' => 'M-' . str_pad($max_num + 1, 5, '0', STR_PAD_LEFT)
        ]);

        $product_id = $db->lastInsertId();

        // Fallback if lastInsertId returns 0
        if ($product_id == 0 || $product_id == false) {
            $stmt = $db->query("SELECT MAX(id) as max_id FROM products");
            $product_id = $stmt->fetch()['max_id'];
        }

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

        // Insert QR code
        if (!empty($qr_code)) {
            $qr_stmt = $db->prepare("INSERT INTO product_barcodes (product_id, barcode, unit_id, is_primary) VALUES (?, ?, ?, ?)");
            $qr_stmt->execute([
                $product_id,
                $qr_code,
                1,
                0
            ]);
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
            foreach ($_POST['alerts'] as $alert_type) {
                if (!empty($alert_type)) {
                    $alert_stmt->execute([
                        $product_id,
                        $alert_type,
                        getAlertMessage($alert_type)
                    ]);
                }
            }
        }

        if (!empty($_POST['custom_alert'])) {
            $custom_stmt = $db->prepare("INSERT INTO product_alerts (product_id, alert_type, alert_message) VALUES (?, ?, ?)");
            $custom_stmt->execute([
                $product_id,
                'OTHER',
                $_POST['custom_alert']
            ]);
        }

        $db->commit();

        if ($product_id > 0) {
            header("Location: view.php?id=" . $product_id);
        } else {
            header("Location: index.php?error=insert_failed");
        }
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

function getAlertMessage($type) {
    $messages = [
        'HIGH_ALERT' => 'HIGH ALERT - يتطلب انتباه خاص',
        'TOXIC' => 'TOXIC - سام/خطير',
        'SOUNDALIKE' => 'SOUND ALIKE - يشبه أصناف أخرى بالاسم',
        'LOOKALIKE' => 'LOOK ALIKE - يشبه أصناف أخرى بالشكل',
        'CONTRAINDICATION' => 'CONTRAINDICATION - موانع استخدام',
        'PREGNANCY' => 'PREGNANCY - حذر خلال الحمل',
        'OTHER' => 'تحذير آخر'
    ];
    return $messages[$type] ?? $type;
}

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
        .price-sync {
            font-size: 12px;
            color: #666;
        }
        .alert-select {
            margin-bottom: 10px;
        }
        .validation-feedback {
            font-size: 13px;
            margin-top: 5px;
            display: none;
        }
        .validation-feedback.valid {
            color: var(--success);
            display: block;
        }
        .validation-feedback.invalid {
            color: var(--danger);
            display: block;
        }
        .form-control.is-valid {
            border-color: var(--success);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: left calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .form-control.is-invalid {
            border-color: var(--danger);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: left calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
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
                                <a class="nav-link" id="pricing-tab" data-bs-toggle="tab" href="#pricing" role="tab">
                                    <i class="bi bi-tag"></i> التسعير والوحدات
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="stock-tab" data-bs-toggle="tab" href="#stock" role="tab">
                                    <i class="bi bi-warehouse"></i> المخزون
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
                                            <input type="text" name="product_name" id="product_name" class="form-control" required 
                                                   value="<?= old('product_name') ?>" placeholder="اسم الصنف بالعربي">
                                            <div class="validation-feedback" id="product_name_feedback"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم الصنف بالإنجليزي</label>
                                            <input type="text" name="product_name_en" id="product_name_en" class="form-control" 
                                                   value="<?= old('product_name_en') ?>" placeholder="Product Name in English">
                                            <div class="validation-feedback" id="product_name_en_feedback"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">المادة الفعالة (الاسم العلمي)</label>
                                            <input type="text" name="scientific_name" class="form-control" 
                                                   value="<?= old('scientific_name') ?>" placeholder="Active Ingredient">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نوع الصنف <span class="text-danger">*</span></label>
                                            <select name="product_type_id" class="form-select" required>
                                                <?php foreach ($product_types as $type): ?>
                                                    <option value="<?= $type['id'] ?>" <?= oldSelect('product_type_id', $type['id']) ?>><?= $type['type_name_ar'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

<div class="row">
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">القسم</label>
            <select name="category_id" class="form-select">
                <option value="">-- اختر القسم --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= oldSelect('category_id', $cat['id']) ?>><?= $cat['category_name_ar'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">الشركة المنتجة</label>
            <select name="company_id" class="form-select">
                <option value="">-- اختر الشركة --</option>
                <?php foreach ($companies as $comp): ?>
                    <option value="<?= $comp['id'] ?>" <?= oldSelect('company_id', $comp['id']) ?>><?= $comp['company_name_ar'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">المصدر <span class="text-danger">*</span></label>
            <select name="is_imported" class="form-select" required>
                <option value="0" <?= oldSelect('is_imported', '0') ?>>محلي</option>
                <option value="1" <?= oldSelect('is_imported', '1') ?>>مستورد</option>
            </select>
        </div>
    </div>
</div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">ملاحظات</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات عامة..."><?= old('notes') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_service" value="1" class="form-check-input" id="is_service" <?= oldCheck('is_service') ?>>
                                            <label class="form-check-label" for="is_service">صنف خدمي</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="hide_in_receipt" value="1" class="form-check-input" id="hide_in_receipt" <?= oldCheck('hide_in_receipt') ?>>
                                            <label class="form-check-label" for="hide_in_receipt">إخفاء في الريسيت</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_shortage" value="1" class="form-check-input" id="is_shortage" <?= oldCheck('is_shortage') ?>>
                                            <label class="form-check-label" for="is_shortage">نواقص عامة</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="has_expire" value="1" class="form-check-input" id="has_expire" <?= oldCheck('has_expire') ?>>
                                            <label class="form-check-label" for="has_expire">له تاريخ صلاحية</label>
                                        </div>
                                    </div>
                                </div>

<div class="row mt-3">
    <div class="col-md-3">
        <div class="form-check">
            <input type="checkbox" name="can_be_negative" value="1" class="form-check-input" id="can_be_negative" <?= oldCheck('can_be_negative') ?>>
            <label class="form-check-label" for="can_be_negative">يسمح بالسالب</label>
        </div>
    </div>
</div>
</div> <!-- إقفال تاب البيانات الأساسية -->
                            <!-- Barcodes Tab -->
                            <div class="tab-pane fade" id="barcodes" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-upc"></i> الباركود الدولي</h6>
                                        <div id="barcodesContainer">
                                            <div class="barcode-row row mb-2">
                                                <div class="col-md-8">
                                                    <input type="text" name="barcodes[]" class="form-control barcode-input" placeholder="باركود دولي 1">
                                                </div>
                                                <div class="col-md-4">
                                                    <select name="barcode_units[]" class="form-select">
                                                        <option value="1">الوحدة الكبرى</option>
                                                        <option value="2">الوحدة الوسطى</option>
                                                        <option value="3">الوحدة الصغرى</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-success btn-sm add-barcode">
                                            <i class="bi bi-plus-lg"></i> إضافة باركود
                                        </button>
                                        <div class="validation-feedback" id="barcodes_feedback"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-qr-code"></i> QR Code</h6>
                                        <div class="mb-3">
                                            <input type="text" name="qr_code" id="qr_code" class="form-control" placeholder="QR Code">
                                            <div class="validation-feedback" id="qr_code_feedback"></div>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="print_barcode" value="1" class="form-check-input" id="print_barcode" <?= oldCheck('print_barcode') ?>>
                                            <label class="form-check-label" for="print_barcode">طباعة باركود داخلي</label>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input type="checkbox" name="print_internal_barcode" value="1" class="form-check-input" id="print_internal_barcode" <?= oldCheck('print_internal_barcode') ?>>
                                            <label class="form-check-label" for="print_internal_barcode">طباعة باركود داخلي</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pricing & Units Tab -->
                            <div class="tab-pane fade" id="pricing" role="tabpanel">
                                <!-- Main Pricing -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">سعر البيع (الكبرى) <span class="text-danger">*</span></label>
                                            <input type="number" name="sell_price" id="sell_price" class="form-control" step="0.01" placeholder="0.00" required
                                                   value="<?= old('sell_price') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">نسبة الخصم (الربح) %</label>
                                            <div class="input-group">
                                                <input type="number" name="profit_margin" id="profit_margin" class="form-control" step="0.01" placeholder="0.00"
                                                       value="<?= old('profit_margin') ?>">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">نسبة الخصم من سعر البيع</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">سعر الشراء (يدوي)</label>
                                            <div class="input-group">
                                                <input type="number" name="cost_price" id="cost_price" class="form-control" step="0.01" placeholder="0.00"
                                                       value="<?= old('cost_price') ?>">
                                                <span class="input-group-text">ج</span>
                                            </div>
                                            <small class="text-muted">اتركه فارغاً لحسابه تلقائياً</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">ضريبة القيمة المضافة</label>
                                            <div class="input-group">
                                                <input type="number" name="vat_percent" id="vat_percent" class="form-control" step="0.01" value="0"
                                                       value="<?= old('vat_percent', '0') ?>">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">0% افتراضي</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">قيمة صافي الربح</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="net_profit_value" readonly style="background: #e9ecef;">
                                                <span class="input-group-text">ج</span>
                                            </div>
                                            <small class="text-muted">سعر البيع - سعر الشراء</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">قيمة الضريبة</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="vat_value" readonly style="background: #e9ecef;">
                                                <span class="input-group-text">ج</span>
                                            </div>
                                            <small class="text-muted">صافي الربح × نسبة الضريبة</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">صافي الربح بعد الضريبة</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="net_profit_after_vat" readonly style="background: #e9ecef;">
                                                <span class="input-group-text">ج</span>
                                            </div>
                                            <small class="text-muted">صافي الربح - قيمة الضريبة</small>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <!-- Units with Auto Pricing -->
                                <h6 class="mb-3"><i class="bi bi-balance-scale"></i> الوحدات والتسعير</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="unit-card">
                                            <h6><i class="bi bi-box"></i> الوحدة الكبرى</h6>
                                            <div class="mb-3">
                                                <label class="form-label">نوع الوحدة</label>
                                                <select name="unit1_id" class="form-select">
                                                    <option value="">-- اختر --</option>
                                                    <?php foreach ($units as $unit): ?>
                                                        <option value="<?= $unit['id'] ?>" <?= oldSelect('unit1_id', $unit['id']) ?>><?= $unit['unit_name_ar'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="unit1_display_price" id="unit1_display_price" class="form-control" step="0.01" readonly style="background: #e9ecef;">
                                                <small class="price-sync">يسمع تلقائي من سعر البيع</small>
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
                                                        <option value="<?= $unit['id'] ?>" <?= oldSelect('unit2_id', $unit['id']) ?>><?= $unit['unit_name_ar'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">عدد الوحدات</label>
                                                <input type="number" name="unit1_to_unit2" id="unit1_to_unit2" class="form-control" placeholder="1"
                                                       value="<?= old('unit1_to_unit2', '1') ?>">
                                                <small class="text-muted">الوحدة الكبرى = كم وحدة وسطى؟ (افتراضي: 1)</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="unit2_sell_price" id="unit2_sell_price" class="form-control" step="0.01" placeholder="0.00">
                                                <small class="price-sync">يتم حسابه تلقائي</small>
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
                                                        <option value="<?= $unit['id'] ?>" <?= oldSelect('unit3_id', $unit['id']) ?>><?= $unit['unit_name_ar'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">عدد الوحدات</label>
                                                <input type="number" name="unit1_to_unit3" id="unit1_to_unit3" class="form-control" placeholder="1"
                                                       value="<?= old('unit1_to_unit3', '1') ?>">
                                                <small class="text-muted">الوحدة الكبرى = كم وحدة صغرى؟ (افتراضي: 1)</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="unit3_sell_price" id="unit3_sell_price" class="form-control" step="0.01" placeholder="0.00">
                                                <small class="price-sync">يتم حسابه تلقائي</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">وحدة البيع الافتراضية</label>
                                            <select name="default_sale_unit" class="form-select">
                                                <option value="1" <?= oldSelect('default_sale_unit', '1') ?>>الوحدة الكبرى</option>
                                                <option value="2" <?= oldSelect('default_sale_unit', '2') ?>>الوحدة الوسطى</option>
                                                <option value="3" <?= oldSelect('default_sale_unit', '3') ?>>الوحدة الصغرى</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Discount Settings -->
                                <hr>
                                <h6 class="mb-3"><i class="bi bi-percent"></i> إعدادات الخصم</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check mb-3">
                                            <input type="checkbox" name="allow_discount" value="1" class="form-check-input" id="allow_discount" checked>
                                            <label class="form-check-label" for="allow_discount">مسموح بالخصم في فاتورة البيع</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">أقصى نسبة خصم مسموحة</label>
                                            <div class="input-group">
                                                <input type="number" name="max_discount" id="max_discount" class="form-control" step="0.01" placeholder="0.00"
                                                       value="<?= old('max_discount') ?>">
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">النسبة القصوى للخصم على هذا الصنف</small>
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
                                            <input type="number" name="max_stock" class="form-control" placeholder="0"
                                                   value="<?= old('max_stock') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">الحد الأدنى للمخزون</label>
                                            <input type="number" name="min_stock" class="form-control" placeholder="0"
                                                   value="<?= old('min_stock') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">حد الطلب (Reorder Point)</label>
                                            <input type="number" name="reorder_point" class="form-control" placeholder="0"
                                                   value="<?= old('reorder_point') ?>">
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

                            <!-- Alerts Tab -->
                            <div class="tab-pane fade" id="alerts" role="tabpanel">
                                <h6 class="mb-3">اختر التحذيرات المناسبة:</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="alert-select">
                                            <div class="form-check">
                                                <input type="checkbox" name="alerts[]" value="HIGH_ALERT" class="form-check-input" id="alert_high">
                                                <label class="form-check-label" for="alert_high">
                                                    <span class="badge bg-danger">HIGH ALERT</span> - يتطلب انتباه خاص
                                                </label>
                                            </div>
                                        </div>
                                        <div class="alert-select">
                                            <div class="form-check">
                                                <input type="checkbox" name="alerts[]" value="TOXIC" class="form-check-input" id="alert_toxic">
                                                <label class="form-check-label" for="alert_toxic">
                                                    <span class="badge bg-danger">TOXIC</span> - سام/خطير
                                                </label>
                                            </div>
                                        </div>
                                        <div class="alert-select">
                                            <div class="form-check">
                                                <input type="checkbox" name="alerts[]" value="SOUNDALIKE" class="form-check-input" id="alert_sound">
                                                <label class="form-check-label" for="alert_sound">
                                                    <span class="badge bg-warning">SOUND ALIKE</span> - يشبه أصناف أخرى بالاسم
                                                </label>
                                            </div>
                                        </div>
                                        <div class="alert-select">
                                            <div class="form-check">
                                                <input type="checkbox" name="alerts[]" value="LOOKALIKE" class="form-check-input" id="alert_look">
                                                <label class="form-check-label" for="alert_look">
                                                    <span class="badge bg-warning">LOOK ALIKE</span> - يشبه أصناف أخرى بالشكل
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="alert-select">
                                            <div class="form-check">
                                                <input type="checkbox" name="alerts[]" value="CONTRAINDICATION" class="form-check-input" id="alert_contra">
                                                <label class="form-check-label" for="alert_contra">
                                                    <span class="badge bg-info">CONTRAINDICATION</span> - موانع استخدام
                                                </label>
                                            </div>
                                        </div>
                                        <div class="alert-select">
                                            <div class="form-check">
                                                <input type="checkbox" name="alerts[]" value="PREGNANCY" class="form-check-input" id="alert_preg">
                                                <label class="form-check-label" for="alert_preg">
                                                    <span class="badge bg-info">PREGNANCY</span> - حذر خلال الحمل
                                                </label>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="mb-3">
                                            <label class="form-label">تحذير مخصص (اختياري)</label>
                                            <input type="text" name="custom_alert" class="form-control" placeholder="اكتب تحذير مخصص..."
                                                   value="<?= old('custom_alert') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
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
        // ===== PRICE CALCULATION (FIXED) =====
        const sellPriceInput = document.getElementById('sell_price');
        const profitMarginInput = document.getElementById('profit_margin');
        const costPriceInput = document.getElementById('cost_price');
        const vatPercentInput = document.getElementById('vat_percent');
        const netProfitValue = document.getElementById('net_profit_value');
        const vatValue = document.getElementById('vat_value');
        const netProfitAfterVat = document.getElementById('net_profit_after_vat');
        const unit1DisplayPrice = document.getElementById('unit1_display_price');
        const unit2SellPrice = document.getElementById('unit2_sell_price');
        const unit3SellPrice = document.getElementById('unit3_sell_price');
        const unit1ToUnit2 = document.getElementById('unit1_to_unit2');
        const unit1ToUnit3 = document.getElementById('unit1_to_unit3');

        let lastEditedField = null;

        function updatePrices() {
            const sellPrice = parseFloat(sellPriceInput.value) || 0;
            let profitMargin = parseFloat(profitMarginInput.value) || 0;
            let costPrice = parseFloat(costPriceInput.value) || 0;
            const vatPercent = parseFloat(vatPercentInput.value) || 0;

            // If sell_price is set and profit_margin is set, calculate cost_price
            if (sellPrice > 0 && profitMargin > 0 && (lastEditedField === 'profit_margin' || lastEditedField === 'sell_price')) {
                costPrice = sellPrice * (1 - (profitMargin / 100));
                costPriceInput.value = costPrice.toFixed(2);
            }

            // If sell_price is set and cost_price is set, calculate profit_margin
            if (sellPrice > 0 && costPrice > 0 && (lastEditedField === 'cost_price' || lastEditedField === 'sell_price')) {
                profitMargin = ((sellPrice - costPrice) / sellPrice) * 100;
                profitMarginInput.value = profitMargin.toFixed(2);
            }

            // Recalculate with updated values
            costPrice = parseFloat(costPriceInput.value) || 0;
            profitMargin = parseFloat(profitMarginInput.value) || 0;

            // Calculate net profit
            const netProfit = sellPrice - costPrice;
            netProfitValue.value = netProfit.toFixed(2);

            // Calculate VAT
            const vatAmount = netProfit * (vatPercent / 100);
            vatValue.value = vatAmount.toFixed(2);

            // Calculate net profit after VAT
            const netProfitAfterVatValue = netProfit - vatAmount;
            netProfitAfterVat.value = netProfitAfterVatValue.toFixed(2);

            // Update unit 1 display price
            unit1DisplayPrice.value = sellPrice.toFixed(2);

            // Calculate unit 2 price
            const unit2Qty = parseInt(unit1ToUnit2.value) || 0;
            if (unit2Qty > 0) {
                unit2SellPrice.value = (sellPrice / unit2Qty).toFixed(2);
            } else {
                unit2SellPrice.value = sellPrice.toFixed(2);
            }

            // Calculate unit 3 price
            const unit3Qty = parseInt(unit1ToUnit3.value) || 0;
            if (unit3Qty > 0) {
                unit3SellPrice.value = (sellPrice / unit3Qty).toFixed(2);
            } else {
                unit3SellPrice.value = sellPrice.toFixed(2);
            }
        }

        // Track which field was last edited
        sellPriceInput.addEventListener('input', function() { lastEditedField = 'sell_price'; updatePrices(); });
        profitMarginInput.addEventListener('input', function() { lastEditedField = 'profit_margin'; updatePrices(); });
        costPriceInput.addEventListener('input', function() { lastEditedField = 'cost_price'; updatePrices(); });
        vatPercentInput.addEventListener('input', function() { lastEditedField = 'vat_percent'; updatePrices(); });
        unit1ToUnit2.addEventListener('input', updatePrices);
        unit1ToUnit3.addEventListener('input', updatePrices);

        // ===== REAL-TIME VALIDATION (AJAX) =====
        const validationTimeout = {};
        
        function checkDuplicate(field, value, feedbackId) {
            const feedback = document.getElementById(feedbackId);
            const input = document.getElementById(field);
            
            if (!value || value.trim() === '') {
                input.classList.remove('is-valid', 'is-invalid');
                feedback.style.display = 'none';
                return;
            }

            if (validationTimeout[field]) {
                clearTimeout(validationTimeout[field]);
            }

            feedback.innerHTML = '<i class="bi bi-hourglass-split"></i> جاري الفحص...';
            feedback.className = 'validation-feedback';
            feedback.style.display = 'block';

            validationTimeout[field] = setTimeout(() => {
                fetch(`../../api/check-product.php?field=${field}&value=${encodeURIComponent(value)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                            feedback.innerHTML = '<i class="bi bi-x-circle"></i> ' + data.message;
                            feedback.className = 'validation-feedback invalid';
                        } else {
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                            feedback.innerHTML = '<i class="bi bi-check-circle"></i> متاح';
                            feedback.className = 'validation-feedback valid';
                        }
                    })
                    .catch(err => {
                        feedback.style.display = 'none';
                        input.classList.remove('is-valid', 'is-invalid');
                    });
            }, 500);
        }

        // Product name validation
        document.getElementById('product_name').addEventListener('blur', function() {
            checkDuplicate('product_name', this.value, 'product_name_feedback');
        });

        // Product name EN validation
        document.getElementById('product_name_en').addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                checkDuplicate('product_name_en', this.value, 'product_name_en_feedback');
            }
        });

        // Barcode validation
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('barcode-input')) {
                const barcodeValue = e.target.value;
                if (barcodeValue.length > 3) {
                    checkDuplicate('barcode', barcodeValue, 'barcodes_feedback');
                }
            }
        });

        // QR Code validation
        document.getElementById('qr_code').addEventListener('blur', function() {
            if (this.value.trim() !== '') {
                checkDuplicate('qr_code', this.value, 'qr_code_feedback');
            }
        });

        // ===== FORM SUBMISSION VALIDATION =====
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const invalidFields = document.querySelectorAll('.is-invalid');
            if (invalidFields.length > 0) {
                e.preventDefault();
                alert('يوجد حقول تحتوي على قيم مكررة. يرجى تصحيحها قبل الحفظ.');
                invalidFields[0].focus();
            }
        });

        // ===== ADD BARCODE ROW =====
        document.querySelector('.add-barcode').addEventListener('click', function() {
            var container = document.getElementById('barcodesContainer');
            var newRow = document.createElement('div');
            newRow.className = 'barcode-row row mb-2';
            newRow.innerHTML = `
                <div class="col-md-8">
                    <input type="text" name="barcodes[]" class="form-control barcode-input" placeholder="باركود">
                </div>
                <div class="col-md-4">
                    <select name="barcode_units[]" class="form-select">
                        <option value="1">الوحدة الكبرى</option>
                        <option value="2">الوحدة الوسطى</option>
                        <option value="3">الوحدة الصغرى</option>
                    </select>
                </div>
            `;
            container.appendChild(newRow);
        });

        // ===== ADD LOCATION ROW =====
        document.querySelector('.add-location').addEventListener('click', function() {
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

        // Remove row
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-row')) {
                e.target.closest('.barcode-row, .location-row').remove();
            }
        });

        // Initial calculation
        updatePrices();
    });
    </script>
</body>
</html>