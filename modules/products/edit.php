<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$product_id) {
    header("Location: index.php");
    exit;
}

// Get product data
$stmt = $db->prepare("
    SELECT p.*, 
        c.category_name_ar as category_name,
        co.company_name_ar as company_name,
        pt.type_name_ar as product_type_name
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.id
    LEFT JOIN product_companies co ON p.company_id = co.id
    LEFT JOIN product_types pt ON p.product_type_id = pt.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: index.php");
    exit;
}

// Get lookup data
$categories = $db->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY category_name_ar")->fetchAll();
$companies = $db->query("SELECT * FROM product_companies WHERE is_active = 1 ORDER BY company_name_ar")->fetchAll();
$units = $db->query("SELECT * FROM product_units WHERE is_active = 1 ORDER BY unit_name_ar")->fetchAll();
$product_types = $db->query("SELECT * FROM product_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$suppliers = $db->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY supplier_name")->fetchAll();

// Get existing data
$barcodes = $db->prepare("SELECT * FROM product_barcodes WHERE product_id = ? ORDER BY is_primary DESC");
$barcodes->execute([$product_id]);
$barcodes = $barcodes->fetchAll();

$locations = $db->prepare("SELECT * FROM product_locations WHERE product_id = ? AND is_active = 1");
$locations->execute([$product_id]);
$locations = $locations->fetchAll();

$alerts = $db->prepare("SELECT * FROM product_alerts WHERE product_id = ? AND is_active = 1");
$alerts->execute([$product_id]);
$alerts = $alerts->fetchAll();

$supplier_prices = $db->prepare("
    SELECT sp.*, s.supplier_name, s.supplier_code
    FROM supplier_product_pricing sp
    LEFT JOIN suppliers s ON sp.supplier_id = s.id
    WHERE sp.product_id = ? AND sp.is_active = 1
");
$supplier_prices->execute([$product_id]);
$supplier_prices = $supplier_prices->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Store old prices for history
        $old_sell = $product['sell_price'];
        $old_cost = $product['cost_price'];
        $new_sell = $_POST['sell_price'] ?? $old_sell;
        $new_cost = $_POST['cost_price'] ?? $old_cost;

        // Update product
        $stmt = $db->prepare("
            UPDATE products SET
                product_name = ?, product_name_en = ?, scientific_name = ?,
                product_type_id = ?, is_service = ?, hide_in_receipt = ?, is_shortage = ?,
                max_stock = ?, min_stock = ?, reorder_point = ?,
                category_id = ?, company_id = ?, group_id = ?,
                sell_price = ?, cost_price = ?, unit2_sell_price = ?, unit3_sell_price = ?,
                unit1_id = ?, unit2_id = ?, unit3_id = ?, unit1_to_unit2 = ?, unit1_to_unit3 = ?, default_sale_unit = ?,
                has_expire = ?, is_drug = ?, is_imported = ?, can_be_negative = ?, is_made = ?,
                print_barcode = ?, barcode_type = ?, print_internal_barcode = ?,
                allow_discount = ?, max_discount = ?,
                notes = ?, is_active = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['product_name'] ?? $product['product_name'],
            $_POST['product_name_en'] ?? $product['product_name_en'],
            $_POST['scientific_name'] ?? $product['scientific_name'],
            $_POST['product_type_id'] ?? $product['product_type_id'],
            $_POST['is_service'] ?? 0,
            $_POST['hide_in_receipt'] ?? 0,
            $_POST['is_shortage'] ?? 0,
            $_POST['max_stock'] ?? $product['max_stock'],
            $_POST['min_stock'] ?? $product['min_stock'],
            $_POST['reorder_point'] ?? $product['reorder_point'],
            $_POST['category_id'] ?? $product['category_id'],
            $_POST['company_id'] ?? $product['company_id'],
            $_POST['group_id'] ?? $product['group_id'],
            $new_sell,
            $new_cost,
            $_POST['unit2_sell_price'] ?? $product['unit2_sell_price'],
            $_POST['unit3_sell_price'] ?? $product['unit3_sell_price'],
            $_POST['unit1_id'] ?? $product['unit1_id'],
            $_POST['unit2_id'] ?? $product['unit2_id'],
            $_POST['unit3_id'] ?? $product['unit3_id'],
            $_POST['unit1_to_unit2'] ?? $product['unit1_to_unit2'],
            $_POST['unit1_to_unit3'] ?? $product['unit1_to_unit3'],
            $_POST['default_sale_unit'] ?? $product['default_sale_unit'],
            $_POST['has_expire'] ?? 0,
            $_POST['is_drug'] ?? 0,
            $_POST['is_imported'] ?? 0,
            $_POST['can_be_negative'] ?? 0,
            $_POST['is_made'] ?? 0,
            $_POST['print_barcode'] ?? 0,
            $_POST['barcode_type'] ?? $product['barcode_type'],
            $_POST['print_internal_barcode'] ?? 0,
            $_POST['allow_discount'] ?? 1,
            $_POST['max_discount'] ?? $product['max_discount'],
            $_POST['notes'] ?? $product['notes'],
            $_POST['is_active'] ?? $product['is_active'],
            $product_id
        ]);

        // Log price change if different
        if ($old_sell != $new_sell || $old_cost != $new_cost) {
            $history_stmt = $db->prepare("
                INSERT INTO product_price_history (product_id, old_sell_price, new_sell_price, old_cost_price, new_cost_price, changed_by, change_reason)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $history_stmt->execute([
                $product_id,
                $old_sell,
                $new_sell,
                $old_cost,
                $new_cost,
                $_SESSION['user_id'] ?? null,
                $_POST['price_change_reason'] ?? 'Manual update'
            ]);
        }

        // Update barcodes - delete old and insert new
        $db->prepare("DELETE FROM product_barcodes WHERE product_id = ?")->execute([$product_id]);
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

        // Update locations - soft delete old and insert new
        $db->prepare("UPDATE product_locations SET is_active = 0 WHERE product_id = ?")->execute([$product_id]);
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

        // Update alerts - soft delete old and insert new
        $db->prepare("UPDATE product_alerts SET is_active = 0 WHERE product_id = ?")->execute([$product_id]);
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

        // Update supplier pricing - soft delete old and insert new
        $db->prepare("UPDATE supplier_product_pricing SET is_active = 0 WHERE product_id = ?")->execute([$product_id]);
        if (!empty($_POST['supplier_ids'])) {
            $supplier_stmt = $db->prepare("
                INSERT INTO supplier_product_pricing (product_id, supplier_id, purchase_price, sell_price, discount_percent, vat_percent, is_default, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
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
                        $_POST['supplier_notes'][$index] ?? null,
                        $_SESSION['user_id'] ?? null
                    ]);
                }
            }
        }

        $db->commit();

        header("Location: view.php?id=" . $product_id);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

$page_title = 'تعديل صنف: ' . $product['product_name'];

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
                <h2><i class="bi bi-pencil-square"></i> <?= $page_title ?></h2>
                <div>
                    <a href="view.php?id=<?= $product_id ?>" class="btn btn-info">
                        <i class="bi bi-eye"></i> عرض
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-right"></i> العودة
                    </a>
                </div>
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
                                                   value="<?= htmlspecialchars($product['product_name']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم الصنف بالإنجليزي</label>
                                            <input type="text" name="product_name_en" class="form-control" 
                                                   value="<?= htmlspecialchars($product['product_name_en'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">المادة الفعالة (الاسم العلمي)</label>
                                            <input type="text" name="scientific_name" class="form-control" 
                                                   value="<?= htmlspecialchars($product['scientific_name'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نوع الصنف <span class="text-danger">*</span></label>
                                            <select name="product_type_id" class="form-select" required>
                                                <?php foreach ($product_types as $type): ?>
                                                    <option value="<?= $type['id'] ?>" <?= $product['product_type_id'] == $type['id'] ? 'selected' : '' ?>>
                                                        <?= $type['type_name_ar'] ?>
                                                    </option>
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
                                                    <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                                        <?= $cat['category_name_ar'] ?>
                                                    </option>
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
                                                    <option value="<?= $comp['id'] ?>" <?= $product['company_id'] == $comp['id'] ? 'selected' : '' ?>>
                                                        <?= $comp['company_name_ar'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">ملاحظات</label>
                                            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($product['notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_service" value="1" class="form-check-input" id="is_service" <?= $product['is_service'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_service">صنف خدمي</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="hide_in_receipt" value="1" class="form-check-input" id="hide_in_receipt" <?= $product['hide_in_receipt'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="hide_in_receipt">إخفاء في الريسيت</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_shortage" value="1" class="form-check-input" id="is_shortage" <?= $product['is_shortage'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_shortage">نواقص عامة</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="has_expire" value="1" class="form-check-input" id="has_expire" <?= $product['has_expire'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="has_expire">له تاريخ صلاحية</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" <?= $product['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">نشط</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_imported" value="1" class="form-check-input" id="is_imported" <?= $product['is_imported'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_imported">مستورد</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Barcodes Tab -->
                            <div class="tab-pane fade" id="barcodes" role="tabpanel">
                                <div id="barcodesContainer">
                                    <?php if (empty($barcodes)): ?>
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
                                            <div class="col-md-3">
                                                <button type="button" class="btn btn-success btn-sm add-barcode">
                                                    <i class="bi bi-plus-lg"></i> إضافة
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($barcodes as $index => $barcode): ?>
                                            <div class="barcode-row row mb-2">
                                                <div class="col-md-4">
                                                    <input type="text" name="barcodes[]" class="form-control" value="<?= htmlspecialchars($barcode['barcode']) ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <select name="barcode_units[]" class="form-select">
                                                        <option value="1" <?= $barcode['unit_id'] == 1 ? 'selected' : '' ?>>الوحدة الكبرى</option>
                                                        <option value="2" <?= $barcode['unit_id'] == 2 ? 'selected' : '' ?>>الوحدة الوسطى</option>
                                                        <option value="3" <?= $barcode['unit_id'] == 3 ? 'selected' : '' ?>>الوحدة الصغرى</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <?php if ($barcode['is_primary']): ?>
                                                        <span class="badge bg-primary">رئيسي</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-3">
                                                    <button type="button" class="btn btn-success btn-sm add-barcode">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                    <?php if ($index > 0): ?>
                                                        <button type="button" class="btn btn-danger btn-sm remove-row">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <div class="form-check">
                                            <input type="checkbox" name="print_barcode" value="1" class="form-check-input" id="print_barcode" <?= $product['print_barcode'] ? 'checked' : '' ?>>
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
                                                        <option value="<?= $unit['id'] ?>" <?= $product['unit1_id'] == $unit['id'] ? 'selected' : '' ?>>
                                                            <?= $unit['unit_name_ar'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="sell_price" class="form-control" step="0.01" value="<?= $product['sell_price'] ?>">
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
                                                        <option value="<?= $unit['id'] ?>" <?= $product['unit2_id'] == $unit['id'] ? 'selected' : '' ?>>
                                                            <?= $unit['unit_name_ar'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">عدد الوحدات</label>
                                                <input type="number" name="unit1_to_unit2" class="form-control" value="<?= $product['unit1_to_unit2'] ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="unit2_sell_price" class="form-control" step="0.01" value="<?= $product['unit2_sell_price'] ?>">
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
                                                        <option value="<?= $unit['id'] ?>" <?= $product['unit3_id'] == $unit['id'] ? 'selected' : '' ?>>
                                                            <?= $unit['unit_name_ar'] ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">عدد الوحدات</label>
                                                <input type="number" name="unit1_to_unit3" class="form-control" value="<?= $product['unit1_to_unit3'] ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">سعر البيع</label>
                                                <input type="number" name="unit3_sell_price" class="form-control" step="0.01" value="<?= $product['unit3_sell_price'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">وحدة البيع الافتراضية</label>
                                            <select name="default_sale_unit" class="form-select">
                                                <option value="1" <?= $product['default_sale_unit'] == 1 ? 'selected' : '' ?>>الوحدة الكبرى</option>
                                                <option value="2" <?= $product['default_sale_unit'] == 2 ? 'selected' : '' ?>>الوحدة الوسطى</option>
                                                <option value="3" <?= $product['default_sale_unit'] == 3 ? 'selected' : '' ?>>الوحدة الصغرى</option>
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
                                            <input type="number" name="cost_price" class="form-control" step="0.01" value="<?= $product['cost_price'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">سعر البيع (الكبرى)</label>
                                            <input type="number" name="sell_price" class="form-control" step="0.01" value="<?= $product['sell_price'] ?>">
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
                                                <input type="number" name="max_discount" class="form-control" step="0.01" value="<?= $product['max_discount'] ?>">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">سبب تغيير السعر (اختياري)</label>
                                            <input type="text" name="price_change_reason" class="form-control" placeholder="مثال: تغيير سعر المورد">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-check">
                                            <input type="checkbox" name="allow_discount" value="1" class="form-check-input" id="allow_discount" <?= $product['allow_discount'] ? 'checked' : '' ?>>
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
                                            <input type="number" name="max_stock" class="form-control" value="<?= $product['max_stock'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">الحد الأدنى للمخزون</label>
                                            <input type="number" name="min_stock" class="form-control" value="<?= $product['min_stock'] ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">حد الطلب (Reorder Point)</label>
                                            <input type="number" name="reorder_point" class="form-control" value="<?= $product['reorder_point'] ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <h5 class="mt-3"><i class="bi bi-geo-alt"></i> مواقع التخزين</h5>
                                        <div id="locationsContainer">
                                            <?php if (empty($locations)): ?>
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
                                            <?php else: ?>
                                                <?php foreach ($locations as $index => $location): ?>
                                                    <div class="location-row row mb-2">
                                                        <div class="col-md-3">
                                                            <input type="text" name="locations[]" class="form-control" value="<?= htmlspecialchars($location['location_code']) ?>">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="text" name="location_names[]" class="form-control" value="<?= htmlspecialchars($location['location_name']) ?>">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <input type="text" name="shelf_numbers[]" class="form-control" value="<?= htmlspecialchars($location['shelf_number'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <input type="text" name="row_numbers[]" class="form-control" value="<?= htmlspecialchars($location['row_number'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <button type="button" class="btn btn-success btn-sm add-location">
                                                                <i class="bi bi-plus-lg"></i>
                                                            </button>
                                                            <?php if ($index > 0): ?>
                                                                <button type="button" class="btn btn-danger btn-sm remove-row">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Suppliers Tab -->
                            <div class="tab-pane fade" id="suppliers" role="tabpanel">
                                <div id="suppliersContainer">
                                    <?php if (empty($supplier_prices)): ?>
                                        <div class="supplier-row row mb-3 border p-3 rounded">
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
                                                <button type="button" class="btn btn-success btn-sm w-100 add-supplier">
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </div>
                                            <div class="col-md-12 mt-2">
                                                <input type="text" name="supplier_notes[]" class="form-control" placeholder="ملاحظات...">
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($supplier_prices as $index => $sp): ?>
                                            <div class="supplier-row row mb-3 border p-3 rounded">
                                                <div class="col-md-3">
                                                    <select name="supplier_ids[]" class="form-select">
                                                        <option value="">-- اختر المورد --</option>
                                                        <?php foreach ($suppliers as $sup): ?>
                                                            <option value="<?= $sup['id'] ?>" <?= $sp['supplier_id'] == $sup['id'] ? 'selected' : '' ?>>
                                                                <?= $sup['supplier_name'] ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" name="supplier_prices[]" class="form-control" step="0.01" value="<?= $sp['purchase_price'] ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" name="supplier_sell_prices[]" class="form-control" step="0.01" value="<?= $sp['sell_price'] ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" name="supplier_discounts[]" class="form-control" step="0.01" value="<?= $sp['discount_percent'] ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" name="supplier_vats[]" class="form-control" step="0.01" value="<?= $sp['vat_percent'] ?>">
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-success btn-sm w-100 add-supplier">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                    <?php if ($index > 0): ?>
                                                        <button type="button" class="btn btn-danger btn-sm w-100 remove-row mt-1">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-12 mt-2">
                                                    <input type="text" name="supplier_notes[]" class="form-control" value="<?= htmlspecialchars($sp['notes'] ?? '') ?>">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Alerts Tab -->
                            <div class="tab-pane fade" id="alerts" role="tabpanel">
                                <div id="alertsContainer">
                                    <?php if (empty($alerts)): ?>
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
                                    <?php else: ?>
                                        <?php foreach ($alerts as $index => $alert): ?>
                                            <div class="alert-row row mb-2">
                                                <div class="col-md-4">
                                                    <select name="alerts[]" class="form-select">
                                                        <option value="">-- اختر --</option>
                                                        <option value="HIGH_ALERT" <?= $alert['alert_type'] == 'HIGH_ALERT' ? 'selected' : '' ?>>HIGH ALERT</option>
                                                        <option value="TOXIC" <?= $alert['alert_type'] == 'TOXIC' ? 'selected' : '' ?>>TOXIC</option>
                                                        <option value="SOUNDALIKE" <?= $alert['alert_type'] == 'SOUNDALIKE' ? 'selected' : '' ?>>SOUND ALIKE</option>
                                                        <option value="LOOKALIKE" <?= $alert['alert_type'] == 'LOOKALIKE' ? 'selected' : '' ?>>LOOK ALIKE</option>
                                                        <option value="CONTRAINDICATION" <?= $alert['alert_type'] == 'CONTRAINDICATION' ? 'selected' : '' ?>>CONTRAINDICATION</option>
                                                        <option value="PREGNANCY" <?= $alert['alert_type'] == 'PREGNANCY' ? 'selected' : '' ?>>PREGNANCY</option>
                                                        <option value="OTHER" <?= $alert['alert_type'] == 'OTHER' ? 'selected' : '' ?>>أخرى</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <input type="text" name="alert_messages[]" class="form-control" value="<?= htmlspecialchars($alert['alert_message']) ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-success btn-sm add-alert">
                                                        <i class="bi bi-plus-lg"></i>
                                                    </button>
                                                    <?php if ($index > 0): ?>
                                                        <button type="button" class="btn btn-danger btn-sm remove-row">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ التغييرات
                        </button>
                        <a href="view.php?id=<?= $product_id ?>" class="btn btn-secondary btn-lg">
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
        calculateProfit(); // Initial calculation
    });
    </script>
</body>
</html>