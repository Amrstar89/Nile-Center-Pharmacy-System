<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();
$error = '';
$success = '';

// Get areas, governorates, zones for dropdowns
$areas = $db->query("SELECT * FROM areas WHERE is_active = 1 ORDER BY area_name_ar")->fetchAll();
$governorates = $db->query("SELECT * FROM governorates WHERE is_active = 1 ORDER BY governorate_name_ar")->fetchAll();
$zones = $db->query("SELECT * FROM delivery_zones WHERE is_active = 1 ORDER BY zone_name_ar")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Generate supplier code
        $last_code = $db->query("SELECT MAX(CAST(supplier_code AS UNSIGNED)) as max_code FROM suppliers")->fetch();
        $new_code = ($last_code['max_code'] ?? 0) + 1;

        // Insert supplier
        $stmt = $db->prepare("
            INSERT INTO suppliers (supplier_code, supplier_name, supplier_name_en, supplier_type, 
                payment_type, credit_limit, grace_period, return_policy, notes, 
                instapay_number, wallet_number, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $new_code,
            trim($_POST['supplier_name'] ?? ''),
            trim($_POST['supplier_name_en'] ?? '') ?: null,
            $_POST['supplier_type'] ?? 'company',
            $_POST['payment_type'] ?? 'cash',
            floatval($_POST['credit_limit'] ?? 0),
            intval($_POST['grace_period'] ?? 0),
            trim($_POST['return_policy'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
            trim($_POST['instapay_number'] ?? '') ?: null,
            trim($_POST['wallet_number'] ?? '') ?: null,
            isset($_POST['is_active']) ? 1 : 0
        ]);
        $supplier_id = $db->lastInsertId();

        // Insert phones
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

        // Insert addresses
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

        // Insert contacts (managers, representatives, distributors)
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

        // Insert bank accounts
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

        // Initialize balance
        $db->prepare("INSERT INTO supplier_balances (supplier_id, balance, total_purchases, total_payments, total_returns) VALUES (?, 0, 0, 0, 0)")
            ->execute([$supplier_id]);

        $db->commit();
        $success = 'تم إضافة المورد بنجاح!';

        // Redirect after success
        header("Location: index.php?success=1");
        exit;

    } catch (PDOException $e) {
        $db->rollBack();
        $error = 'حدث خطأ: ' . $e->getMessage();
    }
}

$page_title = 'إضافة مورد جديد';
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
        .section-title { color: var(--primary); font-weight: 600; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #eee; }
        .dynamic-row { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 10px; }
        .remove-btn { color: var(--danger); cursor: pointer; }
        .add-btn { color: var(--success); cursor: pointer; font-weight: 500; }
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
                    <h2><i class="bi bi-truck"></i> <?= $page_title ?></h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">الموردين</a></li>
                            <li class="breadcrumb-item active">إضافة جديد</li>
                        </ol>
                    </nav>
                </div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-right"></i> رجوع
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="supplierTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">
                            <i class="bi bi-info-circle"></i> البيانات الأساسية
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="phones-tab" data-bs-toggle="tab" data-bs-target="#phones" type="button" role="tab">
                            <i class="bi bi-telephone"></i> الهواتف
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" type="button" role="tab">
                            <i class="bi bi-geo-alt"></i> العناوين
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button" role="tab">
                            <i class="bi bi-people"></i> الموظفين
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank" type="button" role="tab">
                            <i class="bi bi-bank"></i> الحسابات البنكية
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                            <i class="bi bi-gear"></i> الإعدادات
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="supplierTabsContent">
                    <!-- Basic Info -->
                    <div class="tab-pane fade show active" id="basic" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="bi bi-info-circle"></i> البيانات الأساسية</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">اسم المورد <span class="text-danger">*</span></label>
                                        <input type="text" name="supplier_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">الاسم بالإنجليزي</label>
                                        <input type="text" name="supplier_name_en" class="form-control">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">تصنيف المورد <span class="text-danger">*</span></label>
                                        <select name="supplier_type" class="form-select" required>
                                            <option value="b2b">صيدلية (B2B)</option>
                                            <option value="private_office">مكتب خاص</option>
                                            <option value="warehouse">مخزن</option>
                                            <option value="distributor">موزع</option>
                                            <option value="company" selected>شركة</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">نوع التعامل</label>
                                        <select name="payment_type" class="form-select" id="paymentType">
                                            <option value="cash">نقدي</option>
                                            <option value="credit">آجل</option>
                                            <option value="cheque">شيك</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3" id="creditLimitDiv" style="display:none;">
                                        <label class="form-label">حد التعامل</label>
                                        <div class="input-group">
                                            <input type="number" name="credit_limit" class="form-control" step="0.01" value="0">
                                            <span class="input-group-text">ج</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3" id="gracePeriodDiv" style="display:none;">
                                        <label class="form-label">فترة السماح (يوم)</label>
                                        <input type="number" name="grace_period" class="form-control" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Phones -->
                    <div class="tab-pane fade" id="phones" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="bi bi-telephone"></i> أرقام الهاتف</h5>
                                <div id="phonesContainer">
                                    <div class="dynamic-row row">
                                        <div class="col-md-3">
                                            <select name="phones[0][country_code]" class="form-select">
                                                <option value="+20" selected>🇪🇬 +20</option>
                                                <option value="+966">🇸🇦 +966</option>
                                                <option value="+971">🇦🇪 +971</option>
                                                <option value="+965">🇰🇼 +965</option>
                                                <option value="+974">🇶🇦 +974</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" name="phones[0][number]" class="form-control" placeholder="رقم الهاتف">
                                        </div>
                                        <div class="col-md-3">
                                            <select name="phones[0][type]" class="form-select">
                                                <option value="mobile">موبايل</option>
                                                <option value="landline">أرضي</option>
                                                <option value="fax">فاكس</option>
                                                <option value="whatsapp">واتساب</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i> حذف</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <span class="add-btn" onclick="addPhone()"><i class="bi bi-plus-circle"></i> إضافة رقم هاتف</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Addresses -->
                    <div class="tab-pane fade" id="addresses" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="bi bi-geo-alt"></i> العناوين</h5>
                                <div id="addressesContainer">
                                    <div class="dynamic-row">
                                        <div class="row">
                                            <div class="col-md-3 mb-2">
                                                <select name="addresses[0][type]" class="form-select">
                                                    <option value="main">رئيسي</option>
                                                    <option value="warehouse">مخزن</option>
                                                    <option value="branch">فرع</option>
                                                    <option value="other">أخرى</option>
                                                </select>
                                            </div>
                                            <div class="col-md-9 mb-2">
                                                <input type="text" name="addresses[0][street_name]" class="form-control" placeholder="اسم الشارع">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[0][building]" class="form-control" placeholder="رقم العمارة">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[0][floor]" class="form-control" placeholder="الدور">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[0][apartment]" class="form-control" placeholder="الشقة">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[0][landmark]" class="form-control" placeholder="علامة مميزة">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <select name="addresses[0][governorate_id]" class="form-select">
                                                    <option value="">-- المحافظة --</option>
                                                    <?php foreach ($governorates as $gov): ?>
                                                        <option value="<?= $gov['id'] ?>"><?= htmlspecialchars($gov['governorate_name_ar']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <select name="addresses[0][area_id]" class="form-select">
                                                    <option value="">-- المنطقة --</option>
                                                    <?php foreach ($areas as $area): ?>
                                                        <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['area_name_ar']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <select name="addresses[0][zone_id]" class="form-select">
                                                    <option value="">-- Zone --</option>
                                                    <?php foreach ($zones as $zone): ?>
                                                        <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['zone_name_ar']) ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-1 mb-2 text-end">
                                                <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <span class="add-btn" onclick="addAddress()"><i class="bi bi-plus-circle"></i> إضافة عنوان</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contacts -->
                    <div class="tab-pane fade" id="contacts" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="bi bi-people"></i> الموظفين والمناديب</h5>
                                <div id="contactsContainer">
                                    <div class="dynamic-row row">
                                        <div class="col-md-3 mb-2">
                                            <select name="contacts[0][type]" class="form-select">
                                                <option value="manager">مدير</option>
                                                <option value="representative">مندوب</option>
                                                <option value="distributor">موزع</option>
                                                <option value="other">أخرى</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="contacts[0][name]" class="form-control" placeholder="الاسم">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="contacts[0][job_title]" class="form-control" placeholder="المسمى الوظيفي">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <input type="text" name="contacts[0][phone]" class="form-control" placeholder="الهاتف">
                                        </div>
                                        <div class="col-md-1 mb-2 text-end">
                                            <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <span class="add-btn" onclick="addContact()"><i class="bi bi-plus-circle"></i> إضافة موظف</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Accounts -->
                    <div class="tab-pane fade" id="bank" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="bi bi-bank"></i> الحسابات البنكية</h5>
                                <div id="bankContainer">
                                    <div class="dynamic-row row">
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="bank_accounts[0][bank_name]" class="form-control" placeholder="اسم البنك">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="bank_accounts[0][account_number]" class="form-control" placeholder="رقم الحساب">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <input type="text" name="bank_accounts[0][iban]" class="form-control" placeholder="IBAN">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <input type="text" name="bank_accounts[0][swift]" class="form-control" placeholder="Swift">
                                        </div>
                                        <div class="col-md-1 mb-2">
                                            <input type="text" name="bank_accounts[0][branch]" class="form-control" placeholder="فرع">
                                        </div>
                                        <div class="col-md-1 mb-2 text-end">
                                            <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <span class="add-btn" onclick="addBank()"><i class="bi bi-plus-circle"></i> إضافة حساب بنكي</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="tab-pane fade" id="settings" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="section-title"><i class="bi bi-gear"></i> الإعدادات</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">رقم الإنستاباي</label>
                                        <input type="text" name="instapay_number" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">رقم المحفظة</label>
                                        <input type="text" name="wallet_number" class="form-control">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">سياسة المرتجعات</label>
                                        <textarea name="return_policy" class="form-control" rows="3" placeholder="سياسة المرتجعات الخاصة بالمورد..."></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">ملاحظات</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات عامة..."></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                            <label class="form-check-label" for="isActive">مورد نشط</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="card mt-4">
                    <div class="card-body text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-check-lg"></i> حفظ المورد
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Payment type toggle
        document.getElementById('paymentType').addEventListener('change', function() {
            const creditDiv = document.getElementById('creditLimitDiv');
            const graceDiv = document.getElementById('gracePeriodDiv');
            if (this.value === 'cash') {
                creditDiv.style.display = 'none';
                graceDiv.style.display = 'none';
            } else {
                creditDiv.style.display = 'block';
                graceDiv.style.display = 'block';
            }
        });

        // Dynamic rows
        let phoneCount = 1, addressCount = 1, contactCount = 1, bankCount = 1;

        function addPhone() {
            const container = document.getElementById('phonesContainer');
            const html = `
                <div class="dynamic-row row">
                    <div class="col-md-3">
                        <select name="phones[${phoneCount}][country_code]" class="form-select">
                            <option value="+20" selected>🇪🇬 +20</option>
                            <option value="+966">🇸🇦 +966</option>
                            <option value="+971">🇦🇪 +971</option>
                            <option value="+965">🇰🇼 +965</option>
                            <option value="+974">🇶🇦 +974</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="phones[${phoneCount}][number]" class="form-control" placeholder="رقم الهاتف">
                    </div>
                    <div class="col-md-3">
                        <select name="phones[${phoneCount}][type]" class="form-select">
                            <option value="mobile">موبايل</option>
                            <option value="landline">أرضي</option>
                            <option value="fax">فاكس</option>
                            <option value="whatsapp">واتساب</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i> حذف</span>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
            phoneCount++;
        }

        function addAddress() {
            const container = document.getElementById('addressesContainer');
            const html = `
                <div class="dynamic-row">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <select name="addresses[${addressCount}][type]" class="form-select">
                                <option value="main">رئيسي</option>
                                <option value="warehouse">مخزن</option>
                                <option value="branch">فرع</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-9 mb-2">
                            <input type="text" name="addresses[${addressCount}][street_name]" class="form-control" placeholder="اسم الشارع">
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="addresses[${addressCount}][building]" class="form-control" placeholder="رقم العمارة">
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="addresses[${addressCount}][floor]" class="form-control" placeholder="الدور">
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="addresses[${addressCount}][apartment]" class="form-control" placeholder="الشقة">
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="addresses[${addressCount}][landmark]" class="form-control" placeholder="علامة مميزة">
                        </div>
                        <div class="col-md-4 mb-2">
                            <select name="addresses[${addressCount}][governorate_id]" class="form-select">
                                <option value="">-- المحافظة --</option>
                                <?php foreach ($governorates as $gov): ?>
                                    <option value="<?= $gov['id'] ?>"><?= htmlspecialchars($gov['governorate_name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <select name="addresses[${addressCount}][area_id]" class="form-select">
                                <option value="">-- المنطقة --</option>
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?= $area['id'] ?>"><?= htmlspecialchars($area['area_name_ar']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <select name="addresses[${addressCount}][zone_id]" class="form-select">
                                <option value="">-- Zone --</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['zone_name_ar']) ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1 mb-2 text-end">
                            <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                        </div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
            addressCount++;
        }

        function addContact() {
            const container = document.getElementById('contactsContainer');
            const html = `
                <div class="dynamic-row row">
                    <div class="col-md-3 mb-2">
                        <select name="contacts[${contactCount}][type]" class="form-select">
                            <option value="manager">مدير</option>
                            <option value="representative">مندوب</option>
                            <option value="distributor">موزع</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" name="contacts[${contactCount}][name]" class="form-control" placeholder="الاسم">
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" name="contacts[${contactCount}][job_title]" class="form-control" placeholder="المسمى الوظيفي">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" name="contacts[${contactCount}][phone]" class="form-control" placeholder="الهاتف">
                    </div>
                    <div class="col-md-1 mb-2 text-end">
                        <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
            contactCount++;
        }

        function addBank() {
            const container = document.getElementById('bankContainer');
            const html = `
                <div class="dynamic-row row">
                    <div class="col-md-3 mb-2">
                        <input type="text" name="bank_accounts[${bankCount}][bank_name]" class="form-control" placeholder="اسم البنك">
                    </div>
                    <div class="col-md-3 mb-2">
                        <input type="text" name="bank_accounts[${bankCount}][account_number]" class="form-control" placeholder="رقم الحساب">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" name="bank_accounts[${bankCount}][iban]" class="form-control" placeholder="IBAN">
                    </div>
                    <div class="col-md-2 mb-2">
                        <input type="text" name="bank_accounts[${bankCount}][swift]" class="form-control" placeholder="Swift">
                    </div>
                    <div class="col-md-1 mb-2">
                        <input type="text" name="bank_accounts[${bankCount}][branch]" class="form-control" placeholder="فرع">
                    </div>
                    <div class="col-md-1 mb-2 text-end">
                        <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
            bankCount++;
        }

        function removeRow(btn) {
            btn.closest('.dynamic-row').remove();
        }
    </script>
</body>
</html>