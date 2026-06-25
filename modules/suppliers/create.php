<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Helper: retain POST data
function old($key, $default = '') {
    if (strpos($key, '[') !== false) {
        preg_match('/^(\w+)\[(\d+)\]\[(\w+)\]$/', $key, $matches);
        if ($matches) {
            $arr = $matches[1];
            $idx = $matches[2];
            $sub = $matches[3];
            return $_POST[$arr][$idx][$sub] ?? $default;
        }
        return $default;
    }
    return $_POST[$key] ?? $default;
}
function old_checked($key) {
    return isset($_POST[$key]) ? 'checked' : '';
}
function old_selected($key, $value) {
    return (isset($_POST[$key]) && $_POST[$key] == $value) ? 'selected' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update session activity to prevent timeout during form processing
    $_SESSION['LAST_ACTIVITY'] = time();
    error_log('Supplier Create: Form submitted with data: ' . print_r($_POST, true));
    try {
        $db->beginTransaction();

        // ========== VALIDATION ==========
        $errors = [];

        // 1. Validate Arabic name (required, unique)
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

        // 2. Validate English name (optional, but if provided must be unique)
        $supplier_name_en = trim($_POST['supplier_name_en'] ?? '');
        if (!empty($supplier_name_en)) {
            $stmt = $db->prepare("SELECT id FROM suppliers WHERE supplier_name_en = ? AND supplier_name_en IS NOT NULL AND supplier_name_en != '' AND (deleted_at IS NULL OR deleted_at = '')");
            $stmt->execute([$supplier_name_en]);
            if ($stmt->fetch()) {
                $errors[] = 'الاسم الإنجليزي موجود بالفعل';
            }
        }

        // 3. Validate phones (unique across all suppliers)
        if (!empty($_POST['phones'])) {
            foreach ($_POST['phones'] as $i => $phone) {
                if (!empty($phone['number'])) {
                    $stmt = $db->prepare("SELECT sp.*, s.supplier_name FROM supplier_phones sp JOIN suppliers s ON sp.supplier_id = s.id WHERE sp.phone_number = ? AND (s.deleted_at IS NULL OR s.deleted_at = '')");
                    $stmt->execute([$phone['number']]);
                    if ($existing = $stmt->fetch()) {
                        $errors[] = 'رقم الهاتف ' . $phone['number'] . ' مسجل لمورد: ' . $existing['supplier_name'];
                    }
                }
            }
        }

        // 4. Validate supplier_type (required)
        $supplier_type = $_POST['supplier_type'] ?? '';
        if (empty($supplier_type)) {
            $errors[] = 'تصنيف المورد مطلوب';
        }

        // 5. Validate payment_type (required)
        $payment_type = $_POST['payment_type'] ?? '';
        if (empty($payment_type)) {
            $errors[] = 'نوع التعامل مطلوب';
        }

        // If validation errors, throw exception
        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        // ========== GENERATE SEQUENTIAL CODE (1, 2, 3...) ==========
        // Use a simpler approach that's more robust
        $last_code_result = $db->query("SELECT MAX(CAST(supplier_code AS UNSIGNED)) as max_code FROM suppliers WHERE supplier_code REGEXP '^[0-9]+$'")->fetch();
        $max_code = isset($last_code_result['max_code']) ? intval($last_code_result['max_code']) : 0;
        $new_code = strval($max_code + 1);

        // ========== INSERT SUPPLIER ==========
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

        // ========== INSERT PHONES ==========
        if (!empty($_POST['phones'])) {
            $phone_stmt = $db->prepare("INSERT INTO supplier_phones (supplier_id, country_code, phone_number, phone_type, is_primary) VALUES (?, ?, ?, ?, ?)");
            $primary_set = false;
            foreach ($_POST['phones'] as $i => $phone) {
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

        // ========== INSERT ADDRESSES ==========
        if (!empty($_POST['addresses'])) {
            $addr_stmt = $db->prepare("
                INSERT INTO supplier_addresses (supplier_id, address_type, building_number, floor_number, 
                    apartment_number, street_name, landmark, area_id, governorate_id, delivery_zone_id, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $primary_set = false;
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
                        !$primary_set ? 1 : 0
                    ]);
                    $primary_set = true;
                }
            }
        }

        // ========== INSERT CONTACTS ==========
        if (!empty($_POST['contacts'])) {
            $contact_stmt = $db->prepare("
                INSERT INTO supplier_contacts (supplier_id, contact_type, contact_name, job_title, phone, email, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $primary_set = false;
            foreach ($_POST['contacts'] as $i => $contact) {
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

        // ========== INSERT BANK ACCOUNTS ==========
        if (!empty($_POST['bank_accounts'])) {
            $bank_stmt = $db->prepare("
                INSERT INTO supplier_bank_accounts (supplier_id, account_number, bank_name, iban, swift_code, branch_name, is_primary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $primary_set = false;
            foreach ($_POST['bank_accounts'] as $i => $bank) {
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

        // ========== INITIALIZE BALANCE ==========
        $db->prepare("INSERT INTO supplier_balances (supplier_id, balance, total_purchases, total_payments, total_returns) VALUES (?, 0, 0, 0, 0)")
            ->execute([$supplier_id]);

        $db->commit();

        // Clear output buffer before redirect
        ob_end_clean();
        session_write_close();
        header("Location: index.php?success=1");
        exit();

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
        // Log error for debugging
        error_log('Supplier Create Error: ' . $e->getMessage());
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'حدث خطأ في قاعدة البيانات: ' . $e->getMessage();
        error_log('Supplier Create PDO Error: ' . $e->getMessage());
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
        .validation-error { color: var(--danger); font-size: 12px; margin-top: 4px; display: none; }
        .is-invalid { border-color: var(--danger) !important; }
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

            <form method="POST" action="" id="supplierForm" novalidate>
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
                                        <input type="text" name="supplier_name" id="supplierName" class="form-control" 
                                               value="<?= htmlspecialchars(old('supplier_name')) ?>" required>
                                        <div class="validation-error" id="nameError">اسم المورد مطلوب</div>
                                        <div class="validation-error" id="nameDuplicateError">اسم المورد موجود بالفعل</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">الاسم بالإنجليزي</label>
                                        <input type="text" name="supplier_name_en" id="supplierNameEn" class="form-control"
                                               value="<?= htmlspecialchars(old('supplier_name_en')) ?>">
                                        <div class="validation-error" id="nameEnDuplicateError">الاسم الإنجليزي موجود بالفعل</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">تصنيف المورد <span class="text-danger">*</span></label>
                                        <select name="supplier_type" class="form-select" required>
                                            <option value="">-- اختر --</option>
                                            <option value="b2b" <?= old_selected('supplier_type', 'b2b') ?>>صيدلية (B2B)</option>
                                            <option value="private_office" <?= old_selected('supplier_type', 'private_office') ?>>مكتب خاص</option>
                                            <option value="warehouse" <?= old_selected('supplier_type', 'warehouse') ?>>مخزن</option>
                                            <option value="distributor" <?= old_selected('supplier_type', 'distributor') ?>>موزع</option>
                                            <option value="company" <?= old_selected('supplier_type', 'company') ?>>شركة</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">نوع التعامل <span class="text-danger">*</span></label>
                                        <select name="payment_type" class="form-select" id="paymentType" required>
                                            <option value="">-- اختر --</option>
                                            <option value="cash" <?= old_selected('payment_type', 'cash') ?>>نقدي</option>
                                            <option value="credit" <?= old_selected('payment_type', 'credit') ?>>آجل</option>
                                            <option value="cheque" <?= old_selected('payment_type', 'cheque') ?>>شيك</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3" id="creditLimitDiv" style="display:<?= old('payment_type') == 'cash' ? 'none' : 'block' ?>;">
                                        <label class="form-label">حد التعامل</label>
                                        <div class="input-group">
                                            <input type="number" name="credit_limit" class="form-control" step="0.01" 
                                                   value="<?= htmlspecialchars(old('credit_limit', '0')) ?>">
                                            <span class="input-group-text">ج</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3" id="gracePeriodDiv" style="display:<?= old('payment_type') == 'cash' ? 'none' : 'block' ?>;">
                                        <label class="form-label">فترة السماح (يوم)</label>
                                        <input type="number" name="grace_period" class="form-control" 
                                               value="<?= htmlspecialchars(old('grace_period', '0')) ?>">
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
                                    <?php
                                    $phone_count = 0;
                                    if (!empty($_POST['phones'])):
                                        foreach ($_POST['phones'] as $idx => $phone):
                                            if (!empty($phone['number']) || $idx == 0):
                                    ?>
                                    <div class="dynamic-row row">
                                        <div class="col-md-3">
                                            <select name="phones[<?= $idx ?>][country_code]" class="form-select">
                                                <option value="+20" <?= ($phone['country_code'] ?? '+20') == '+20' ? 'selected' : '' ?>>🇪🇬 +20</option>
                                                <option value="+966" <?= ($phone['country_code'] ?? '') == '+966' ? 'selected' : '' ?>>🇸🇦 +966</option>
                                                <option value="+971" <?= ($phone['country_code'] ?? '') == '+971' ? 'selected' : '' ?>>🇦🇪 +971</option>
                                                <option value="+965" <?= ($phone['country_code'] ?? '') == '+965' ? 'selected' : '' ?>>🇰🇼 +965</option>
                                                <option value="+974" <?= ($phone['country_code'] ?? '') == '+974' ? 'selected' : '' ?>>🇶🇦 +974</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" name="phones[<?= $idx ?>][number]" class="form-control phone-number" 
                                                   placeholder="رقم الهاتف" data-index="<?= $idx ?>"
                                                   value="<?= htmlspecialchars($phone['number'] ?? '') ?>">
                                            <div class="validation-error phone-duplicate-error" data-index="<?= $idx ?>">رقم الهاتف مسجل لمورد آخر</div>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="phones[<?= $idx ?>][type]" class="form-select">
                                                <option value="mobile" <?= ($phone['type'] ?? 'mobile') == 'mobile' ? 'selected' : '' ?>>موبايل</option>
                                                <option value="landline" <?= ($phone['type'] ?? '') == 'landline' ? 'selected' : '' ?>>أرضي</option>
                                                <option value="fax" <?= ($phone['type'] ?? '') == 'fax' ? 'selected' : '' ?>>فاكس</option>
                                                <option value="whatsapp" <?= ($phone['type'] ?? '') == 'whatsapp' ? 'selected' : '' ?>>واتساب</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i> حذف</span>
                                        </div>
                                    </div>
                                    <?php
                                            $phone_count = $idx + 1;
                                            endif;
                                        endforeach;
                                    endif;
                                    ?>
                                    <?php if (empty($_POST['phones'])): ?>
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
                                            <input type="text" name="phones[0][number]" class="form-control phone-number" placeholder="رقم الهاتف" data-index="0">
                                            <div class="validation-error phone-duplicate-error" data-index="0">رقم الهاتف مسجل لمورد آخر</div>
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
                                    <?php endif; ?>
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
                                    <?php
                                    $addr_count = 0;
                                    if (!empty($_POST['addresses'])):
                                        foreach ($_POST['addresses'] as $idx => $addr):
                                            if (!empty($addr['street_name']) || $idx == 0):
                                    ?>
                                    <div class="dynamic-row">
                                        <div class="row">
                                            <div class="col-md-3 mb-2">
                                                <select name="addresses[<?= $idx ?>][type]" class="form-select">
                                                    <option value="main" <?= ($addr['type'] ?? 'main') == 'main' ? 'selected' : '' ?>>رئيسي</option>
                                                    <option value="warehouse" <?= ($addr['type'] ?? '') == 'warehouse' ? 'selected' : '' ?>>مخزن</option>
                                                    <option value="branch" <?= ($addr['type'] ?? '') == 'branch' ? 'selected' : '' ?>>فرع</option>
                                                    <option value="other" <?= ($addr['type'] ?? '') == 'other' ? 'selected' : '' ?>>أخرى</option>
                                                </select>
                                            </div>
                                            <div class="col-md-9 mb-2">
                                                <input type="text" name="addresses[<?= $idx ?>][street_name]" class="form-control" 
                                                       placeholder="اسم الشارع" value="<?= htmlspecialchars($addr['street_name'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[<?= $idx ?>][building]" class="form-control" 
                                                       placeholder="رقم العمارة" value="<?= htmlspecialchars($addr['building'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[<?= $idx ?>][floor]" class="form-control" 
                                                       placeholder="الدور" value="<?= htmlspecialchars($addr['floor'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[<?= $idx ?>][apartment]" class="form-control" 
                                                       placeholder="الشقة" value="<?= htmlspecialchars($addr['apartment'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <input type="text" name="addresses[<?= $idx ?>][landmark]" class="form-control" 
                                                       placeholder="علامة مميزة" value="<?= htmlspecialchars($addr['landmark'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <select name="addresses[<?= $idx ?>][governorate_id]" class="form-select">
                                                    <option value="">-- المحافظة --</option>
                                                    <?php foreach ($governorates as $gov): ?>
                                                        <option value="<?= $gov['id'] ?>" <?= ($addr['governorate_id'] ?? '') == $gov['id'] ? 'selected' : '' ?>><?= htmlspecialchars($gov['governorate_name_ar']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <select name="addresses[<?= $idx ?>][area_id]" class="form-select">
                                                    <option value="">-- المنطقة --</option>
                                                    <?php foreach ($areas as $area): ?>
                                                        <option value="<?= $area['id'] ?>" <?= ($addr['area_id'] ?? '') == $area['id'] ? 'selected' : '' ?>><?= htmlspecialchars($area['area_name_ar']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <select name="addresses[<?= $idx ?>][zone_id]" class="form-select">
                                                    <option value="">-- Zone --</option>
                                                    <?php foreach ($zones as $zone): ?>
                                                        <option value="<?= $zone['id'] ?>" <?= ($addr['zone_id'] ?? '') == $zone['id'] ? 'selected' : '' ?>><?= htmlspecialchars($zone['zone_name_ar']) ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-1 mb-2 text-end">
                                                <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                            $addr_count = $idx + 1;
                                            endif;
                                        endforeach;
                                    endif;
                                    ?>
                                    <?php if (empty($_POST['addresses'])): ?>
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
                                    <?php endif; ?>
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
                                    <?php
                                    $contact_count = 0;
                                    if (!empty($_POST['contacts'])):
                                        foreach ($_POST['contacts'] as $idx => $contact):
                                            if (!empty($contact['name']) || $idx == 0):
                                    ?>
                                    <div class="dynamic-row row">
                                        <div class="col-md-3 mb-2">
                                            <select name="contacts[<?= $idx ?>][type]" class="form-select">
                                                <option value="manager" <?= ($contact['type'] ?? 'manager') == 'manager' ? 'selected' : '' ?>>مدير</option>
                                                <option value="representative" <?= ($contact['type'] ?? '') == 'representative' ? 'selected' : '' ?>>مندوب</option>
                                                <option value="distributor" <?= ($contact['type'] ?? '') == 'distributor' ? 'selected' : '' ?>>موزع</option>
                                                <option value="other" <?= ($contact['type'] ?? '') == 'other' ? 'selected' : '' ?>>أخرى</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="contacts[<?= $idx ?>][name]" class="form-control" 
                                                   placeholder="الاسم" value="<?= htmlspecialchars($contact['name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="contacts[<?= $idx ?>][job_title]" class="form-control" 
                                                   placeholder="المسمى الوظيفي" value="<?= htmlspecialchars($contact['job_title'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <input type="text" name="contacts[<?= $idx ?>][phone]" class="form-control" 
                                                   placeholder="الهاتف" value="<?= htmlspecialchars($contact['phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-1 mb-2 text-end">
                                            <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                                        </div>
                                    </div>
                                    <?php
                                            $contact_count = $idx + 1;
                                            endif;
                                        endforeach;
                                    endif;
                                    ?>
                                    <?php if (empty($_POST['contacts'])): ?>
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
                                    <?php endif; ?>
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
                                    <?php
                                    $bank_count = 0;
                                    if (!empty($_POST['bank_accounts'])):
                                        foreach ($_POST['bank_accounts'] as $idx => $bank):
                                            if (!empty($bank['account_number']) || $idx == 0):
                                    ?>
                                    <div class="dynamic-row row">
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="bank_accounts[<?= $idx ?>][bank_name]" class="form-control" 
                                                   placeholder="اسم البنك" value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <input type="text" name="bank_accounts[<?= $idx ?>][account_number]" class="form-control" 
                                                   placeholder="رقم الحساب" value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <input type="text" name="bank_accounts[<?= $idx ?>][iban]" class="form-control" 
                                                   placeholder="IBAN" value="<?= htmlspecialchars($bank['iban'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <input type="text" name="bank_accounts[<?= $idx ?>][swift]" class="form-control" 
                                                   placeholder="Swift" value="<?= htmlspecialchars($bank['swift'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-1 mb-2">
                                            <input type="text" name="bank_accounts[<?= $idx ?>][branch]" class="form-control" 
                                                   placeholder="فرع" value="<?= htmlspecialchars($bank['branch'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-1 mb-2 text-end">
                                            <span class="remove-btn" onclick="removeRow(this)"><i class="bi bi-trash"></i></span>
                                        </div>
                                    </div>
                                    <?php
                                            $bank_count = $idx + 1;
                                            endif;
                                        endforeach;
                                    endif;
                                    ?>
                                    <?php if (empty($_POST['bank_accounts'])): ?>
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
                                    <?php endif; ?>
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
                                        <input type="text" name="instapay_number" class="form-control"
                                               value="<?= htmlspecialchars(old('instapay_number')) ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">رقم المحفظة</label>
                                        <input type="text" name="wallet_number" class="form-control"
                                               value="<?= htmlspecialchars(old('wallet_number')) ?>">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">سياسة المرتجعات</label>
                                        <textarea name="return_policy" class="form-control" rows="3" placeholder="سياسة المرتجعات الخاصة بالمورد..."><?= htmlspecialchars(old('return_policy')) ?></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">ملاحظات</label>
                                        <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات عامة..."><?= htmlspecialchars(old('notes')) ?></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= old_checked('is_active') ?: 'checked' ?>>
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
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
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

        // Validation functions
        const baseUrl = window.location.protocol + '//' + window.location.host + '/nile-center-system';

        async function validateSupplierName(name) {
            if (!name.trim()) return false;
            try {
                const response = await fetch(`${baseUrl}/api/api_validate_supplier.php?action=check_name&name=${encodeURIComponent(name)}`);
                const data = await response.json();
                return data.valid;
            } catch (e) { return true; }
        }

        async function validateSupplierNameEn(nameEn) {
            if (!nameEn.trim()) return true; // Optional
            try {
                const response = await fetch(`${baseUrl}/api/api_validate_supplier.php?action=check_name_en&name_en=${encodeURIComponent(nameEn)}`);
                const data = await response.json();
                return data.valid;
            } catch (e) { return true; }
        }

        async function validatePhone(phone, index) {
            if (!phone.trim()) return true; // Optional
            try {
                const response = await fetch(`${baseUrl}/api/api_validate_supplier.php?action=check_phone&phone=${encodeURIComponent(phone)}`);
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

        // Real-time validation
        document.getElementById('supplierName').addEventListener('blur', async function() {
            const name = this.value.trim();
            const errorDiv = document.getElementById('nameError');
            const duplicateDiv = document.getElementById('nameDuplicateError');

            if (!name) {
                errorDiv.style.display = 'block';
                this.classList.add('is-invalid');
                return;
            }
            errorDiv.style.display = 'none';

            const isValid = await validateSupplierName(name);
            if (!isValid) {
                duplicateDiv.style.display = 'block';
                this.classList.add('is-invalid');
            } else {
                duplicateDiv.style.display = 'none';
                this.classList.remove('is-invalid');
            }
        });

        document.getElementById('supplierNameEn').addEventListener('blur', async function() {
            const nameEn = this.value.trim();
            if (!nameEn) return;

            const isValid = await validateSupplierNameEn(nameEn);
            const duplicateDiv = document.getElementById('nameEnDuplicateError');
            if (!isValid) {
                duplicateDiv.style.display = 'block';
                this.classList.add('is-invalid');
            } else {
                duplicateDiv.style.display = 'none';
                this.classList.remove('is-invalid');
            }
        });

        // Phone validation
        document.addEventListener('blur', async function(e) {
            if (e.target.classList.contains('phone-number')) {
                const phone = e.target.value.trim();
                const index = e.target.dataset.index;
                if (phone) {
                    await validatePhone(phone, index);
                }
            }
        }, true);

        // Form submission validation
        document.getElementById('supplierForm').addEventListener('submit', async function(e) {
            const name = document.getElementById('supplierName').value.trim();
            if (!name) {
                e.preventDefault();
                document.getElementById('nameError').style.display = 'block';
                document.getElementById('supplierName').classList.add('is-invalid');
                document.getElementById('supplierName').focus();
                return false;
            }

            const isNameValid = await validateSupplierName(name);
            if (!isNameValid) {
                e.preventDefault();
                document.getElementById('nameDuplicateError').style.display = 'block';
                document.getElementById('supplierName').classList.add('is-invalid');
                document.getElementById('supplierName').focus();
                return false;
            }

            // Validate all phones
            const phoneInputs = document.querySelectorAll('.phone-number');
            for (let input of phoneInputs) {
                const phone = input.value.trim();
                const index = input.dataset.index;
                if (phone) {
                    const isValid = await validatePhone(phone, index);
                    if (!isValid) {
                        e.preventDefault();
                        input.classList.add('is-invalid');
                        input.focus();
                        return false;
                    }
                }
            }

            return true;
        });

        // Dynamic rows - use correct counts from PHP
        let phoneCount = <?= $phone_count ?: 1 ?>;
        let addressCount = <?= $addr_count ?: 1 ?>;
        let contactCount = <?= $contact_count ?: 1 ?>;
        let bankCount = <?= $bank_count ?: 1 ?>;

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
                        <input type="text" name="phones[${phoneCount}][number]" class="form-control phone-number" placeholder="رقم الهاتف" data-index="${phoneCount}">
                        <div class="validation-error phone-duplicate-error" data-index="${phoneCount}">رقم الهاتف مسجل لمورد آخر</div>
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