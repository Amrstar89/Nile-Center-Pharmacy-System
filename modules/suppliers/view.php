<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$supplier_id = intval($_GET['id'] ?? 0);
if ($supplier_id <= 0) {
    redirect('index.php');
}

// Get supplier with balance
$stmt = $db->prepare("
    SELECT s.*, sb.balance, sb.total_purchases, sb.total_payments, sb.total_returns
    FROM suppliers s
    LEFT JOIN supplier_balances sb ON s.id = sb.supplier_id
    WHERE s.id = ? AND (s.deleted_at IS NULL OR s.deleted_at = '')
");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();
if (!$supplier) {
    redirect('index.php');
}

// Get related data
$phones = $db->query("SELECT * FROM supplier_phones WHERE supplier_id = {$supplier_id} ORDER BY is_primary DESC, id ASC")->fetchAll();
$addresses = $db->query("SELECT sa.*, a.area_name_ar, g.governorate_name_ar, z.zone_name_ar 
    FROM supplier_addresses sa 
    LEFT JOIN areas a ON sa.area_id = a.id 
    LEFT JOIN governorates g ON sa.governorate_id = g.id 
    LEFT JOIN delivery_zones z ON sa.delivery_zone_id = z.id 
    WHERE sa.supplier_id = {$supplier_id} ORDER BY sa.is_primary DESC, sa.id ASC")->fetchAll();
$contacts = $db->query("SELECT * FROM supplier_contacts WHERE supplier_id = {$supplier_id} AND is_active = 1 ORDER BY is_primary DESC, id ASC")->fetchAll();
$bank_accounts = $db->query("SELECT * FROM supplier_bank_accounts WHERE supplier_id = {$supplier_id} AND is_active = 1 ORDER BY is_primary DESC, id ASC")->fetchAll();

// FIXED: Get products with barcodes from product_barcodes table (NOT products.barcode)
$products = $db->query("
    SELECT sp.*, p.product_name, p.product_name_en,
        (SELECT pb.barcode FROM product_barcodes pb 
         WHERE pb.product_id = p.id AND pb.is_primary = 1 LIMIT 1) as barcode
    FROM supplier_products sp 
    LEFT JOIN products p ON sp.product_id = p.id 
    WHERE sp.supplier_id = {$supplier_id} 
    ORDER BY sp.last_purchase_date DESC LIMIT 50
")->fetchAll();

$due_payments = $db->query("SELECT * FROM supplier_due_payments WHERE supplier_id = {$supplier_id} ORDER BY due_date ASC LIMIT 20")->fetchAll();

// Type labels
$type_labels = [
    'b2b' => ['صيدلية', 'bi-shop', 'info'],
    'private_office' => ['مكتب خاص', 'bi-briefcase', 'warning'],
    'warehouse' => ['مخزن', 'bi-building', 'secondary'],
    'distributor' => ['موزع', 'bi-truck', 'success'],
    'company' => ['شركة', 'bi-building-fill', 'primary']
];
$type_info = $type_labels[$supplier['supplier_type']] ?? ['شركة', 'bi-building-fill', 'primary'];

$page_title = 'عرض المورد - ' . $supplier['supplier_name'];
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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; --info: #0dcaf0; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; color: #fff; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: flex; align-items: center; transition: all 0.3s; border-radius: 8px; margin: 2px 10px; text-decoration: none; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { color: #fff; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .sidebar .nav-link i { margin-left: 10px; font-size: 18px; color: rgba(255,255,255,0.7); }
        .sidebar .nav-link:hover i { color: #fff; }
        .sidebar .nav-link.active i { color: #fff; }
        .sidebar-brand { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; }
        .sidebar-brand h4 { margin: 0; font-size: 20px; }
        .sidebar-brand small { color: rgba(255,255,255,0.6); font-size: 12px; }
        .sidebar-heading { color: rgba(255,255,255,0.5); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 15px 20px 5px; font-weight: 600; }
        .nav-menu { padding: 10px 0; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .nav-tabs .nav-link { border: none; color: #666; font-weight: 500; }
        .nav-tabs .nav-link.active { color: var(--primary); border-bottom: 2px solid var(--primary); background: transparent; }
        .info-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; padding: 15px; margin-bottom: 10px; }
        .info-label { color: #666; font-size: 12px; margin-bottom: 3px; }
        .info-value { font-weight: 600; font-size: 14px; }
        .balance-card { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 15px; }
        .balance-amount { font-size: 28px; font-weight: bold; }
        .contact-card { border-left: 3px solid var(--primary); padding: 10px 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; }
        .product-row { border-bottom: 1px solid #eee; padding: 10px 0; }
        .product-row:last-child { border-bottom: none; }
        .due-item { border-left: 3px solid var(--warning); padding: 10px 15px; background: #fff8e1; border-radius: 8px; margin-bottom: 8px; }
        .due-overdue { border-left-color: var(--danger); background: #ffebee; }
        .due-paid { border-left-color: var(--success); background: #e8f5e9; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-eye"></i> <?= $page_title ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">الموردين</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($supplier['supplier_name']) ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="statement.php?id=<?= $supplier_id ?>" class="btn btn-primary">
                        <i class="bi bi-file-text"></i> كشف حساب
                    </a>
                    <a href="edit.php?id=<?= $supplier_id ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> تعديل
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i> رجوع
                    </a>
                </div>
            </div>

            <!-- Supplier Header Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h3><i class="bi bi-truck"></i> <?= htmlspecialchars($supplier['supplier_name']) ?></h3>
                            <?php if (!empty($supplier['supplier_name_en'])): ?>
                                <p class="text-muted mb-2"><?= htmlspecialchars($supplier['supplier_name_en']) ?></p>
                            <?php endif; ?>
                            <div>
                                <span class="badge bg-<?= $type_info[2] ?> me-2"><i class="bi <?= $type_info[1] ?>"></i> <?= $type_info[0] ?></span>
                                <span class="badge bg-light text-dark border">كود: <?= htmlspecialchars($supplier['supplier_code'] ?? $supplier['id']) ?></span>
                                <span class="badge <?= $supplier['is_active'] ? 'bg-success' : 'bg-danger' ?>"><?= $supplier['is_active'] ? 'نشط' : 'موقوف' ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="balance-card p-4 text-center">
                                <div class="mb-2">الرصيد الحالي</div>
                                <div class="balance-amount"><?= number_format(floatval($supplier['balance'] ?? 0), 2) ?> ج</div>
                                <small><?= floatval($supplier['balance'] ?? 0) > 0 ? '(علينا للمورد)' : (floatval($supplier['balance'] ?? 0) < 0 ? '(المورد علينا)' : 'متعادل') ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="viewTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button"><i class="bi bi-info-circle"></i> المعلومات</button></li>
                <li class="nav-item"><button class="nav-link" id="phones-tab" data-bs-toggle="tab" data-bs-target="#phones" type="button"><i class="bi bi-telephone"></i> الهواتف (<?= count($phones) ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" type="button"><i class="bi bi-geo-alt"></i> العناوين (<?= count($addresses) ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button"><i class="bi bi-people"></i> الموظفين (<?= count($contacts) ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank" type="button"><i class="bi bi-bank"></i> البنوك (<?= count($bank_accounts) ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button"><i class="bi bi-box-seam"></i> الأصناف (<?= count($products) ?>)</button></li>
                <li class="nav-item"><button class="nav-link" id="dues-tab" data-bs-toggle="tab" data-bs-target="#dues" type="button"><i class="bi bi-calendar-check"></i> المستحقات (<?= count($due_payments) ?>)</button></li>
            </ul>

            <div class="tab-content" id="viewTabsContent">
                <!-- Info Tab -->
                <div class="tab-pane fade show active" id="info" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="info-card">
                                <div class="info-label">نوع التعامل</div>
                                <div class="info-value">
                                    <?php if ($supplier['payment_type'] == 'credit'): ?>
                                        <span class="badge bg-info">آجل</span>
                                    <?php elseif ($supplier['payment_type'] == 'cheque'): ?>
                                        <span class="badge bg-warning text-dark">شيك</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">نقدي</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="info-card">
                                <div class="info-label">حد التعامل</div>
                                <div class="info-value"><?= $supplier['payment_type'] != 'cash' ? number_format($supplier['credit_limit'], 2) . ' ج' : '-' ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="info-card">
                                <div class="info-label">فترة السماح</div>
                                <div class="info-value"><?= $supplier['grace_period'] > 0 ? $supplier['grace_period'] . ' يوم' : '-' ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="info-card">
                                <div class="info-label">رقم الإنستاباي</div>
                                <div class="info-value"><?= htmlspecialchars($supplier['instapay_number'] ?? '-') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="info-card">
                                <div class="info-label">رقم المحفظة</div>
                                <div class="info-value"><?= htmlspecialchars($supplier['wallet_number'] ?? '-') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="info-card">
                                <div class="info-label">تاريخ الإضافة</div>
                                <div class="info-value"><?= date('Y-m-d', strtotime($supplier['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="info-card">
                                <div class="info-label">سياسة المرتجعات</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($supplier['return_policy'] ?? 'لا توجد')) ?></div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="info-card">
                                <div class="info-label">ملاحظات</div>
                                <div class="info-value"><?= nl2br(htmlspecialchars($supplier['notes'] ?? 'لا توجد')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phones Tab -->
                <div class="tab-pane fade" id="phones" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-telephone"></i> أرقام الهاتف</h5>
                        <?php if (empty($phones)): ?>
                            <div class="text-center text-muted py-4">لا توجد أرقام هاتف مسجلة</div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($phones as $phone): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="info-card">
                                        <div class="d-flex justify-content-between">
                                            <span class="badge bg-light text-dark border"><?= ['mobile'=>'موبايل','landline'=>'أرضي','fax'=>'فاكس','whatsapp'=>'واتساب'][$phone['phone_type']] ?? $phone['phone_type'] ?></span>
                                            <?php if ($phone['is_primary']): ?><span class="badge bg-primary">رئيسي</span><?php endif; ?>
                                        </div>
                                        <div class="info-value mt-2"><?= htmlspecialchars($phone['country_code'] . ' ' . $phone['phone_number']) ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div></div>
                </div>

                <!-- Addresses Tab -->
                <div class="tab-pane fade" id="addresses" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-geo-alt"></i> العناوين</h5>
                        <?php if (empty($addresses)): ?>
                            <div class="text-center text-muted py-4">لا توجد عناوين مسجلة</div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($addresses as $addr): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="info-card">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="badge bg-light text-dark border"><?= ['main'=>'رئيسي','warehouse'=>'مخزن','branch'=>'فرع','other'=>'أخرى'][$addr['address_type']] ?? $addr['address_type'] ?></span>
                                            <?php if ($addr['is_primary']): ?><span class="badge bg-primary">رئيسي</span><?php endif; ?>
                                        </div>
                                        <div class="info-value"><?= htmlspecialchars($addr['street_name'] ?? '') ?></div>
                                        <?php if ($addr['building_number']): ?><div class="info-label">عمارة: <?= htmlspecialchars($addr['building_number']) ?> <?= $addr['floor_number'] ? '- الدور: ' . htmlspecialchars($addr['floor_number']) : '' ?> <?= $addr['apartment_number'] ? '- شقة: ' . htmlspecialchars($addr['apartment_number']) : '' ?></div><?php endif; ?>
                                        <?php if ($addr['landmark']): ?><div class="info-label">علامة: <?= htmlspecialchars($addr['landmark']) ?></div><?php endif; ?>
                                        <?php if ($addr['governorate_name_ar']): ?><div class="info-label"><?= htmlspecialchars($addr['governorate_name_ar']) ?> - <?= htmlspecialchars($addr['area_name_ar'] ?? '') ?> <?= $addr['zone_name_ar'] ? '- ' . htmlspecialchars($addr['zone_name_ar']) : '' ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div></div>
                </div>

                <!-- Contacts Tab -->
                <div class="tab-pane fade" id="contacts" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-people"></i> الموظفين والمناديب</h5>
                        <?php if (empty($contacts)): ?>
                            <div class="text-center text-muted py-4">لا يوجد موظفين مسجلين</div>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                            <div class="contact-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-<?= ['manager'=>'primary','representative'=>'info','distributor'=>'success','other'=>'secondary'][$contact['contact_type']] ?? 'secondary' ?> me-2">
                                            <?= ['manager'=>'مدير','representative'=>'مندوب','distributor'=>'موزع','other'=>'أخرى'][$contact['contact_type']] ?? $contact['contact_type'] ?>
                                        </span>
                                        <?php if ($contact['is_primary']): ?><span class="badge bg-primary">رئيسي</span><?php endif; ?>
                                        <h6 class="mt-2 mb-1"><?= htmlspecialchars($contact['contact_name']) ?></h6>
                                        <?php if ($contact['job_title']): ?><div class="text-muted small"><?= htmlspecialchars($contact['job_title']) ?></div><?php endif; ?>
                                    </div>
                                    <div class="text-start">
                                        <?php if ($contact['phone']): ?><div><i class="bi bi-telephone"></i> <?= htmlspecialchars($contact['phone']) ?></div><?php endif; ?>
                                        <?php if ($contact['email']): ?><div><i class="bi bi-envelope"></i> <?= htmlspecialchars($contact['email']) ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div></div>
                </div>

                <!-- Bank Tab -->
                <div class="tab-pane fade" id="bank" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-bank"></i> الحسابات البنكية</h5>
                        <?php if (empty($bank_accounts)): ?>
                            <div class="text-center text-muted py-4">لا توجد حسابات بنكية مسجلة</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>البنك</th><th>رقم الحساب</th><th>IBAN</th><th>Swift</th><th>فرع</th><th>رئيسي</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($bank_accounts as $bank): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($bank['bank_name']) ?></strong></td>
                                            <td><?= htmlspecialchars($bank['account_number']) ?></td>
                                            <td><?= htmlspecialchars($bank['iban'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($bank['swift_code'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($bank['branch_name'] ?? '-') ?></td>
                                            <td><?= $bank['is_primary'] ? '<span class="badge bg-primary">✓</span>' : '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div></div>
                </div>

                <!-- Products Tab -->
                <div class="tab-pane fade" id="products" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0"><i class="bi bi-box-seam"></i> أصناف المورد</h5>
                            <a href="#" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> إضافة صنف</a>
                        </div>
                        <?php if (empty($products)): ?>
                            <div class="text-center text-muted py-4">لا توجد أصناف مسجلة</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>الصنف</th>
                                            <th>كود المورد</th>
                                            <th>سعر الشراء</th>
                                            <th>الخصم</th>
                                            <th>الضريبة</th>
                                            <th>الصافي</th>
                                            <th>آخر شراء</th>
                                            <th>افتراضي</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $prod): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($prod['product_name']) ?></strong>
                                                <?php if ($prod['product_name_en']): ?><br><small class="text-muted"><?= htmlspecialchars($prod['product_name_en']) ?></small><?php endif; ?>
                                                <?php if ($prod['barcode']): ?><br><small class="text-muted"><?= htmlspecialchars($prod['barcode']) ?></small><?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($prod['product_code'] ?? '-') ?></td>
                                            <td><?= number_format($prod['purchase_price'], 2) ?> ج</td>
                                            <td><?= $prod['discount_percent'] > 0 ? $prod['discount_percent'] . '%' : '-' ?></td>
                                            <td><?= $prod['vat_percent'] > 0 ? $prod['vat_percent'] . '%' : '-' ?></td>
                                            <td><strong><?= number_format($prod['net_price'], 2) ?> ج</strong></td>
                                            <td><?= $prod['last_purchase_date'] ? date('Y-m-d', strtotime($prod['last_purchase_date'])) : '-' ?></td>
                                            <td><?= $prod['is_default'] ? '<span class="badge bg-success">✓</span>' : '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div></div>
                </div>

                <!-- Dues Tab -->
                <div class="tab-pane fade" id="dues" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <h5 class="mb-3"><i class="bi bi-calendar-check"></i> مستحقات المورد</h5>
                        <?php if (empty($due_payments)): ?>
                            <div class="text-center text-muted py-4">لا توجد مستحقات مسجلة</div>
                        <?php else: ?>
                            <?php foreach ($due_payments as $due): 
                                $due_class = $due['status'] == 'overdue' ? 'due-overdue' : ($due['status'] == 'paid' ? 'due-paid' : '');
                                $status_label = ['pending'=>'معلق','partial'=>'جزئي','paid'=>'مدفوع','overdue'=>'متأخر'][$due['status']] ?? $due['status'];
                            ?>
                            <div class="due-item <?= $due_class ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-light text-dark border me-2"><?= $due['reference_number'] ?? '#' . $due['id'] ?></span>
                                            <span class="badge bg-<?= $due['due_type']=='cheque'?'warning text-dark':'info' ?>"><?= $due['due_type']=='cheque'?'شيك':'آجل' ?></span>
                                            <span class="badge bg-<?= ['pending'=>'secondary','partial'=>'warning','paid'=>'success','overdue'=>'danger'][$due['status']] ?> ms-2"><?= $status_label ?></span>
                                        </div>
                                        <div class="mt-2">
                                            <strong>المبلغ: <?= number_format($due['amount'], 2) ?> ج</strong>
                                            <?php if ($due['paid_amount'] > 0): ?>
                                                <span class="text-muted"> | مدفوع: <?= number_format($due['paid_amount'], 2) ?> ج | متبقي: <?= number_format($due['remaining_amount'], 2) ?> ج</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($due['cheque_number']): ?><div class="text-muted small">شيك: <?= htmlspecialchars($due['cheque_number']) ?> - <?= htmlspecialchars($due['bank_name'] ?? '') ?></div><?php endif; ?>
                                    </div>
                                    <div class="text-start">
                                        <div class="text-muted small">تاريخ الاستحقاق</div>
                                        <div class="fw-bold <?= $due['status']=='overdue'?'text-danger':'' ?>"><?= date('Y-m-d', strtotime($due['due_date'])) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>