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

// Get product data with all joins
$stmt = $db->prepare("
    SELECT p.*, 
        c.category_name_ar as category_name,
        co.company_name_ar as company_name,
        pt.type_name_ar as product_type_name,
        u1.unit_name_ar as unit1_name,
        u2.unit_name_ar as unit2_name,
        u3.unit_name_ar as unit3_name
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.id
    LEFT JOIN product_companies co ON p.company_id = co.id
    LEFT JOIN product_types pt ON p.product_type_id = pt.id
    LEFT JOIN product_units u1 ON p.unit1_id = u1.id
    LEFT JOIN product_units u2 ON p.unit2_id = u2.id
    LEFT JOIN product_units u3 ON p.unit3_id = u3.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: index.php");
    exit;
}

// Get barcodes
$barcodes = $db->prepare("SELECT * FROM product_barcodes WHERE product_id = ? ORDER BY is_primary DESC");
$barcodes->execute([$product_id]);
$barcodes = $barcodes->fetchAll();

// Get stock batches
$stock = $db->prepare("
    SELECT ib.*, b.branch_name, s.supplier_name
    FROM inventory_batches ib
    LEFT JOIN branches b ON ib.branch_id = b.id
    LEFT JOIN suppliers s ON ib.supplier_id = s.id
    WHERE ib.product_id = ? AND ib.is_active = 1
    ORDER BY ib.exp_date ASC
");
$stock->execute([$product_id]);
$stock_batches = $stock->fetchAll();

// Get locations
$locations = $db->prepare("SELECT * FROM product_locations WHERE product_id = ? AND is_active = 1");
$locations->execute([$product_id]);
$locations = $locations->fetchAll();

// Get alerts
$alerts = $db->prepare("SELECT * FROM product_alerts WHERE product_id = ? AND is_active = 1");
$alerts->execute([$product_id]);
$alerts = $alerts->fetchAll();

// Get supplier pricing
$supplier_prices = $db->prepare("
    SELECT sp.*, s.supplier_name, s.supplier_code
    FROM supplier_product_pricing sp
    LEFT JOIN suppliers s ON sp.supplier_id = s.id
    WHERE sp.product_id = ? AND sp.is_active = 1
    ORDER BY sp.is_default DESC, sp.purchase_price ASC
");
$supplier_prices->execute([$product_id]);
$supplier_prices = $supplier_prices->fetchAll();

// Get price history
$price_history = $db->prepare("
    SELECT ph.*, u.full_name as changed_by_name
    FROM product_price_history ph
    LEFT JOIN users u ON ph.changed_by = u.id
    WHERE ph.product_id = ?
    ORDER BY ph.created_at DESC
    LIMIT 10
");
$price_history->execute([$product_id]);
$price_history = $price_history->fetchAll();

// Calculate totals
$total_stock = array_sum(array_column($stock_batches, 'quantity'));
$total_stock_value = array_sum(array_map(function($b) { 
    return $b['quantity'] * $b['purchase_price']; 
}, $stock_batches));

// Calculate profit
$profit = $product['sell_price'] - $product['cost_price'];
$profit_margin = $product['sell_price'] > 0 ? (($product['sell_price'] - $product['cost_price']) / $product['sell_price'] * 100) : 0;
$profit_pct = $product['cost_price'] > 0 ? (($product['sell_price'] - $product['cost_price']) / $product['cost_price'] * 100) : 0;

$page_title = 'كارت الصنف: ' . $product['product_name'];

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
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .badge-low { background: #dc3545; }
        .badge-out { background: #6c757d; }
        .alert-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .alert-HIGH_ALERT { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-TOXIC { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-SOUNDALIKE { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .alert-LOOKALIKE { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .alert-CONTRAINDICATION { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .alert-PREGNANCY { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .alert-OTHER { background: #e2e3e5; border: 1px solid #d6d8db; color: #383d41; }
        .info-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            width: 150px;
            display: inline-block;
        }
        .info-value {
            color: #333;
        }
        .unit-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            height: 100%;
        }
        .unit-card h5 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        .unit-card .price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
        }
        .unit-card .ratio {
            font-size: 0.9rem;
            color: #666;
        }
        .feature-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            margin: 2px;
            font-size: 0.85rem;
        }
        .feature-active {
            background: #d4edda;
            color: #155724;
        }
        .feature-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; }
            .main-content { margin-right: 0; }
        }
        @media print {
            .sidebar { display: none; }
            .main-content { margin-right: 0; }
            .btn { display: none; }
        }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>

    <div class="main-content">
        <div class="container-fluid">

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-box"></i> <?= $page_title ?></h2>
                <div>
                    <a href="edit.php?id=<?= $product_id ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-right"></i> العودة
                    </a>
                    <button onclick="window.print()" class="btn btn-info">
                        <i class="bi bi-printer"></i> طباعة
                    </button>
                </div>
            </div>

            <!-- Product Header Card -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> البيانات الأساسية</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">كود الصنف:</span>
                                        <span class="info-value"><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($product['product_code']) ?></code></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">الاسم:</span>
                                        <span class="info-value fw-bold"><?= htmlspecialchars($product['product_name']) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">الاسم الإنجليزي:</span>
                                        <span class="info-value"><?= htmlspecialchars($product['product_name_en'] ?? '-') ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">المادة الفعالة:</span>
                                        <span class="info-value"><?= htmlspecialchars($product['scientific_name'] ?? '-') ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">النوع:</span>
                                        <span class="info-value"><span class="badge bg-info"><?= htmlspecialchars($product['product_type_name'] ?? 'غير محدد') ?></span></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">القسم:</span>
                                        <span class="info-value"><?= htmlspecialchars($product['category_name'] ?? '-') ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">الشركة:</span>
                                        <span class="info-value"><?= htmlspecialchars($product['company_name'] ?? '-') ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">الكود المختصر:</span>
                                        <span class="info-value"><?= htmlspecialchars($product['manual_code'] ?? '-') ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">المصدر:</span>
                                        <span class="info-value">
                                            <?php if ($product['is_imported']): ?>
                                                <span class="badge bg-warning">مستورد</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">محلي</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">الحالة:</span>
                                        <span class="info-value">
                                            <?php if ($product['is_active']): ?>
                                                <span class="badge bg-success">نشط</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">موقوف</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">تاريخ الإضافة:</span>
                                        <span class="info-value"><?= date('Y-m-d H:i', strtotime($product['created_at'])) ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">آخر تحديث:</span>
                                        <span class="info-value"><?= date('Y-m-d H:i', strtotime($product['updated_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Features -->
                            <hr>
                            <h6 class="mb-3"><i class="bi bi-sliders"></i> خصائص الصنف</h6>
                            <div>
                                <span class="feature-badge <?= $product['is_service'] ? 'feature-active' : 'feature-inactive' ?>">
                                    <i class="bi bi-<?= $product['is_service'] ? 'check' : 'x' ?>-circle"></i> صنف خدمي
                                </span>
                                <span class="feature-badge <?= $product['hide_in_receipt'] ? 'feature-active' : 'feature-inactive' ?>">
                                    <i class="bi bi-<?= $product['hide_in_receipt'] ? 'check' : 'x' ?>-circle"></i> إخفاء في الريسيت
                                </span>
                                <span class="feature-badge <?= $product['is_shortage'] ? 'feature-active' : 'feature-inactive' ?>">
                                    <i class="bi bi-<?= $product['is_shortage'] ? 'check' : 'x' ?>-circle"></i> نواقص عامة
                                </span>
                                <span class="feature-badge <?= $product['has_expire'] ? 'feature-active' : 'feature-inactive' ?>">
                                    <i class="bi bi-<?= $product['has_expire'] ? 'check' : 'x' ?>-circle"></i> له تاريخ صلاحية
                                </span>
                                <span class="feature-badge <?= $product['is_drug'] ? 'feature-active' : 'feature-inactive' ?>">
                                    <i class="bi bi-<?= $product['is_drug'] ? 'check' : 'x' ?>-circle"></i> دواء
                                </span>
                                <span class="feature-badge <?= $product['can_be_negative'] ? 'feature-active' : 'feature-inactive' ?>">
                                    <i class="bi bi-<?= $product['can_be_negative'] ? 'check' : 'x' ?>-circle"></i> يسمح بالسالب
                                </span>
                                <span class="feature-badge <?= $product['allow_discount'] ? 'feature-active' : 'feature-inactive' ?>">
                                    <i class="bi bi-<?= $product['allow_discount'] ? 'check' : 'x' ?>-circle"></i> مسموح بالخصم
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Stock Summary -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-warehouse"></i> ملخص المخزون</h5>
                        </div>
                        <div class="card-body text-center">
                            <h1 class="display-4 <?= $total_stock <= 0 ? 'text-danger' : ($total_stock <= $product['min_stock'] ? 'text-warning' : 'text-success') ?>">
                                <?= number_format($total_stock) ?>
                            </h1>
                            <p class="text-muted">إجمالي المخزون</p>
                            <hr>
                            <div class="row">
                                <div class="col-6">
                                    <h5><?= number_format($product['min_stock'], 0) ?></h5>
                                    <small class="text-muted">حد الطلب</small>
                                </div>
                                <div class="col-6">
                                    <h5><?= number_format($total_stock_value, 2) ?> ج</h5>
                                    <small class="text-muted">قيمة المخزون</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Summary -->
                    <div class="card mt-3">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="bi bi-tag"></i> التسعير</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td>سعر التكلفة:</td>
                                    <td class="text-start fw-bold"><?= number_format($product['cost_price'], 2) ?> ج</td>
                                </tr>
                                <tr>
                                    <td>سعر البيع:</td>
                                    <td class="text-start fw-bold text-primary"><?= number_format($product['sell_price'], 2) ?> ج</td>
                                </tr>
                                <tr>
                                    <td>صافي الربح:</td>
                                    <td class="text-start fw-bold text-success">
                                        <?= number_format($profit, 2) ?> ج
                                    </td>
                                </tr>
                                <tr>
                                    <td>نسبة الخصم:</td>
                                    <td class="text-start fw-bold text-warning">
                                        <?= number_format($profit_margin, 2) ?>%
                                    </td>
                                </tr>
                                <tr>
                                    <td>الخصم الأقصى:</td>
                                    <td class="text-start"><?= number_format($product['max_discount'], 1) ?>%</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Units Section -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="bi bi-balance-scale"></i> الوحدات والتسعير</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Unit 1 (Large) -->
                                <div class="col-md-4 mb-3">
                                    <div class="unit-card border border-primary">
                                        <h5><i class="bi bi-box"></i> الوحدة الكبرى</h5>
                                        <p class="text-muted mb-2"><?= htmlspecialchars($product['unit1_name'] ?? 'غير محدد') ?></p>
                                        <div class="price"><?= number_format($product['sell_price'], 2) ?> ج</div>
                                        <div class="ratio mt-2">
                                            <span class="badge bg-primary">وحدة البيع الافتراضية</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Unit 2 (Medium) -->
                                <div class="col-md-4 mb-3">
                                    <div class="unit-card border border-info">
                                        <h5><i class="bi bi-box-seam"></i> الوحدة الوسطى</h5>
                                        <p class="text-muted mb-2"><?= htmlspecialchars($product['unit2_name'] ?? 'غير محدد') ?></p>
                                        <div class="price"><?= number_format($product['unit2_sell_price'], 2) ?> ج</div>
                                        <div class="ratio mt-2">
                                            <?php if ($product['unit1_to_unit2'] > 0): ?>
                                                <span class="badge bg-info">1 : <?= number_format($product['unit1_to_unit2']) ?></span>
                                                <small class="d-block text-muted">الكبرى = <?= number_format($product['unit1_to_unit2']) ?> وسطى</small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">غير محدد</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <!-- Unit 3 (Small) -->
                                <div class="col-md-4 mb-3">
                                    <div class="unit-card border border-success">
                                        <h5><i class="bi bi-box2"></i> الوحدة الصغرى</h5>
                                        <p class="text-muted mb-2"><?= htmlspecialchars($product['unit3_name'] ?? 'غير محدد') ?></p>
                                        <div class="price"><?= number_format($product['unit3_sell_price'], 2) ?> ج</div>
                                        <div class="ratio mt-2">
                                            <?php if ($product['unit1_to_unit3'] > 0): ?>
                                                <span class="badge bg-success">1 : <?= number_format($product['unit1_to_unit3']) ?></span>
                                                <small class="d-block text-muted">الكبرى = <?= number_format($product['unit1_to_unit3']) ?> صغرى</small>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">غير محدد</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header p-0">
                            <ul class="nav nav-tabs" id="viewTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="barcodes-tab" data-bs-toggle="tab" href="#barcodes" role="tab">
                                        <i class="bi bi-upc"></i> الباركود (<?= count($barcodes) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="stock-tab" data-bs-toggle="tab" href="#stock" role="tab">
                                        <i class="bi bi-warehouse"></i> المخزون (<?= count($stock_batches) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="locations-tab" data-bs-toggle="tab" href="#locations" role="tab">
                                        <i class="bi bi-geo-alt"></i> المواقع (<?= count($locations) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="suppliers-tab" data-bs-toggle="tab" href="#suppliers" role="tab">
                                        <i class="bi bi-truck"></i> الموردين (<?= count($supplier_prices) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="alerts-tab" data-bs-toggle="tab" href="#alerts" role="tab">
                                        <i class="bi bi-exclamation-triangle"></i> التحذيرات (<?= count($alerts) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="history-tab" data-bs-toggle="tab" href="#history" role="tab">
                                        <i class="bi bi-clock-history"></i> تاريخ الأسعار
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div class="card-body">
                            <div class="tab-content" id="viewTabContent">

                                <!-- Barcodes Tab -->
                                <div class="tab-pane fade show active" id="barcodes" role="tabpanel">
                                    <?php if (empty($barcodes)): ?>
                                        <div class="alert alert-info">لا يوجد باركود مسجل</div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($barcodes as $barcode): ?>
                                                <div class="col-md-3 mb-3">
                                                    <div class="card <?= $barcode['is_primary'] ? 'border-primary' : '' ?>">
                                                        <div class="card-body text-center">
                                                            <i class="bi bi-upc-scan fs-1 text-muted mb-2"></i>
                                                            <h5 class="font-monospace"><?= htmlspecialchars($barcode['barcode']) ?></h5>
                                                            <p class="text-muted">
                                                                الوحدة: <?= $barcode['unit_id'] == 1 ? 'كبرى' : ($barcode['unit_id'] == 2 ? 'وسطى' : 'صغرى') ?>
                                                            </p>
                                                            <?php if ($barcode['is_primary']): ?>
                                                                <span class="badge bg-primary">رئيسي</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Stock Tab -->
                                <div class="tab-pane fade" id="stock" role="tabpanel">
                                    <?php if (empty($stock_batches)): ?>
                                        <div class="alert alert-warning">لا يوجد مخزون مسجل</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>رقم التشغيلة</th>
                                                        <th>الفرع</th>
                                                        <th>المورد</th>
                                                        <th>الكمية</th>
                                                        <th>سعر الشراء</th>
                                                        <th>سعر البيع</th>
                                                        <th>تاريخ الصلاحية</th>
                                                        <th>القيمة</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stock_batches as $batch): 
                                                        $batch_value = $batch['quantity'] * $batch['purchase_price'];
                                                        $is_expired = $batch['exp_date'] && strtotime($batch['exp_date']) < time();
                                                        $is_near_expiry = $batch['exp_date'] && strtotime($batch['exp_date']) < strtotime('+3 months');
                                                    ?>
                                                        <tr class="<?= $is_expired ? 'table-danger' : ($is_near_expiry ? 'table-warning' : '') ?>">
                                                            <td><code><?= htmlspecialchars($batch['batch_number'] ?? 'N/A') ?></code></td>
                                                            <td><?= htmlspecialchars($batch['branch_name'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($batch['supplier_name'] ?? '-') ?></td>
                                                            <td><?= number_format($batch['quantity']) ?></td>
                                                            <td><?= number_format($batch['purchase_price'], 2) ?></td>
                                                            <td><?= number_format($batch['sell_price'], 2) ?></td>
                                                            <td>
                                                                <?php if ($batch['exp_date']): ?>
                                                                    <span class="<?= $is_expired ? 'text-danger' : ($is_near_expiry ? 'text-warning' : 'text-success') ?>">
                                                                        <?= date('Y-m-d', strtotime($batch['exp_date'])) ?>
                                                                        <?php if ($is_expired): ?> (منتهي)<?php endif; ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    -
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= number_format($batch_value, 2) ?> ج</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-light">
                                                    <tr>
                                                        <th colspan="3">الإجمالي</th>
                                                        <th><?= number_format($total_stock) ?></th>
                                                        <th colspan="3"></th>
                                                        <th><?= number_format($total_stock_value, 2) ?> ج</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Locations Tab -->
                                <div class="tab-pane fade" id="locations" role="tabpanel">
                                    <?php if (empty($locations)): ?>
                                        <div class="alert alert-info">لا يوجد مواقع تخزين مسجلة</div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($locations as $location): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">
                                                                <i class="bi bi-geo-alt-fill text-primary"></i>
                                                                <?= htmlspecialchars($location['location_name']) ?>
                                                            </h5>
                                                            <p class="card-text">
                                                                <strong>الكود:</strong> <?= htmlspecialchars($location['location_code']) ?><br>
                                                                <?php if ($location['shelf_number']): ?>
                                                                    <strong>الرف:</strong> <?= htmlspecialchars($location['shelf_number']) ?><br>
                                                                <?php endif; ?>
                                                                <?php if ($location['row_number']): ?>
                                                                    <strong>الصف:</strong> <?= htmlspecialchars($location['row_number']) ?>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Suppliers Tab -->
                                <div class="tab-pane fade" id="suppliers" role="tabpanel">
                                    <?php if (empty($supplier_prices)): ?>
                                        <div class="alert alert-info">لا يوجد موردين مسجلين</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>المورد</th>
                                                        <th>سعر الشراء</th>
                                                        <th>سعر البيع</th>
                                                        <th>الخصم</th>
                                                        <th>الضريبة</th>
                                                        <th>الربح</th>
                                                        <th>آخر توريد</th>
                                                        <th>افتراضي</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($supplier_prices as $sp): 
                                                        $sp_profit = $sp['sell_price'] - $sp['purchase_price'];
                                                        $sp_profit_pct = $sp['purchase_price'] > 0 ? ($sp_profit / $sp['purchase_price'] * 100) : 0;
                                                    ?>
                                                        <tr class="<?= $sp['is_default'] ? 'table-primary' : '' ?>">
                                                            <td>
                                                                <?= htmlspecialchars($sp['supplier_name']) ?>
                                                                <br><small class="text-muted"><?= htmlspecialchars($sp['supplier_code']) ?></small>
                                                            </td>
                                                            <td><?= number_format($sp['purchase_price'], 2) ?> ج</td>
                                                            <td><?= number_format($sp['sell_price'], 2) ?> ج</td>
                                                            <td><?= number_format($sp['discount_percent'], 1) ?>%</td>
                                                            <td><?= number_format($sp['vat_percent'], 1) ?>%</td>
                                                            <td class="<?= $sp_profit > 0 ? 'text-success' : 'text-danger' ?>">
                                                                <?= number_format($sp_profit, 2) ?> ج (<?= number_format($sp_profit_pct, 1) ?>%)
                                                            </td>
                                                            <td><?= $sp['last_supply_date'] ? date('Y-m-d', strtotime($sp['last_supply_date'])) : '-' ?></td>
                                                            <td>
                                                                <?php if ($sp['is_default']): ?>
                                                                    <span class="badge bg-primary">✓</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Alerts Tab -->
                                <div class="tab-pane fade" id="alerts" role="tabpanel">
                                    <?php if (empty($alerts)): ?>
                                        <div class="alert alert-success">لا يوجد تحذيرات مسجلة</div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($alerts as $alert): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="alert-card alert-<?= $alert['alert_type'] ?>">
                                                        <h5 class="alert-heading">
                                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                                            <?= str_replace('_', ' ', $alert['alert_type']) ?>
                                                        </h5>
                                                        <p class="mb-0"><?= htmlspecialchars($alert['alert_message']) ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Price History Tab -->
                                <div class="tab-pane fade" id="history" role="tabpanel">
                                    <?php if (empty($price_history)): ?>
                                        <div class="alert alert-info">لا يوجد تاريخ تغيير أسعار</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>التاريخ</th>
                                                        <th>سعر البيع القديم</th>
                                                        <th>سعر البيع الجديد</th>
                                                        <th>التغيير</th>
                                                        <th>بواسطة</th>
                                                        <th>السبب</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($price_history as $ph): 
                                                        $change = $ph['new_sell_price'] - ($ph['old_sell_price'] ?? 0);
                                                    ?>
                                                        <tr>
                                                            <td><?= date('Y-m-d H:i', strtotime($ph['created_at'])) ?></td>
                                                            <td><?= $ph['old_sell_price'] ? number_format($ph['old_sell_price'], 2) : '-' ?></td>
                                                            <td><?= number_format($ph['new_sell_price'], 2) ?></td>
                                                            <td class="<?= $change > 0 ? 'text-success' : ($change < 0 ? 'text-danger' : '') ?>">
                                                                <?= $change > 0 ? '+' : '' ?><?= number_format($change, 2) ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($ph['changed_by_name'] ?? 'System') ?></td>
                                                            <td><?= htmlspecialchars($ph['change_reason'] ?? '-') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>