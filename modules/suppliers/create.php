<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'إضافة مورد جديد';

// ===== AJAX Live Validation endpoint =====
if (isset($_GET['ajax_validate']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');

    $field = $_GET['field'] ?? '';
    $value = trim($_GET['value'] ?? '');

    if (empty($field) || empty($value)) {
        echo json_encode(['valid' => true, 'message' => '']);
        exit;
    }

    $valid = true;
    $message = '';

    switch ($field) {
        case 'supplier_name':
            $stmt = $db->prepare("SELECT id FROM suppliers WHERE supplier_name = ? AND (deleted_at IS NULL OR deleted_at = '')");
            $stmt->execute([$value]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ اسم المورد موجود بالفعل'; }
            break;

        case 'supplier_name_en':
            $stmt = $db->prepare("SELECT id FROM suppliers WHERE supplier_name_en = ? AND supplier_name_en IS NOT NULL AND supplier_name_en != '' AND (deleted_at IS NULL OR deleted_at = '')");
            $stmt->execute([$value]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ الاسم الإنجليزي موجود بالفعل'; }
            break;

        case 'phone':
            $country_code = trim($_GET['country_code'] ?? '+20');
            $full_phone = $country_code . $value;
            $stmt = $db->prepare("SELECT sp.*, s.supplier_name FROM supplier_phones sp JOIN suppliers s ON sp.supplier_id = s.id WHERE CONCAT(sp.country_code, sp.phone_number) = ? AND (s.deleted_at IS NULL OR s.deleted_at = '')");
            $stmt->execute([$full_phone]);
            if ($existing = $stmt->fetch()) { $valid = false; $message = '⚠️ رقم الهاتف مسجل لمورد: ' . $existing['supplier_name']; }
            break;
    }

    echo json_encode(['valid' => $valid, 'message' => $message]);
    exit;
}

// Helper functions
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
$governorates = $db->query("SELECT * FROM governorates WHERE is_active = 1 ORDER BY governorate_name_ar")->fetchAll();
$areas = $db->query("SELECT * FROM areas WHERE is_active = 1 ORDER BY area_name_ar")->fetchAll();
$zones = $db->query("SELECT * FROM delivery_zones WHERE is_active = 1 ORDER BY zone_name_ar")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $errors = [];

        $supplier_name = trim($_POST['supplier_name'] ?? '');
        if (empty($supplier_name)) {
            $errors[] = 'اسم المورد مطلوب';
        } else {
            $stmt = $db->prepare("SELECT id FROM suppliers WHERE supplier_name = ? AND (deleted_at IS NULL OR deleted_at = '')");
            $stmt->execute([$supplier_name]);
            if ($stmt->fetch()) {
                $errors[] = 'اسم المورد موجود بالفعل';
            }
        }

        $supplier_name_en = trim($_POST['supplier_name_en'] ?? '');
        if (!empty($supplier_name_en)) {
            $stmt = $db->prepare("SELECT id FROM suppliers WHERE supplier_name_en = ? AND supplier_name_en IS NOT NULL AND supplier_name_en != '' AND (deleted_at IS NULL OR deleted_at = '')");
            $stmt->execute([$supplier_name_en]);
            if ($stmt->fetch()) {
                $errors[] = 'الاسم الإنجليزي موجود بالفعل';
            }
        }

        $supplier_type = $_POST['supplier_type'] ?? '';
        if (empty($supplier_type)) {
            $errors[] = 'تصنيف المورد مطلوب';
        }

        $payment_type = $_POST['payment_type'] ?? '';
        if (empty($payment_type)) {
            $errors[] = 'نوع التعامل مطلوب';
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        // Generate sequential code (1, 2, 3...)
        $last_code_result = $db->query("SELECT MAX(CAST(supplier_code AS UNSIGNED)) as max_code FROM suppliers WHERE supplier_code REGEXP '^[0-9]+$")->fetch();
        $max_code = isset($last_code_result['max_code']) ? intval($last_code_result['max_code']) : 0;
        $new_code = strval($max_code + 1);

        // Insert supplier
        $stmt = $db->prepare("
            INSERT INTO suppliers (supplier_code, supplier_name, supplier_name_en, supplier_type, 
                payment_type, credit_limit, grace_period, return_policy, notes, 
                instapay_number, wallet_number, is_active, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $new_code,
            $supplier_name,
            !empty($supplier_name_en) ? $supplier_name_en : null,
            $supplier_type,
            $payment_type,
            floatval($_POST['credit_limit'] ?? 0),
            intval($_POST['grace_period'] ?? 0),
            trim($_POST['return_policy'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
            trim($_POST['instapay_number'] ?? '') ?: null,
            trim($_POST['wallet_number'] ?? '') ?: null,
            isset($_POST['is_active']) ? 1 : 0,
            $_SESSION['user_id'] ?? 1
        ]);

        $supplier_id = $db->lastInsertId();

        // Insert phones
        if (!empty($_POST['phones'])) {
            $phone_stmt = $db->prepare("INSERT INTO supplier_phones (supplier_id, country_code, phone_number, phone_type, is_primary) VALUES (?, ?, ?, ?, ?)");
            $primary_set = false;
            foreach ($_POST['phones'] as $index => $phone) {
                if (!empty($phone['number'])) {
                    $phone_stmt->execute([
                        $supplier_id,
                        $phone['country_code'] ?? '+20',
                        $phone['number'],
                        $phone['type'] ?? 'mobile',
                        !$primary_set ? 1 : 0
                    ]);
                    $primary_set = true;
                }
            }
        }

        // Insert addresses
        if (!empty($_POST['addresses'])) {
            $addr_stmt = $db->prepare("
                INSERT INTO supplier_addresses (supplier_id, address_type, building_number, floor_number, 
                    apartment_number, street_name, landmark, area_id, governorate_id, delivery_zone_id, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $primary_set = false;
            foreach ($_POST['addresses'] as $index => $addr) {
                if (!empty($addr['street_name'])) {
                    $addr_stmt->execute([
                        $supplier_id,
                        $addr['type'] ?? 'main',
                        $addr['building'] ?? null,
                        $addr['floor'] ?? null,
                        $addr['apartment'] ?? null,
                        $addr['street_name'],
                        $addr['landmark'] ?? null,
                        !empty($addr['area_id']) ? intval($addr['area_id']) : null,
                        !empty($addr['governorate_id']) ? intval($addr['governorate_id']) : null,
                        !empty($addr['zone_id']) ? intval($addr['zone_id']) : null,
                        !$primary_set ? 1 : 0
                    ]);
                    $primary_set = true;
                }
            }
        }

        // Insert contacts
        if (!empty($_POST['contacts'])) {
            $contact_stmt = $db->prepare("
                INSERT INTO supplier_contacts (supplier_id, contact_type, contact_name, job_title, phone, email, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $primary_set = false;
            foreach ($_POST['contacts'] as $contact) {
                if (!empty($contact['name'])) {
                    $contact_stmt->execute([
                        $supplier_id,
                        $contact['type'] ?? 'manager',
                        $contact['name'],
                        $contact['job_title'] ?? null,
                        $contact['phone'] ?? null,
                        $contact['email'] ?? null,
                        !$primary_set ? 1 : 0
                    ]);
                    $primary_set = true;
                }
            }
        }

        // Insert bank accounts
        if (!empty($_POST['bank_accounts'])) {
            $bank_stmt = $db->prepare("
                INSERT INTO supplier_bank_accounts (supplier_id, account_number, bank_name, iban, swift_code, branch_name, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $primary_set = false;
            foreach ($_POST['bank_accounts'] as $bank) {
                if (!empty($bank['account_number']) && !empty($bank['bank_name'])) {
                    $bank_stmt->execute([
                        $supplier_id,
                        $bank['account_number'],
                        $bank['bank_name'],
                        $bank['iban'] ?? null,
                        $bank['swift'] ?? null,
                        $bank['branch'] ?? null,
                        !$primary_set ? 1 : 0
                    ]);
                    $primary_set = true;
                }
            }
        }

        // Initialize balance
        $db->prepare("INSERT INTO supplier_balances (supplier_id, balance, total_purchases, total_payments, total_returns) VALUES (?, 0, 0, 0, 0)")
            ->execute([$supplier_id]);

        $db->commit();
        header("Location: index.php?success=1");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
    }
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
        :root { --primary: #667eea; --secondary: #764ba2; --success: #198754; --warning: #ffc107; --danger: #dc3545; }
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; position: fixed; right: 0; top: 0; width: 260px; z-index: 1000; }
        .main-content { margin-right: 260px; padding: 20px; }
        .card { border: none; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border: none; }
        .section-title { color: var(--primary); font-weight: 700; border-right: 4px solid var(--primary); padding-right: 10px; margin-bottom: 20px; }
        .phone-row, .address-row, .contact-row, .bank-row { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; position: relative; }
        .remove-btn { position: absolute; left: 10px; top: 10px; color: var(--danger); cursor: pointer; }
        .country-select { display: flex; align-items: center; }
        .country-select select { border-radius: 0 5px 5px 0; }
        .country-select .flag { padding: 8px 12px; background: #e9ecef; border: 1px solid #ced4da; border-left: none; border-radius: 5px 0 0 5px; }
        .form-label { font-weight: 600; color: #555; }
        .nav-tabs .nav-link { color: #666; border: none; padding: 15px 20px; }
        .nav-tabs .nav-link.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: none; }
        .validation-msg { font-size: 12px; margin-top: 4px; min-height: 18px; }
        .validation-msg.text-danger { color: var(--danger); }
        .validation-msg.text-success { color: var(--success); }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; } .main-content { margin-right: 0; } }
    </style>
</head>
<body>
    <?= $sidebar ?? '' ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-truck"></i> <?= $page_title ?></h2>
                <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="supplierForm">
                <div class="card">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs" id="supplierTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" id="basic-tab" data-bs-toggle="tab" href="#basic" role="tab"><i class="bi bi-info-circle"></i> البيانات الأساسية</a></li>
                            <li class="nav-item"><a class="nav-link" id="phones-tab" data-bs-toggle="tab" href="#phones" role="tab"><i class="bi bi-telephone"></i> الهواتف</a></li>
                            <li class="nav-item"><a class="nav-link" id="addresses-tab" data-bs-toggle="tab" href="#addresses" role="tab"><i class="bi bi-geo-alt"></i> العناوين</a></li>
                            <li class="nav-item"><a class="nav-link" id="contacts-tab" data-bs-toggle="tab" href="#contacts" role="tab"><i class="bi bi-people"></i> الموظفين</a></li>
                            <li class="nav-item"><a class="nav-link" id="bank-tab" data-bs-toggle="tab" href="#bank" role="tab"><i class="bi bi-bank"></i> الحسابات البنكية</a></li>
                            <li class="nav-item"><a class="nav-link" id="settings-tab" data-bs-toggle="tab" href="#settings" role="tab"><i class="bi bi-gear"></i> الإعدادات</a></li>
                        </ul>
                    </div>

                    <div class="card-body">
                        <div class="tab-content" id="supplierTabContent">

                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <h5 class="section-title">معلومات المورد الأساسية</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الكود التسلسلي</label>
                                            <input type="text" class="form-control" value="يتم توليده تلقائياً" readonly style="background: #e9ecef;">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">تصنيف المورد <span class="text-danger">*</span></label>
                                            <select name="supplier_type" class="form-select" required>
                                                <option value="">-- اختر التصنيف --</option>
                                                <option value="b2b" <?= oldSelect('supplier_type', 'b2b') ?>>صيدلية (B2B)</option>
                                                <option value="private_office" <?= oldSelect('supplier_type', 'private_office') ?>>مكتب خاص</option>
                                                <option value="warehouse" <?= oldSelect('supplier_type', 'warehouse') ?>>مخزن</option>
                                                <option value="distributor" <?= oldSelect('supplier_type', 'distributor') ?>>موزع</option>
                                                <option value="company" <?= oldSelect('supplier_type', 'company') ?>>شركة</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم المورد <span class="text-danger">*</span></label>
                                            <input type="text" name="supplier_name" class="form-control" required value="<?= old('supplier_name') ?>" placeholder="اسم المورد" onblur="validateField(this, 'supplier_name')">
                                            <div class="validation-msg"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الاسم بالإنجليزي</label>
                                            <input type="text" name="supplier_name_en" class="form-control" value="<?= old('supplier_name_en') ?>" placeholder="Supplier Name" onblur="validateField(this, 'supplier_name_en')">
                                            <div class="validation-msg"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">نوع التعامل <span class="text-danger">*</span></label>
                                            <select name="payment_type" id="payment_type" class="form-select" required onchange="toggleCreditFields()">
                                                <option value="">-- اختر --</option>
                                                <option value="cash" <?= oldSelect('payment_type', 'cash') ?>>نقدي</option>
                                                <option value="credit" <?= oldSelect('payment_type', 'credit') ?>>آجل</option>
                                                <option value="cheque" <?= oldSelect('payment_type', 'cheque') ?>>شيك</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4" id="creditLimitDiv" style="display:<?= old('payment_type') == 'cash' ? 'none' : 'block' ?>;">
                                        <div class="mb-3">
                                            <label class="form-label">حد التعامل</label>
                                            <div class="input-group">
                                                <input type="number" name="credit_limit" class="form-control" step="0.01" value="<?= old('credit_limit', '0') ?>">
                                                <span class="input-group-text">ج</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4" id="gracePeriodDiv" style="display:<?= old('payment_type') == 'cash' ? 'none' : 'block' ?>;">
                                        <div class="mb-3">
                                            <label class="form-label">فترة السماح (يوم)</label>
                                            <input type="number" name="grace_period" class="form-control" value="<?= old('grace_period', '0') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Phones Tab -->
                            <div class="tab-pane fade" id="phones" role="tabpanel">
                                <h5 class="section-title">أرقام الهاتف</h5>
                                <div id="phonesContainer">
                                    <div class="phone-row">
                                        <span class="remove-btn" onclick="this.closest('.phone-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label class="form-label">كود الدولة</label>
                                                <div class="country-select">
                                                    <span class="flag">🇪🇬</span>
                                                    <select name="phones[0][country_code]" class="form-select country-code-select" onchange="updateFlag(this)" id="country_code_0">
                                                        <option value="+20" data-flag="🇪🇬" selected>مصر (+20)</option>
                                                        <option value="+966" data-flag="🇸🇦">السعودية (+966)</option>
                                                        <option value="+971" data-flag="🇦🇪">الإمارات (+971)</option>
                                                        <option value="+965" data-flag="🇰🇼">الكويت (+965)</option>
                                                        <option value="+974" data-flag="🇶🇦">قطر (+974)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">رقم الهاتف</label>
                                                <input type="text" name="phones[0][number]" class="form-control" placeholder="01xxxxxxxxx" onblur="validatePhoneField(this, 0)">
                                                <div class="validation-msg" id="phone_msg_0"></div>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">نوع</label>
                                                <select name="phones[0][type]" class="form-select">
                                                    <option value="mobile">موبايل</option>
                                                    <option value="landline">أرضي</option>
                                                    <option value="fax">فاكس</option>
                                                    <option value="whatsapp">واتساب</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">رئيسي</label>
                                                <div class="form-check">
                                                    <input type="checkbox" name="phones[0][primary]" value="1" class="form-check-input" checked disabled>
                                                    <label class="form-check-label">نعم</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addPhone()">
                                    <i class="bi bi-plus-lg"></i> إضافة رقم هاتف
                                </button>
                            </div>

                            <!-- Addresses Tab -->
                            <div class="tab-pane fade" id="addresses" role="tabpanel">
                                <h5 class="section-title">عناوين المورد</h5>
                                <div id="addressesContainer">
                                    <div class="address-row">
                                        <span class="remove-btn" onclick="this.closest('.address-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label class="form-label">نوع العنوان</label>
                                                <select name="addresses[0][type]" class="form-select">
                                                    <option value="main">رئيسي</option>
                                                    <option value="warehouse">مخزن</option>
                                                    <option value="branch">فرع</option>
                                                    <option value="other">أخرى</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">رقم العمارة/الفيلا</label>
                                                <input type="text" name="addresses[0][building]" class="form-control" placeholder="مثال: 15">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">الدور</label>
                                                <input type="text" name="addresses[0][floor]" class="form-control" placeholder="مثال: 3">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">الشقة</label>
                                                <input type="text" name="addresses[0][apartment]" class="form-control" placeholder="مثال: 5">
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <label class="form-label">اسم الشارع <span class="text-danger">*</span></label>
                                                <input type="text" name="addresses[0][street_name]" class="form-control" placeholder="اسم الشارع">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">علامة مميزة</label>
                                                <input type="text" name="addresses[0][landmark]" class="form-control" placeholder="بجوار... / أمام...">
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-4">
                                                <label class="form-label">المحافظة</label>
                                                <select name="addresses[0][governorate_id]" class="form-select governorate-select" onchange="loadAreas(this, 0)">
                                                    <option value="">-- اختر المحافظة --</option>
                                                    <?php foreach ($governorates as $gov): ?>
                                                        <option value="<?= $gov['id'] ?>"><?= $gov['governorate_name_ar'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">المنطقة</label>
                                                <select name="addresses[0][area_id]" class="form-select area-select" id="area_0">
                                                    <option value="">-- اختر المنطقة --</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">زون التوصيل</label>
                                                <select name="addresses[0][zone_id]" class="form-select">
                                                    <option value="">-- اختر الزون --</option>
                                                    <?php foreach ($zones as $zone): ?>
                                                        <option value="<?= $zone['id'] ?>"><?= $zone['zone_name_ar'] ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addAddress()">
                                    <i class="bi bi-plus-lg"></i> إضافة عنوان
                                </button>
                            </div>

                            <!-- Contacts Tab -->
                            <div class="tab-pane fade" id="contacts" role="tabpanel">
                                <h5 class="section-title">الموظفين والمناديب</h5>
                                <div id="contactsContainer">
                                    <div class="contact-row">
                                        <span class="remove-btn" onclick="this.closest('.contact-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label class="form-label">نوع</label>
                                                <select name="contacts[0][type]" class="form-select">
                                                    <option value="manager">مدير</option>
                                                    <option value="representative">مندوب</option>
                                                    <option value="distributor">موزع</option>
                                                    <option value="other">أخرى</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">الاسم <span class="text-danger">*</span></label>
                                                <input type="text" name="contacts[0][name]" class="form-control" placeholder="الاسم">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">المسمى الوظيفي</label>
                                                <input type="text" name="contacts[0][job_title]" class="form-control" placeholder="مثال: مدير">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">الهاتف</label>
                                                <input type="text" name="contacts[0][phone]" class="form-control" placeholder="رقم الهاتف">
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-6">
                                                <label class="form-label">البريد الإلكتروني</label>
                                                <input type="email" name="contacts[0][email]" class="form-control" placeholder="email@example.com">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">رئيسي</label>
                                                <div class="form-check">
                                                    <input type="checkbox" name="contacts[0][primary]" value="1" class="form-check-input" checked disabled>
                                                    <label class="form-check-label">نعم</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addContact()">
                                    <i class="bi bi-plus-lg"></i> إضافة موظف
                                </button>
                            </div>

                            <!-- Bank Tab -->
                            <div class="tab-pane fade" id="bank" role="tabpanel">
                                <h5 class="section-title">الحسابات البنكية</h5>
                                <div id="bankContainer">
                                    <div class="bank-row">
                                        <span class="remove-btn" onclick="this.closest('.bank-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <label class="form-label">اسم البنك <span class="text-danger">*</span></label>
                                                <input type="text" name="bank_accounts[0][bank_name]" class="form-control" placeholder="اسم البنك">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">رقم الحساب <span class="text-danger">*</span></label>
                                                <input type="text" name="bank_accounts[0][account_number]" class="form-control" placeholder="رقم الحساب">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">IBAN</label>
                                                <input type="text" name="bank_accounts[0][iban]" class="form-control" placeholder="IBAN">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Swift</label>
                                                <input type="text" name="bank_accounts[0][swift]" class="form-control" placeholder="Swift">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">فرع</label>
                                                <input type="text" name="bank_accounts[0][branch]" class="form-control" placeholder="فرع">
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <div class="form-check">
                                                    <input type="checkbox" name="bank_accounts[0][primary]" value="1" class="form-check-input" checked disabled>
                                                    <label class="form-check-label">حساب رئيسي</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addBank()">
                                    <i class="bi bi-plus-lg"></i> إضافة حساب بنكي
                                </button>
                            </div>

                            <!-- Settings Tab -->
                            <div class="tab-pane fade" id="settings" role="tabpanel">
                                <h5 class="section-title">إعدادات المورد</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">رقم الإنستاباي</label>
                                            <input type="text" name="instapay_number" class="form-control" value="<?= old('instapay_number') ?>" placeholder="رقم الإنستاباي">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">رقم المحفظة</label>
                                            <input type="text" name="wallet_number" class="form-control" value="<?= old('wallet_number') ?>" placeholder="رقم المحفظة">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">سياسة المرتجعات</label>
                                            <textarea name="return_policy" class="form-control" rows="3" placeholder="سياسة المرتجعات الخاصة بالمورد..."><?= old('return_policy') ?></textarea>
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
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" checked>
                                            <label class="form-check-label" for="is_active">مورد نشط</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ المورد
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
    // Toggle Credit Fields
    function toggleCreditFields() {
        const paymentType = document.getElementById('payment_type').value;
        document.getElementById('creditLimitDiv').style.display = paymentType === 'cash' ? 'none' : 'block';
        document.getElementById('gracePeriodDiv').style.display = paymentType === 'cash' ? 'none' : 'block';
    }

    // Update Flag Emoji
    function updateFlag(select) {
        const flag = select.options[select.selectedIndex].dataset.flag;
        select.closest('.country-select').querySelector('.flag').textContent = flag;
    }

    // Live Validation
    function validateField(input, fieldName) {
        const value = input.value.trim();
        const msgDiv = input.parentElement.querySelector('.validation-msg');

        if (!value) {
            msgDiv.textContent = '';
            msgDiv.className = 'validation-msg';
            return;
        }

        fetch(`?ajax_validate=1&field=${fieldName}&value=${encodeURIComponent(value)}`)
            .then(r => r.json())
            .then(data => {
                if (data.valid) {
                    msgDiv.textContent = '✓ متاح';
                    msgDiv.className = 'validation-msg text-success';
                } else {
                    msgDiv.textContent = data.message;
                    msgDiv.className = 'validation-msg text-danger';
                }
            })
            .catch(err => console.error(err));
    }

    // Phone Validation
    function validatePhoneField(input, index) {
        const value = input.value.trim();
        const countryCode = document.getElementById('country_code_' + index).value;
        const msgDiv = document.getElementById('phone_msg_' + index);

        if (!value) {
            msgDiv.textContent = '';
            msgDiv.className = 'validation-msg';
            return;
        }

        fetch(`?ajax_validate=1&field=phone&value=${encodeURIComponent(value)}&country_code=${encodeURIComponent(countryCode)}`)
            .then(r => r.json())
            .then(data => {
                if (data.valid) {
                    msgDiv.textContent = '✓ متاح';
                    msgDiv.className = 'validation-msg text-success';
                } else {
                    msgDiv.textContent = data.message;
                    msgDiv.className = 'validation-msg text-danger';
                }
            })
            .catch(err => console.error(err));
    }

    // Add Phone
    let phoneIndex = 1;
    function addPhone() {
        const container = document.getElementById('phonesContainer');
        const newRow = document.createElement('div');
        newRow.className = 'phone-row';
        newRow.innerHTML = `
            <span class="remove-btn" onclick="this.closest('.phone-row').remove()"><i class="bi bi-trash"></i></span>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">كود الدولة</label>
                    <div class="country-select">
                        <span class="flag">🇪🇬</span>
                        <select name="phones[${phoneIndex}][country_code]" class="form-select country-code-select" onchange="updateFlag(this)" id="country_code_${phoneIndex}">
                            <option value="+20" data-flag="🇪🇬" selected>مصر (+20)</option>
                            <option value="+966" data-flag="🇸🇦">السعودية (+966)</option>
                            <option value="+971" data-flag="🇦🇪">الإمارات (+971)</option>
                            <option value="+965" data-flag="🇰🇼">الكويت (+965)</option>
                            <option value="+974" data-flag="🇶🇦">قطر (+974)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="text" name="phones[${phoneIndex}][number]" class="form-control" placeholder="01xxxxxxxxx" onblur="validatePhoneField(this, ${phoneIndex})">
                    <div class="validation-msg" id="phone_msg_${phoneIndex}"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">نوع</label>
                    <select name="phones[${phoneIndex}][type]" class="form-select">
                        <option value="mobile">موبايل</option>
                        <option value="landline">أرضي</option>
                        <option value="fax">فاكس</option>
                        <option value="whatsapp">واتساب</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">رئيسي</label>
                    <div class="form-check">
                        <input type="checkbox" name="phones[${phoneIndex}][primary]" value="1" class="form-check-input">
                        <label class="form-check-label">نعم</label>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        phoneIndex++;
    }

    // Add Address
    let addressIndex = 1;
    function addAddress() {
        const container = document.getElementById('addressesContainer');
        const newRow = document.createElement('div');
        newRow.className = 'address-row';
        newRow.innerHTML = `
            <span class="remove-btn" onclick="this.closest('.address-row').remove()"><i class="bi bi-trash"></i></span>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">نوع العنوان</label>
                    <select name="addresses[${addressIndex}][type]" class="form-select">
                        <option value="main">رئيسي</option>
                        <option value="warehouse">مخزن</option>
                        <option value="branch">فرع</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">رقم العمارة/الفيلا</label>
                    <input type="text" name="addresses[${addressIndex}][building]" class="form-control" placeholder="مثال: 15">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الدور</label>
                    <input type="text" name="addresses[${addressIndex}][floor]" class="form-control" placeholder="مثال: 3">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الشقة</label>
                    <input type="text" name="addresses[${addressIndex}][apartment]" class="form-control" placeholder="مثال: 5">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label">اسم الشارع <span class="text-danger">*</span></label>
                    <input type="text" name="addresses[${addressIndex}][street_name]" class="form-control" placeholder="اسم الشارع">
                </div>
                <div class="col-md-6">
                    <label class="form-label">علامة مميزة</label>
                    <input type="text" name="addresses[${addressIndex}][landmark]" class="form-control" placeholder="بجوار... / أمام...">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <label class="form-label">المحافظة</label>
                    <select name="addresses[${addressIndex}][governorate_id]" class="form-select governorate-select" onchange="loadAreas(this, ${addressIndex})">
                        <option value="">-- اختر المحافظة --</option>
                        <?php foreach ($governorates as $gov): ?>
                            <option value="<?= $gov['id'] ?>"><?= $gov['governorate_name_ar'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">المنطقة</label>
                    <select name="addresses[${addressIndex}][area_id]" class="form-select area-select" id="area_${addressIndex}">
                        <option value="">-- اختر المنطقة --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">زون التوصيل</label>
                    <select name="addresses[${addressIndex}][zone_id]" class="form-select">
                        <option value="">-- اختر الزون --</option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?= $zone['id'] ?>"><?= $zone['zone_name_ar'] ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        addressIndex++;
    }

    // Add Contact
    let contactIndex = 1;
    function addContact() {
        const container = document.getElementById('contactsContainer');
        const newRow = document.createElement('div');
        newRow.className = 'contact-row';
        newRow.innerHTML = `
            <span class="remove-btn" onclick="this.closest('.contact-row').remove()"><i class="bi bi-trash"></i></span>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">نوع</label>
                    <select name="contacts[${contactIndex}][type]" class="form-select">
                        <option value="manager">مدير</option>
                        <option value="representative">مندوب</option>
                        <option value="distributor">موزع</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الاسم <span class="text-danger">*</span></label>
                    <input type="text" name="contacts[${contactIndex}][name]" class="form-control" placeholder="الاسم">
                </div>
                <div class="col-md-3">
                    <label class="form-label">المسمى الوظيفي</label>
                    <input type="text" name="contacts[${contactIndex}][job_title]" class="form-control" placeholder="مثال: مدير">
                </div>
                <div class="col-md-3">
                    <label class="form-label">الهاتف</label>
                    <input type="text" name="contacts[${contactIndex}][phone]" class="form-control" placeholder="رقم الهاتف">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="contacts[${contactIndex}][email]" class="form-control" placeholder="email@example.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label">رئيسي</label>
                    <div class="form-check">
                        <input type="checkbox" name="contacts[${contactIndex}][primary]" value="1" class="form-check-input">
                        <label class="form-check-label">نعم</label>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        contactIndex++;
    }

    // Add Bank
    let bankIndex = 1;
    function addBank() {
        const container = document.getElementById('bankContainer');
        const newRow = document.createElement('div');
        newRow.className = 'bank-row';
        newRow.innerHTML = `
            <span class="remove-btn" onclick="this.closest('.bank-row').remove()"><i class="bi bi-trash"></i></span>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">اسم البنك <span class="text-danger">*</span></label>
                    <input type="text" name="bank_accounts[${bankIndex}][bank_name]" class="form-control" placeholder="اسم البنك">
                </div>
                <div class="col-md-3">
                    <label class="form-label">رقم الحساب <span class="text-danger">*</span></label>
                    <input type="text" name="bank_accounts[${bankIndex}][account_number]" class="form-control" placeholder="رقم الحساب">
                </div>
                <div class="col-md-2">
                    <label class="form-label">IBAN</label>
                    <input type="text" name="bank_accounts[${bankIndex}][iban]" class="form-control" placeholder="IBAN">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Swift</label>
                    <input type="text" name="bank_accounts[${bankIndex}][swift]" class="form-control" placeholder="Swift">
                </div>
                <div class="col-md-2">
                    <label class="form-label">فرع</label>
                    <input type="text" name="bank_accounts[${bankIndex}][branch]" class="form-control" placeholder="فرع">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="form-check">
                        <input type="checkbox" name="bank_accounts[${bankIndex}][primary]" value="1" class="form-check-input">
                        <label class="form-check-label">حساب رئيسي</label>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        bankIndex++;
    }

    // Load Areas based on Governorate (AJAX)
    function loadAreas(select, index) {
        const governorateId = select.value;
        const areaSelect = document.getElementById('area_' + index);

        if (!governorateId) {
            areaSelect.innerHTML = '<option value="">-- اختر المنطقة --</option>';
            return;
        }

        fetch(`../../api/get-areas.php?governorate_id=${governorateId}`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="">-- اختر المنطقة --</option>';
                data.forEach(area => {
                    options += `<option value="${area.id}">${area.area_name_ar}</option>`;
                });
                areaSelect.innerHTML = options;
            })
            .catch(err => {
                console.error('Error loading areas:', err);
            });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        toggleCreditFields();
    });
    </script>
</body>
</html>
