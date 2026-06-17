<?php
require_once '../../core/init.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Check permissions
if (!hasPermission('products.view')) {
    redirect('index.php');
}

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$product_id) {
    setFlashMessage('error', 'معرف الصنف غير صحيح');
    redirect('index.php');
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
    setFlashMessage('error', 'الصنف غير موجود');
    redirect('index.php');
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

$page_title = 'كارت الصنف: ' . $product['product_name'];
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-box"></i> <?= $page_title ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="../../index.php">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="index.php">كارت الأصناف</a></li>
                        <li class="breadcrumb-item active">عرض الصنف</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Action Buttons -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <a href="edit.php?id=<?= $product_id ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة للقائمة
                    </a>
                    <button onclick="window.print()" class="btn btn-info">
                        <i class="fas fa-print"></i> طباعة
                    </button>
                </div>
            </div>

            <!-- Product Header Card -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary">
                            <h3 class="card-title text-white">
                                <i class="fas fa-info-circle"></i> البيانات الأساسية
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="font-weight-bold">كود الصنف:</td>
                                            <td><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($product['product_code']) ?></code></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">الاسم:</td>
                                            <td><?= htmlspecialchars($product['product_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">الاسم الإنجليزي:</td>
                                            <td><?= htmlspecialchars($product['product_name_en'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">المادة الفعالة:</td>
                                            <td><?= htmlspecialchars($product['scientific_name'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">النوع:</td>
                                            <td>
                                                <span class="badge badge-info"><?= htmlspecialchars($product['product_type_name'] ?? 'غير محدد') ?></span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="font-weight-bold">القسم:</td>
                                            <td><?= htmlspecialchars($product['category_name'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">الشركة:</td>
                                            <td><?= htmlspecialchars($product['company_name'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">المصدر:</td>
                                            <td>
                                                <?php if ($product['is_imported']): ?>
                                                    <span class="badge badge-warning">مستورد</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">محلي</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">الحالة:</td>
                                            <td>
                                                <?php if ($product['is_active']): ?>
                                                    <span class="badge badge-success">نشط</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">موقوف</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">تاريخ الإضافة:</td>
                                            <td><?= date('Y-m-d H:i', strtotime($product['created_at'])) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Stock Summary -->
                    <div class="card">
                        <div class="card-header bg-success">
                            <h3 class="card-title text-white">
                                <i class="fas fa-warehouse"></i> ملخص المخزون
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <h1 class="display-4 <?= $total_stock <= 0 ? 'text-danger' : ($total_stock <= $product['min_stock'] ? 'text-warning' : 'text-success') ?>">
                                    <?= number_format($total_stock) ?>
                                </h1>
                                <p class="text-muted">إجمالي المخزون</p>
                            </div>
                            <hr>
                            <div class="row text-center">
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
                        <div class="card-header bg-info">
                            <h3 class="card-title text-white">
                                <i class="fas fa-tag"></i> التسعير
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td>سعر التكلفة:</td>
                                    <td class="text-left font-weight-bold"><?= number_format($product['cost_price'], 2) ?> ج</td>
                                </tr>
                                <tr>
                                    <td>سعر البيع:</td>
                                    <td class="text-left font-weight-bold text-primary"><?= number_format($product['sell_price'], 2) ?> ج</td>
                                </tr>
                                <tr>
                                    <td>الربح:</td>
                                    <td class="text-left font-weight-bold text-success">
                                        <?php 
                                        $profit = $product['sell_price'] - $product['cost_price'];
                                        $profit_pct = $product['cost_price'] > 0 ? ($profit / $product['cost_price'] * 100) : 0;
                                        echo number_format($profit, 2) . ' ج (' . number_format($profit_pct, 1) . '%)';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>الخصم الأقصى:</td>
                                    <td class="text-left"><?= number_format($product['max_discount'], 1) ?>%</td>
                                </tr>
                            </table>
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
                                    <a class="nav-link active" id="barcodes-tab" data-toggle="tab" href="#barcodes" role="tab">
                                        <i class="fas fa-barcode"></i> الباركود (<?= count($barcodes) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="stock-tab" data-toggle="tab" href="#stock" role="tab">
                                        <i class="fas fa-warehouse"></i> المخزون (<?= count($stock_batches) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="locations-tab" data-toggle="tab" href="#locations" role="tab">
                                        <i class="fas fa-map-marker-alt"></i> المواقع (<?= count($locations) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="suppliers-tab" data-toggle="tab" href="#suppliers" role="tab">
                                        <i class="fas fa-truck"></i> الموردين (<?= count($supplier_prices) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="alerts-tab" data-toggle="tab" href="#alerts" role="tab">
                                        <i class="fas fa-exclamation-triangle"></i> التحذيرات (<?= count($alerts) ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="history-tab" data-toggle="tab" href="#history" role="tab">
                                        <i class="fas fa-history"></i> تاريخ الأسعار
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
                                                            <i class="fas fa-barcode fa-3x text-muted mb-2"></i>
                                                            <h5 class="font-monospace"><?= htmlspecialchars($barcode['barcode']) ?></h5>
                                                            <p class="text-muted">
                                                                الوحدة: <?= $barcode['unit_id'] == 1 ? 'كبرى' : ($barcode['unit_id'] == 2 ? 'وسطى' : 'صغرى') ?>
                                                            </p>
                                                            <?php if ($barcode['is_primary']): ?>
                                                                <span class="badge badge-primary">رئيسي</span>
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
                                                <thead class="thead-dark">
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
                                                <tfoot class="thead-light">
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
                                                                <i class="fas fa-map-marker-alt text-primary"></i>
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
                                                <thead class="thead-dark">
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
                                                        $profit = $sp['sell_price'] - $sp['purchase_price'];
                                                        $profit_pct = $sp['purchase_price'] > 0 ? ($profit / $sp['purchase_price'] * 100) : 0;
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
                                                            <td class="<?= $profit > 0 ? 'text-success' : 'text-danger' ?>">
                                                                <?= number_format($profit, 2) ?> ج (<?= number_format($profit_pct, 1) ?>%)
                                                            </td>
                                                            <td><?= $sp['last_supply_date'] ? date('Y-m-d', strtotime($sp['last_supply_date'])) : '-' ?></td>
                                                            <td>
                                                                <?php if ($sp['is_default']): ?>
                                                                    <span class="badge badge-primary">✓</span>
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
                                            <?php foreach ($alerts as $alert): 
                                                $alert_colors = [
                                                    'HIGH_ALERT' => 'danger',
                                                    'TOXIC' => 'danger',
                                                    'SOUNDALIKE' => 'warning',
                                                    'LOOKALIKE' => 'warning',
                                                    'CONTRAINDICATION' => 'info',
                                                    'PREGNANCY' => 'info',
                                                    'OTHER' => 'secondary'
                                                ];
                                                $color = $alert_colors[$alert['alert_type']] ?? 'secondary';
                                            ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="alert alert-<?= $color ?> border-<?= $color ?>">
                                                        <h5 class="alert-heading">
                                                            <i class="fas fa-exclamation-triangle"></i>
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
                                                <thead class="thead-dark">
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
    </section>
</div>

<?php require_once '../../includes/footer.php'; ?>