<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();
$error = '';

$supplier_id = intval($_GET['id'] ?? 0);
if ($supplier_id <= 0) {
    redirect('index.php');
}

// Get supplier
$stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();
if (!$supplier) {
    redirect('index.php');
}

// Get related data - FIX: separate execute and fetchAll
$phone_stmt = $db->prepare("SELECT * FROM supplier_phones WHERE supplier_id = ? ORDER BY is_primary DESC, id ASC");
$phone_stmt->execute([$supplier_id]);
$phones = $phone_stmt->fetchAll();

$addr_stmt = $db->prepare("SELECT sa.*, a.area_name_ar, g.governorate_name_ar, z.zone_name_ar 
    FROM supplier_addresses sa 
    LEFT JOIN areas a ON sa.area_id = a.id 
    LEFT JOIN governorates g ON sa.governorate_id = g.id 
    LEFT JOIN delivery_zones z ON sa.delivery_zone_id = z.id 
    WHERE sa.supplier_id = ? ORDER BY sa.is_primary DESC, sa.id ASC");
$addr_stmt->execute([$supplier_id]);
$addresses = $addr_stmt->fetchAll();

$contact_stmt = $db->prepare("SELECT * FROM supplier_contacts WHERE supplier_id = ? ORDER BY is_primary DESC, id ASC");
$contact_stmt->execute([$supplier_id]);
$contacts = $contact_stmt->fetchAll();

$bank_stmt = $db->prepare("SELECT * FROM supplier_bank_accounts WHERE supplier_id = ? ORDER BY is_primary DESC, id ASC");
$bank_stmt->execute([$supplier_id]);
$bank_accounts = $bank_stmt->fetchAll();

// Get dropdowns
$areas = $db->query("SELECT * FROM areas WHERE is_active = 1 ORDER BY area_name_ar")->fetchAll();
$governorates = $db->query("SELECT * FROM governorates WHERE is_active = 1 ORDER BY governorate_name_ar")->fetchAll();
$zones = $db->query("SELECT * FROM delivery_zones WHERE is_active = 1 ORDER BY zone_name_ar")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Validate supplier name
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        if (empty($supplier_name)) {
            throw new Exception('اسم المورد مطلوب');
        }
        $stmt = $db->prepare("SELECT id FROM suppliers WHERE supplier_name = ? AND id != ?");
        $stmt->execute([$supplier_name, $supplier_id]);
        if ($stmt->fetch()) {
            throw new Exception('اسم المورد موجود بالفعل');
        }

        // Validate English name (if provided)
        $supplier_name_en = trim($_POST['supplier_name_en'] ?? '');
        if (!empty($supplier_name_en)) {
            $stmt = $db->prepare("SELECT id FROM suppliers WHERE supplier_name_en = ? AND id != ?");
            $stmt->execute([$supplier_name_en, $supplier_id]);
            if ($stmt->fetch()) {
                throw new Exception('الاسم الإنجليزي موجود بالفعل');
            }
        }

        // Validate phones
        if (!empty($_POST['phones'])) {
            foreach ($_POST['phones'] as $phone) {
                if (!empty($phone['number'])) {
                    $stmt = $db->prepare("SELECT sp.*, s.supplier_name FROM supplier_phones sp JOIN suppliers s ON sp.supplier_id = s.id WHERE sp.phone_number = ? AND sp.supplier_id != ?");
                    $stmt->execute([$phone['number'], $supplier_id]);
                    if ($existing = $stmt->fetch()) {
                        throw new Exception('رقم الهاتف ' . $phone['number'] . ' مسجل لمورد: ' . $existing['supplier_name']);
                    }
                }
            }
        }

        // Update supplier
        $stmt = $db->prepare("
            UPDATE suppliers SET 
                supplier_name = ?, supplier_name_en = ?, supplier_type = ?, 
                payment_type = ?, credit_limit = ?, grace_period = ?, 
                return_policy = ?, notes = ?, instapay_number = ?, wallet_number = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $supplier_name,
            !empty($supplier_name_en) ? $supplier_name_en : null,
            $_POST['supplier_type'] ?? 'company',
            $_POST['payment_type'] ?? 'cash',
            floatval($_POST['credit_limit'] ?? 0),
            intval($_POST['grace_period'] ?? 0),
            trim($_POST['return_policy'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
            trim($_POST['instapay_number'] ?? '') ?: null,
            trim($_POST['wallet_number'] ?? '') ?: null,
            isset($_POST['is_active']) ? 1 : 0,
            $supplier_id
        ]);

        // Update phones - delete old, insert new
        $db->prepare("DELETE FROM supplier_phones WHERE supplier_id = ?")->execute([$supplier_id]);
        if (!empty($_POST['phones'])) {
            $phone_stmt = $db->prepare("INSERT INTO supplier_phones (supplier_id, country_code, phone_number, phone_type, is_primary) VALUES (?, ?, ?, ?, ?)");
            foreach ($_POST['phones'] as $i => $phone) {
                if (!empty($phone['number'])) {
                    $phone_stmt->execute([
                        $supplier_id,
                        $phone['country_code'] ?? '+20',
                        $phone['number'],
                        $phone['type'] ?? 'mobile',
                        $i === 0 ? 1 : 0
                    ]);
                }
            }
        }

        // Update addresses
        $db->prepare("DELETE FROM supplier_addresses WHERE supplier_id = ?")->execute([$supplier_id]);
        if (!empty($_POST['addresses'])) {
            $addr_stmt = $db->prepare("
                INSERT INTO supplier_addresses (supplier_id, address_type, building_number, floor_number, 
                    apartment_number, street_name, landmark, area_id, governorate_id, delivery_zone_id, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($_POST['addresses'] as $i => $addr) {
                if (!empty($addr['street_name'])) {
                    $addr_stmt->execute([
                        $supplier_id,
                        $addr['type'] ?? 'main',
                        $addr['building'] ?? null,
                        $addr['floor'] ?? null,
                        $addr['apartment'] ?? null,
                        $addr['street_name'],
                        $addr['landmark'] ?? null,
                        !empty($addr['area_id']) ? $addr['area_id'] : null,
                        !empty($addr['governorate_id']) ? $addr['governorate_id'] : null,
                        !empty($addr['zone_id']) ? $addr['zone_id'] : null,
                        $i === 0 ? 1 : 0
                    ]);
                }
            }
        }

        // Update contacts
        $db->prepare("DELETE FROM supplier_contacts WHERE supplier_id = ?")->execute([$supplier_id]);
        if (!empty($_POST['contacts'])) {
            $contact_stmt = $db->prepare("
                INSERT INTO supplier_contacts (supplier_id, contact_type, contact_name, job_title, phone, email, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($_POST['contacts'] as $i => $contact) {
                if (!empty($contact['name'])) {
                    $contact_stmt->execute([
                        $supplier_id,
                        $contact['type'] ?? 'manager',
                        $contact['name'],
                        $contact['job_title'] ?? null,
                        $contact['phone'] ?? null,
                        $contact['email'] ?? null,
                        $i === 0 ? 1 : 0
                    ]);
                }
            }
        }

        // Update bank accounts
        $db->prepare("DELETE FROM supplier_bank_accounts WHERE supplier_id = ?")->execute([$supplier_id]);
        if (!empty($_POST['bank_accounts'])) {
            $bank_stmt = $db->prepare("
                INSERT INTO supplier_bank_accounts (supplier_id, account_number, bank_name, iban, swift_code, branch_name, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($_POST['bank_accounts'] as $i => $bank) {
                if (!empty($bank['account_number']) && !empty($bank['bank_name'])) {
                    $bank_stmt->execute([
                        $supplier_id,
                        $bank['account_number'],
                        $bank['bank_name'],
                        $bank['iban'] ?? null,
                        $bank['swift'] ?? null,
                        $bank['branch'] ?? null,
                        $i === 0 ? 1 : 0
                    ]);
                }
            }
        }

        $db->commit();
        header("Location: index.php?updated=1");
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

$page_title = 'تعديل بيانات المورد - ' . $supplier['supplier_name'];
require_once __DIR__ . '/../../includes/sidebar.php';

// Helper function for select options
function selected_option($value, $current) {
    return $value === $current ? ' selected' : '';
}
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
        .section-title { color: var(--primary); font-weight: 600; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #eee; }
        .dynamic-row { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 10px; }
        .remove-btn { color: var(--danger); cursor: pointer; }
        .add-btn { color: var(--success); cursor: pointer; font-weight: 500; }
        .validation-error { color: var(--danger); font-size: 12px; margin-top: 4px; display: none; }
        .is-invalid { border-color: var(--danger) !important; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-pencil-square"></i> <?= $page_title ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">الموردين</a></li>
                            <li class="breadcrumb-item active">تعديل</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="view.php?id=<?= $supplier_id ?>" class="btn btn-info">
                        <i class="bi bi-eye"></i> عرض
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right"></i> رجوع
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="supplierForm" novalidate>
                <ul class="nav nav-tabs mb-4" id="supplierTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button"><i class="bi bi-info-circle"></i> البيانات الأساسية</button></li>
                    <li class="nav-item"><button class="nav-link" id="phones-tab" data-bs-toggle="tab" data-bs-target="#phones" type="button"><i class="bi bi-telephone"></i> الهواتف</button></li>
                    <li class="nav-item"><button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" type="button"><i class="bi bi-geo-alt"></i> العناوين</button></li>
                    <li class="nav-item"><button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button"><i class="bi bi-people"></i> الموظفين</button></li>
                    <li class="nav-item"><button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank" type="button"><i class="bi bi-bank"></i> الحسابات البنكية</button></li>
                    <li class="nav-item"><button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button"><i class="bi bi-gear"></i> الإعدادات</button></li>
                </ul>

                <div class="tab-content" id="supplierTabsContent">
                    <!-- Basic Info -->
                    <div class="tab-pane fade show active" id="basic" role="tabpanel">
                        <div class="card"><div class="card-body">
                            <h5 class="section-title"><i class="bi bi-info-circle"></i> البيانات الأساسية</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">اسم المورد <span class="text-danger">*</span></label>
                                    <input type="text" name="supplier_name" id="supplierName" class="form-control" value="<?= htmlspecialchars($supplier['supplier_name'], ENT_QUOTES) ?>" required>
                                    <div class="validation-error" id="nameError">اسم المورد مطلوب</div>
                                    <div class="validation-error" id="nameDuplicateError">اسم المورد موجود بالفعل</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">الاسم بالإنجليزي</label>
                                    <input type="text" name="supplier_name_en" id="supplierNameEn" class="form-control" value="<?= htmlspecialchars($supplier['supplier_name_en'] ?? '', ENT_QUOTES) ?>">
                                    <div class="validation-error" id="nameEnDuplicateError">الاسم الإنجليزي موجود بالفعل</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">تصنيف المورد <span class="text-danger">*</span></label>
                                    <select name="supplier_type" class="form-select" required>
                                        <option value="b2b"<?= selected_option('b2b', $supplier['supplier_type']) ?>>صيدلية (B2B)</option>
                                        <option value="private_office"<?= selected_option('private_office', $supplier['supplier_type']) ?>>مكتب خاص</option>
                                        <option value="warehouse"<?= selected_option('warehouse', $supplier['supplier_type']) ?>>مخزن</option>
                                        <option value="distributor"<?= selected_option('distributor', $supplier['supplier_type']) ?>>موزع</option>
                                        <option value="company"<?= selected_option('company', $supplier['supplier_type']) ?>>شركة</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">نوع التعامل</label>
                                    <select name="payment_type" class="form-select" id="paymentType">
                                        <option value="cash"<?= selected_option('cash', $supplier['payment_type']) ?>>نقدي</option>
                                        <option value="credit"<?= selected_option('credit', $supplier['payment_type']) ?>>آجل</option>
                                        <option value="cheque"<?= selected_option('cheque', $supplier['payment_type']) ?>>شيك</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3" id="creditLimitDiv" style="display:<?= $supplier['payment_type']!='cash'?'block':'none' ?>;">
                                    <label class="form-label">حد التعامل</label>
                                    <div class="input-group"><input type="number" name="credit_limit" class="form-control" step="0.01" value="<?= $supplier['credit_limit'] ?>"><span class="input-group-text">ج</span></div>
                                </div>
                                <div class="col-md-4 mb-3" id="gracePeriodDiv" style="display:<?= $supplier['payment_type']!='cash'?'block':'none' ?>;">
                                    <label class="form-label">فترة السماح (يوم)</label>
                                    <input type="number" name="grace_period" class="form-control" value="<?= $supplier['grace_period'] ?>">
                                </div>
                            </div>
                        </div></div>
                    </div>

                    <!-- Phones -->
                    <div class="tab-pane fade" id="phones" role="tabpanel">
                        <div class="card"><div class="card-body">
                            <h5 class="section-title"><i class="bi bi-telephone"></i> أرقام الهاتف</h5>
                            <div id="phonesContainer">
                                <?php foreach ($phones as $i => $phone): ?>
                                <div class="dynamic-row row">
                                    <div class="col-md-3"><select name="phones[<?= $i ?>][country_code]" class="form-select">
                                        <option value="+20"<?= selected_option('+20', $phone['country_code']) ?>>🇪🇬 +20</option>
                                        <option value="+966"<?= selected_option('+966', $phone['country_code']) ?>>🇸🇦 +966</option>
                                        <option value="+971"<?= selected_option('+971', $phone['country_code']) ?>>🇦🇪 +971</option>
                                        <option value="+965"<?= selected_option('+965', $phone['country_code']) ?>>🇰🇼 +965</option>
                                        <option value="+974"<?= selected_option('+974', $phone['country_code']) ?>>🇶🇦 +974</option>
                                    </select></div>
                                    <div class="col-md-3"><input type="text" name="phones[<?= $i ?>][number]" class="form-control phone-number" value="<?= htmlspecialchars($phone['phone_number'], ENT_QUOTES) ?>" placeholder="رقم الهاتف" data-index="<?= $i ?>">
                                        <div class="validation-error phone-duplicate-error" data-index="<?= $i ?>">رقم الهاتف مسجل لمورد آخر</div>
                                    </div>
                                    <div class="col-md-3"><select name="phones[<?= $i ?>][type]" class="form-select">
                                        <option value="mobile"<?= selected_option('mobile', $phone['phone_type']) ?>>موبايل</option>
                                        <option value="landline"<?= selected_option('landline', $phone['phone_type']) ?>>أرضي</option>
                                        <option value="fax"<?= selected_option('fax', $phone['phone_type']) ?>>فاكس</option>
                                        <option value="whatsapp"<?= selected_option('whatsapp', $phone['phone_type']) ?>>واتساب</option>
                                    </select></div>
                                    <div class="col-md-3"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i> حذف</span></div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($phones)): ?>
                                <div class="dynamic-row row">
                                    <div class="col-md-3"><select name="phones[0][country_code]" class="form-select"><option value="+20" selected>🇪🇬 +20</option><option value="+966">🇸🇦 +966</option><option value="+971">🇦🇪 +971</option><option value="+965">🇰🇼 +965</option><option value="+974">🇶🇦 +974</option></select></div>
                                    <div class="col-md-3"><input type="text" name="phones[0][number]" class="form-control phone-number" placeholder="رقم الهاتف" data-index="0">
                                        <div class="validation-error phone-duplicate-error" data-index="0">رقم الهاتف مسجل لمورد آخر</div>
                                    </div>
                                    <div class="col-md-3"><select name="phones[0][type]" class="form-select"><option value="mobile">موبايل</option><option value="landline">أرضي</option><option value="fax">فاكس</option><option value="whatsapp">واتساب</option></select></div>
                                    <div class="col-md-3"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i> حذف</span></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3"><span class="add-btn" onclick="addPhone()"><i class="bi bi-plus-circle"></i> إضافة رقم هاتف</span></div>
                        </div></div>
                    </div>

                    <!-- Addresses -->
                    <div class="tab-pane fade" id="addresses" role="tabpanel">
                        <div class="card"><div class="card-body">
                            <h5 class="section-title"><i class="bi bi-geo-alt"></i> العناوين</h5>
                            <div id="addressesContainer">
                                <?php foreach ($addresses as $i => $addr): ?>
                                <div class="dynamic-row"><div class="row">
                                    <div class="col-md-3 mb-2"><select name="addresses[<?= $i ?>][type]" class="form-select">
                                        <option value="main"<?= selected_option('main', $addr['address_type']) ?>>رئيسي</option>
                                        <option value="warehouse"<?= selected_option('warehouse', $addr['address_type']) ?>>مخزن</option>
                                        <option value="branch"<?= selected_option('branch', $addr['address_type']) ?>>فرع</option>
                                        <option value="other"<?= selected_option('other', $addr['address_type']) ?>>أخرى</option>
                                    </select></div>
                                    <div class="col-md-9 mb-2"><input type="text" name="addresses[<?= $i ?>][street_name]" class="form-control" value="<?= htmlspecialchars($addr['street_name'] ?? '', ENT_QUOTES) ?>" placeholder="اسم الشارع"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[<?= $i ?>][building]" class="form-control" value="<?= htmlspecialchars($addr['building_number'] ?? '', ENT_QUOTES) ?>" placeholder="رقم العمارة"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[<?= $i ?>][floor]" class="form-control" value="<?= htmlspecialchars($addr['floor_number'] ?? '', ENT_QUOTES) ?>" placeholder="الدور"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[<?= $i ?>][apartment]" class="form-control" value="<?= htmlspecialchars($addr['apartment_number'] ?? '', ENT_QUOTES) ?>" placeholder="الشقة"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[<?= $i ?>][landmark]" class="form-control" value="<?= htmlspecialchars($addr['landmark'] ?? '', ENT_QUOTES) ?>" placeholder="علامة مميزة"></div>
                                    <div class="col-md-4 mb-2"><select name="addresses[<?= $i ?>][governorate_id]" class="form-select"><option value="">-- المحافظة --</option><?php foreach ($governorates as $gov): ?><option value="<?= $gov['id'] ?>"<?= selected_option($gov['id'], $addr['governorate_id']) ?>><?= htmlspecialchars($gov['governorate_name_ar']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-4 mb-2"><select name="addresses[<?= $i ?>][area_id]" class="form-select"><option value="">-- المنطقة --</option><?php foreach ($areas as $area): ?><option value="<?= $area['id'] ?>"<?= selected_option($area['id'], $addr['area_id']) ?>><?= htmlspecialchars($area['area_name_ar']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-3 mb-2"><select name="addresses[<?= $i ?>][zone_id]" class="form-select"><option value="">-- Zone --</option><?php foreach ($zones as $zone): ?><option value="<?= $zone['id'] ?>"<?= selected_option($zone['id'], $addr['delivery_zone_id']) ?>><?= htmlspecialchars($zone['zone_name_ar']) ?> (<?= $zone['delivery_fee'] ?> ج)</option><?php endforeach; ?></select></div>
                                    <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
                                </div></div>
                                <?php endforeach; ?>
                                <?php if (empty($addresses)): ?>
                                <div class="dynamic-row"><div class="row">
                                    <div class="col-md-3 mb-2"><select name="addresses[0][type]" class="form-select"><option value="main">رئيسي</option><option value="warehouse">مخزن</option><option value="branch">فرع</option><option value="other">أخرى</option></select></div>
                                    <div class="col-md-9 mb-2"><input type="text" name="addresses[0][street_name]" class="form-control" placeholder="اسم الشارع"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[0][building]" class="form-control" placeholder="رقم العمارة"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[0][floor]" class="form-control" placeholder="الدور"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[0][apartment]" class="form-control" placeholder="الشقة"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="addresses[0][landmark]" class="form-control" placeholder="علامة مميزة"></div>
                                    <div class="col-md-4 mb-2"><select name="addresses[0][governorate_id]" class="form-select"><option value="">-- المحافظة --</option><?php foreach ($governorates as $gov): ?><option value="<?= $gov['id'] ?>"><?= htmlspecialchars($gov['governorate_name_ar']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-4 mb-2"><select name="addresses[0][area_id]" class="form-select"><option value="">-- المنطقة --</option><?php foreach ($areas as $area): ?><option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['area_name_ar']) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-3 mb-2"><select name="addresses[0][zone_id]" class="form-select"><option value="">-- Zone --</option><?php foreach ($zones as $zone): ?><option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['zone_name_ar']) ?> (<?= $zone['delivery_fee'] ?> ج)</option><?php endforeach; ?></select></div>
                                    <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
                                </div></div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3"><span class="add-btn" onclick="addAddress()"><i class="bi bi-plus-circle"></i> إضافة عنوان</span></div>
                        </div></div>
                    </div>

                    <!-- Contacts -->
                    <div class="tab-pane fade" id="contacts" role="tabpanel">
                        <div class="card"><div class="card-body">
                            <h5 class="section-title"><i class="bi bi-people"></i> الموظفين والمناديب</h5>
                            <div id="contactsContainer">
                                <?php foreach ($contacts as $i => $contact): ?>
                                <div class="dynamic-row row">
                                    <div class="col-md-3 mb-2"><select name="contacts[<?= $i ?>][type]" class="form-select">
                                        <option value="manager"<?= selected_option('manager', $contact['contact_type']) ?>>مدير</option>
                                        <option value="representative"<?= selected_option('representative', $contact['contact_type']) ?>>مندوب</option>
                                        <option value="distributor"<?= selected_option('distributor', $contact['contact_type']) ?>>موزع</option>
                                        <option value="other"<?= selected_option('other', $contact['contact_type']) ?>>أخرى</option>
                                    </select></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="contacts[<?= $i ?>][name]" class="form-control" value="<?= htmlspecialchars($contact['contact_name'], ENT_QUOTES) ?>" placeholder="الاسم"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="contacts[<?= $i ?>][job_title]" class="form-control" value="<?= htmlspecialchars($contact['job_title'] ?? '', ENT_QUOTES) ?>" placeholder="المسمى الوظيفي"></div>
                                    <div class="col-md-2 mb-2"><input type="text" name="contacts[<?= $i ?>][phone]" class="form-control" value="<?= htmlspecialchars($contact['phone'] ?? '', ENT_QUOTES) ?>" placeholder="الهاتف"></div>
                                    <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($contacts)): ?>
                                <div class="dynamic-row row">
                                    <div class="col-md-3 mb-2"><select name="contacts[0][type]" class="form-select"><option value="manager">مدير</option><option value="representative">مندوب</option><option value="distributor">موزع</option><option value="other">أخرى</option></select></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="contacts[0][name]" class="form-control" placeholder="الاسم"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="contacts[0][job_title]" class="form-control" placeholder="المسمى الوظيفي"></div>
                                    <div class="col-md-2 mb-2"><input type="text" name="contacts[0][phone]" class="form-control" placeholder="الهاتف"></div>
                                    <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3"><span class="add-btn" onclick="addContact()"><i class="bi bi-plus-circle"></i> إضافة موظف</span></div>
                        </div></div>
                    </div>

                    <!-- Bank -->
                    <div class="tab-pane fade" id="bank" role="tabpanel">
                        <div class="card"><div class="card-body">
                            <h5 class="section-title"><i class="bi bi-bank"></i> الحسابات البنكية</h5>
                            <div id="bankContainer">
                                <?php foreach ($bank_accounts as $i => $bank): ?>
                                <div class="dynamic-row row">
                                    <div class="col-md-3 mb-2"><input type="text" name="bank_accounts[<?= $i ?>][bank_name]" class="form-control" value="<?= htmlspecialchars($bank['bank_name'], ENT_QUOTES) ?>" placeholder="اسم البنك"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="bank_accounts[<?= $i ?>][account_number]" class="form-control" value="<?= htmlspecialchars($bank['account_number'], ENT_QUOTES) ?>" placeholder="رقم الحساب"></div>
                                    <div class="col-md-2 mb-2"><input type="text" name="bank_accounts[<?= $i ?>][iban]" class="form-control" value="<?= htmlspecialchars($bank['iban'] ?? '', ENT_QUOTES) ?>" placeholder="IBAN"></div>
                                    <div class="col-md-2 mb-2"><input type="text" name="bank_accounts[<?= $i ?>][swift]" class="form-control" value="<?= htmlspecialchars($bank['swift_code'] ?? '', ENT_QUOTES) ?>" placeholder="Swift"></div>
                                    <div class="col-md-1 mb-2"><input type="text" name="bank_accounts[<?= $i ?>][branch]" class="form-control" value="<?= htmlspecialchars($bank['branch_name'] ?? '', ENT_QUOTES) ?>" placeholder="فرع"></div>
                                    <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($bank_accounts)): ?>
                                <div class="dynamic-row row">
                                    <div class="col-md-3 mb-2"><input type="text" name="bank_accounts[0][bank_name]" class="form-control" placeholder="اسم البنك"></div>
                                    <div class="col-md-3 mb-2"><input type="text" name="bank_accounts[0][account_number]" class="form-control" placeholder="رقم الحساب"></div>
                                    <div class="col-md-2 mb-2"><input type="text" name="bank_accounts[0][iban]" class="form-control" placeholder="IBAN"></div>
                                    <div class="col-md-2 mb-2"><input type="text" name="bank_accounts[0][swift]" class="form-control" placeholder="Swift"></div>
                                    <div class="col-md-1 mb-2"><input type="text" name="bank_accounts[0][branch]" class="form-control" placeholder="فرع"></div>
                                    <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3"><span class="add-btn" onclick="addBank()"><i class="bi bi-plus-circle"></i> إضافة حساب بنكي</span></div>
                        </div></div>
                    </div>

                    <!-- Settings -->
                    <div class="tab-pane fade" id="settings" role="tabpanel">
                        <div class="card"><div class="card-body">
                            <h5 class="section-title"><i class="bi bi-gear"></i> الإعدادات</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">رقم الإنستاباي</label><input type="text" name="instapay_number" class="form-control" value="<?= htmlspecialchars($supplier['instapay_number'] ?? '', ENT_QUOTES) ?>"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">رقم المحفظة</label><input type="text" name="wallet_number" class="form-control" value="<?= htmlspecialchars($supplier['wallet_number'] ?? '', ENT_QUOTES) ?>"></div>
                                <div class="col-md-12 mb-3"><label class="form-label">سياسة المرتجعات</label><textarea name="return_policy" class="form-control" rows="3"><?= htmlspecialchars($supplier['return_policy'] ?? '') ?></textarea></div>
                                <div class="col-md-12 mb-3"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($supplier['notes'] ?? '') ?></textarea></div>
                                <div class="col-md-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $supplier['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="isActive">مورد نشط</label></div></div>
                            </div>
                        </div></div>
                    </div>
                </div>

                <div class="card mt-4"><div class="card-body text-center">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"><i class="bi bi-check-lg"></i> حفظ التعديلات</button>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2"><i class="bi bi-x-lg"></i> إلغاء</a>
                </div></div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const SUPPLIER_ID = <?= $supplier_id ?>;
        const baseUrl = window.location.protocol + '//' + window.location.host + '/nile-center-system';

        document.getElementById('paymentType').addEventListener('change', function() {
            const creditDiv = document.getElementById('creditLimitDiv');
            const graceDiv = document.getElementById('gracePeriodDiv');
            if (this.value === 'cash') { creditDiv.style.display = 'none'; graceDiv.style.display = 'none'; }
            else { creditDiv.style.display = 'block'; graceDiv.style.display = 'block'; }
        });

        async function validateSupplierName(name) {
            if (!name.trim()) return false;
            try {
                const response = await fetch(`${baseUrl}/api/api_validate_supplier.php?action=check_name&name=${encodeURIComponent(name)}&exclude_id=${SUPPLIER_ID}`);
                const data = await response.json();
                return data.valid;
            } catch (e) { return true; }
        }

        async function validateSupplierNameEn(nameEn) {
            if (!nameEn.trim()) return true;
            try {
                const response = await fetch(`${baseUrl}/api/api_validate_supplier.php?action=check_name_en&name_en=${encodeURIComponent(nameEn)}&exclude_id=${SUPPLIER_ID}`);
                const data = await response.json();
                return data.valid;
            } catch (e) { return true; }
        }

        async function validatePhone(phone, index) {
            if (!phone.trim()) return true;
            try {
                const response = await fetch(`${baseUrl}/api/api_validate_supplier.php?action=check_phone&phone=${encodeURIComponent(phone)}&exclude_id=${SUPPLIER_ID}`);
                const data = await response.json();
                const errorDiv = document.querySelector(`.phone-duplicate-error[data-index="${index}"]`);
                if (!data.valid && errorDiv) {
                    errorDiv.textContent = data.message;
                    errorDiv.style.display = 'block';
                    return false;
                } else if (errorDiv) {
                    errorDiv.style.display = 'none';
                }
                return true;
            } catch (e) { return true; }
        }

        document.getElementById('supplierName').addEventListener('blur', async function() {
            const name = this.value.trim();
            const errorDiv = document.getElementById('nameError');
            const duplicateDiv = document.getElementById('nameDuplicateError');
            if (!name) { errorDiv.style.display = 'block'; this.classList.add('is-invalid'); return; }
            errorDiv.style.display = 'none';
            const isValid = await validateSupplierName(name);
            if (!isValid) { duplicateDiv.style.display = 'block'; this.classList.add('is-invalid'); }
            else { duplicateDiv.style.display = 'none'; this.classList.remove('is-invalid'); }
        });

        document.getElementById('supplierNameEn').addEventListener('blur', async function() {
            const nameEn = this.value.trim();
            if (!nameEn) return;
            const isValid = await validateSupplierNameEn(nameEn);
            const duplicateDiv = document.getElementById('nameEnDuplicateError');
            if (!isValid) { duplicateDiv.style.display = 'block'; this.classList.add('is-invalid'); }
            else { duplicateDiv.style.display = 'none'; this.classList.remove('is-invalid'); }
        });

        document.addEventListener('blur', async function(e) {
            if (e.target.classList.contains('phone-number')) {
                const phone = e.target.value.trim();
                const index = e.target.dataset.index;
                if (phone) await validatePhone(phone, index);
            }
        }, true);

        document.getElementById('supplierForm').addEventListener('submit', async function(e) {
            const name = document.getElementById('supplierName').value.trim();
            if (!name) {
                e.preventDefault(); document.getElementById('nameError').style.display = 'block';
                document.getElementById('supplierName').classList.add('is-invalid');
                document.getElementById('supplierName').focus(); return false;
            }
            const isNameValid = await validateSupplierName(name);
            if (!isNameValid) {
                e.preventDefault(); document.getElementById('nameDuplicateError').style.display = 'block';
                document.getElementById('supplierName').classList.add('is-invalid');
                document.getElementById('supplierName').focus(); return false;
            }
            const phoneInputs = document.querySelectorAll('.phone-number');
            for (let input of phoneInputs) {
                const phone = input.value.trim(); const index = input.dataset.index;
                if (phone) {
                    const isValid = await validatePhone(phone, index);
                    if (!isValid) { e.preventDefault(); input.classList.add('is-invalid'); input.focus(); return false; }
                }
            }
            return true;
        });

        let phoneCount = <?= count($phones) ?: 1 ?>;
        let addressCount = <?= count($addresses) ?: 1 ?>;
        let contactCount = <?= count($contacts) ?: 1 ?>;
        let bankCount = <?= count($bank_accounts) ?: 1 ?>;

        function addPhone() {
            const container = document.getElementById('phonesContainer');
            const html = `<div class="dynamic-row row">
                <div class="col-md-3"><select name="phones[${phoneCount}][country_code]" class="form-select"><option value="+20">🇪🇬 +20</option><option value="+966">🇸🇦 +966</option><option value="+971">🇦🇪 +971</option><option value="+965">🇰🇼 +965</option><option value="+974">🇶🇦 +974</option></select></div>
                <div class="col-md-3"><input type="text" name="phones[${phoneCount}][number]" class="form-control phone-number" placeholder="رقم الهاتف" data-index="${phoneCount}"><div class="validation-error phone-duplicate-error" data-index="${phoneCount}">رقم الهاتف مسجل لمورد آخر</div></div>
                <div class="col-md-3"><select name="phones[${phoneCount}][type]" class="form-select"><option value="mobile">موبايل</option><option value="landline">أرضي</option><option value="fax">فاكس</option><option value="whatsapp">واتساب</option></select></div>
                <div class="col-md-3"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i> حذف</span></div></div>`;
            container.insertAdjacentHTML('beforeend', html); phoneCount++;
        }
        function addAddress() {
            const container = document.getElementById('addressesContainer');
            const html = `<div class="dynamic-row"><div class="row">
                <div class="col-md-3 mb-2"><select name="addresses[${addressCount}][type]" class="form-select"><option value="main">رئيسي</option><option value="warehouse">مخزن</option><option value="branch">فرع</option><option value="other">أخرى</option></select></div>
                <div class="col-md-9 mb-2"><input type="text" name="addresses[${addressCount}][street_name]" class="form-control" placeholder="اسم الشارع"></div>
                <div class="col-md-3 mb-2"><input type="text" name="addresses[${addressCount}][building]" class="form-control" placeholder="رقم العمارة"></div>
                <div class="col-md-3 mb-2"><input type="text" name="addresses[${addressCount}][floor]" class="form-control" placeholder="الدور"></div>
                <div class="col-md-3 mb-2"><input type="text" name="addresses[${addressCount}][apartment]" class="form-control" placeholder="الشقة"></div>
                <div class="col-md-3 mb-2"><input type="text" name="addresses[${addressCount}][landmark]" class="form-control" placeholder="علامة مميزة"></div>
                <div class="col-md-4 mb-2"><select name="addresses[${addressCount}][governorate_id]" class="form-select"><option value="">-- المحافظة --</option><?php foreach ($governorates as $gov): ?><option value="<?= $gov['id'] ?>"><?= htmlspecialchars($gov['governorate_name_ar']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4 mb-2"><select name="addresses[${addressCount}][area_id]" class="form-select"><option value="">-- المنطقة --</option><?php foreach ($areas as $area): ?><option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['area_name_ar']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb-2"><select name="addresses[${addressCount}][zone_id]" class="form-select"><option value="">-- Zone --</option><?php foreach ($zones as $zone): ?><option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['zone_name_ar']) ?> (<?= $zone['delivery_fee'] ?> ج)</option><?php endforeach; ?></select></div>
                <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
            </div></div>`;
            container.insertAdjacentHTML('beforeend', html); addressCount++;
        }
        function addContact() {
            const container = document.getElementById('contactsContainer');
            const html = `<div class="dynamic-row row">
                <div class="col-md-3 mb-2"><select name="contacts[${contactCount}][type]" class="form-select"><option value="manager">مدير</option><option value="representative">مندوب</option><option value="distributor">موزع</option><option value="other">أخرى</option></select></div>
                <div class="col-md-3 mb-2"><input type="text" name="contacts[${contactCount}][name]" class="form-control" placeholder="الاسم"></div>
                <div class="col-md-3 mb-2"><input type="text" name="contacts[${contactCount}][job_title]" class="form-control" placeholder="المسمى الوظيفي"></div>
                <div class="col-md-2 mb-2"><input type="text" name="contacts[${contactCount}][phone]" class="form-control" placeholder="الهاتف"></div>
                <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
            </div>`;
            container.insertAdjacentHTML('beforeend', html); contactCount++;
        }
        function addBank() {
            const container = document.getElementById('bankContainer');
            const html = `<div class="dynamic-row row">
                <div class="col-md-3 mb-2"><input type="text" name="bank_accounts[${bankCount}][bank_name]" class="form-control" placeholder="اسم البنك"></div>
                <div class="col-md-3 mb-2"><input type="text" name="bank_accounts[${bankCount}][account_number]" class="form-control" placeholder="رقم الحساب"></div>
                <div class="col-md-2 mb-2"><input type="text" name="bank_accounts[${bankCount}][iban]" class="form-control" placeholder="IBAN"></div>
                <div class="col-md-2 mb-2"><input type="text" name="bank_accounts[${bankCount}][swift]" class="form-control" placeholder="Swift"></div>
                <div class="col-md-1 mb-2"><input type="text" name="bank_accounts[${bankCount}][branch]" class="form-control" placeholder="فرع"></div>
                <div class="col-md-1 mb-2 text-end"><span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span></div>
            </div>`;
            container.insertAdjacentHTML('beforeend', html); bankCount++;
        }
        function removeRow(btn) { btn.closest('.dynamic-row').remove(); }
    </script>
</body>
</html>