<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/auth.php';
requireAuth();

$db = getDB();

$page_title = 'تعديل بيانات العميل';

$customer_id = intval($_GET['id'] ?? 0);
if ($customer_id === 0) {
    redirect('index.php');
}

// Get customer data
$customer = $db->prepare("SELECT * FROM customers WHERE id = ?");
$customer->execute([$customer_id]);
$customer = $customer->fetch();

if (!$customer) {
    redirect('index.php');
}

// AJAX Live Validation endpoint
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
        case 'customer_name':
            $stmt = $db->prepare("SELECT id FROM customers WHERE customer_name = ? AND id != ?");
            $stmt->execute([$value, $customer_id]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ اسم العميل موجود بالفعل'; }
            break;

        case 'customer_name_en':
            $stmt = $db->prepare("SELECT id FROM customers WHERE customer_name_en = ? AND customer_name_en IS NOT NULL AND customer_name_en != '' AND id != ?");
            $stmt->execute([$value, $customer_id]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ الاسم الإنجليزي موجود بالفعل'; }
            break;

        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $valid = false; $message = '⚠️ بريد إلكتروني غير صالح';
            } else {
                $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND email IS NOT NULL AND email != '' AND id != ?");
                $stmt->execute([$value, $customer_id]);
                if ($stmt->fetch()) { $valid = false; $message = '⚠️ البريد الإلكتروني مستخدم بالفعل'; }
            }
            break;

        case 'phone':
            $country_code = trim($_GET['country_code'] ?? '+20');
            $full_phone = $country_code . $value;
            $stmt = $db->prepare("SELECT id FROM customer_phones WHERE CONCAT(country_code, phone_number) = ? AND customer_id != ?");
            $stmt->execute([$full_phone, $customer_id]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ رقم الهاتف مستخدم بالفعل'; }
            break;

        case 'contract_number':
            $stmt = $db->prepare("SELECT id FROM customer_contracts WHERE contract_number = ? AND customer_id != ?");
            $stmt->execute([$value, $customer_id]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ رقم التعاقد موجود بالفعل'; }
            break;

        case 'card_number':
            $stmt = $db->prepare("SELECT id FROM customer_contracts WHERE card_number = ? AND card_number IS NOT NULL AND card_number != '' AND customer_id != ?");
            $stmt->execute([$value, $customer_id]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ رقم الكارنية موجود بالفعل'; }
            break;

        case 'patient_card_number':
            $stmt = $db->prepare("SELECT id FROM customer_contracts WHERE patient_card_number = ? AND patient_card_number IS NOT NULL AND patient_card_number != '' AND customer_id != ?");
            $stmt->execute([$value, $customer_id]);
            if ($stmt->fetch()) { $valid = false; $message = '⚠️ رقم بطاقة المريض موجود بالفعل'; }
            break;
    }

    echo json_encode(['valid' => $valid, 'message' => $message]);
    exit;
}

// Get related data
$phones = $db->prepare("SELECT * FROM customer_phones WHERE customer_id = ? ORDER BY is_primary DESC");
$phones->execute([$customer_id]);
$phones = $phones->fetchAll();

$addresses = $db->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_primary DESC");
$addresses->execute([$customer_id]);
$addresses = $addresses->fetchAll();

$employees = $db->prepare("SELECT * FROM company_employees WHERE customer_id = ? AND is_active = 1");
$employees->execute([$customer_id]);
$employees = $employees->fetchAll();

// Get contract info
$contract_stmt = $db->prepare("SELECT * FROM customer_contracts WHERE customer_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
$contract_stmt->execute([$customer_id]);
$customer_contract = $contract_stmt->fetch();

// Get lookup data
$branches = $db->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name")->fetchAll();
$customer_classes = $db->query("SELECT * FROM customer_classes WHERE is_active = 1 ORDER BY class_name_ar")->fetchAll();
$country_codes = $db->query("SELECT * FROM country_codes WHERE is_active = 1 ORDER BY country_name_ar")->fetchAll();
$governorates = $db->query("SELECT * FROM governorates WHERE is_active = 1 ORDER BY governorate_name_ar")->fetchAll();
$delivery_zones = $db->query("SELECT * FROM delivery_zones WHERE is_active = 1 ORDER BY zone_name_ar")->fetchAll();

// Helper functions
function old($field, $default = '') {
    return isset($_POST[$field]) ? htmlspecialchars($_POST[$field]) : htmlspecialchars($default);
}
function oldCheck($field, $value = 1) {
    return isset($_POST[$field]) && $_POST[$field] == $value ? 'checked' : '';
}
function oldSelect($field, $value) {
    return isset($_POST[$field]) && $_POST[$field] == $value ? 'selected' : '';
}
function dataSelect($field, $value, $data) {
    return ($data[$field] ?? '') == $value ? 'selected' : '';
}
function dataCheck($field, $data) {
    return ($data[$field] ?? 0) ? 'checked' : '';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $errors = [];

        $customer_name = trim($_POST['customer_name'] ?? '');
        if (empty($customer_name)) {
            $errors[] = 'اسم العميل مطلوب';
        }

        // Validate contract fields if has_contract is checked
        if (isset($_POST['has_contract']) && !empty($_POST['contract_number'])) {
            $stmt = $db->prepare("SELECT id FROM customer_contracts WHERE contract_number = ? AND customer_id != ?");
            $stmt->execute([$_POST['contract_number'], $customer_id]);
            if ($stmt->fetch()) {
                $errors[] = 'رقم التعاقد موجود بالفعل';
            }

            if (!empty($_POST['card_number'])) {
                $stmt = $db->prepare("SELECT id FROM customer_contracts WHERE card_number = ? AND card_number IS NOT NULL AND card_number != '' AND customer_id != ?");
                $stmt->execute([$_POST['card_number'], $customer_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'رقم الكارنية موجود بالفعل';
                }
            }

            if (!empty($_POST['patient_card_number'])) {
                $stmt = $db->prepare("SELECT id FROM customer_contracts WHERE patient_card_number = ? AND patient_card_number IS NOT NULL AND patient_card_number != '' AND customer_id != ?");
                $stmt->execute([$_POST['patient_card_number'], $customer_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'رقم بطاقة المريض موجود بالفعل';
                }
            }
        }

        if (!empty($errors)) {
            throw new Exception(implode('<br>', $errors));
        }

        $customer_type = $_POST['customer_type'] ?? 'individual';
        $payment_type = $_POST['payment_type'] ?? 'cash';
        $credit_limit = floatval($_POST['credit_limit'] ?? 0);

        // Update customer
        $sql = "UPDATE customers SET
            customer_name = :customer_name,
            customer_name_en = :customer_name_en,
            customer_type = :customer_type,
            customer_class_id = :customer_class_id,
            payment_type = :payment_type,
            credit_limit = :credit_limit,
            branch_id = :branch_id,
            phone = :phone,
            email = :email,
            address = :address,
            notes = :notes,
            is_active = :is_active
            WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':customer_name' => $customer_name,
            ':customer_name_en' => !empty($_POST['customer_name_en']) ? $_POST['customer_name_en'] : null,
            ':customer_type' => $customer_type,
            ':customer_class_id' => !empty($_POST['customer_class_id']) ? intval($_POST['customer_class_id']) : null,
            ':payment_type' => $payment_type,
            ':credit_limit' => $payment_type == 'credit' ? $credit_limit : 0,
            ':branch_id' => !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null,
            ':phone' => !empty($_POST['phone']) ? $_POST['phone'] : null,
            ':email' => !empty($_POST['email']) ? $_POST['email'] : null,
            ':address' => !empty($_POST['address']) ? $_POST['address'] : null,
            ':notes' => $_POST['notes'] ?? null,
            ':is_active' => isset($_POST['is_active']) ? 1 : 0,
            ':id' => $customer_id
        ]);

        // Delete old phones and re-insert
        $db->prepare("DELETE FROM customer_phones WHERE customer_id = ?")->execute([$customer_id]);
        if (!empty($_POST['phones'])) {
            $phone_stmt = $db->prepare("INSERT INTO customer_phones (customer_id, country_code, phone_number, phone_type, is_primary, is_whatsapp) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['phones'] as $index => $phone_data) {
                if (!empty($phone_data['number'])) {
                    $phone_stmt->execute([
                        $customer_id,
                        $phone_data['country_code'] ?? '+20',
                        $phone_data['number'],
                        $phone_data['type'] ?? 'mobile',
                        $index === 0 ? 1 : 0,
                        isset($phone_data['whatsapp']) ? 1 : 0
                    ]);
                }
            }
        }

        // Delete old addresses and re-insert
        $db->prepare("DELETE FROM customer_addresses WHERE customer_id = ?")->execute([$customer_id]);
        if (!empty($_POST['addresses'])) {
            $addr_stmt = $db->prepare("INSERT INTO customer_addresses 
                (customer_id, address_type, building_number, floor_number, apartment_number, street_name, landmark, area_id, governorate_id, delivery_zone_id, is_primary) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['addresses'] as $index => $addr) {
                if (!empty($addr['street_name'])) {
                    $addr_stmt->execute([
                        $customer_id,
                        $addr['type'] ?? 'home',
                        $addr['building'] ?? null,
                        $addr['floor'] ?? null,
                        $addr['apartment'] ?? null,
                        $addr['street_name'],
                        $addr['landmark'] ?? null,
                        !empty($addr['area_id']) ? intval($addr['area_id']) : null,
                        !empty($addr['governorate_id']) ? intval($addr['governorate_id']) : null,
                        !empty($addr['zone_id']) ? intval($addr['zone_id']) : null,
                        $index === 0 ? 1 : 0
                    ]);
                }
            }
        }

        // Delete old employees and re-insert if company
        $db->prepare("DELETE FROM company_employees WHERE customer_id = ?")->execute([$customer_id]);
        if ($customer_type == 'company' && !empty($_POST['employees'])) {
            $emp_stmt = $db->prepare("INSERT INTO company_employees 
                (customer_id, employee_name, employee_name_en, national_id, phone, email, job_title, department) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['employees'] as $emp) {
                if (!empty($emp['name'])) {
                    $emp_stmt->execute([
                        $customer_id,
                        $emp['name'],
                        $emp['name_en'] ?? null,
                        $emp['national_id'] ?? null,
                        $emp['phone'] ?? null,
                        $emp['email'] ?? null,
                        $emp['job_title'] ?? null,
                        $emp['department'] ?? null
                    ]);
                }
            }
        }

        // Update contract
        if (isset($_POST['has_contract']) && !empty($_POST['contract_number'])) {
            $db->prepare("DELETE FROM customer_contracts WHERE customer_id = ?")->execute([$customer_id]);
            $contract_stmt = $db->prepare("INSERT INTO customer_contracts 
                (customer_id, contract_number, contract_type, card_number, patient_card_number, expiry_date, coverage_percent, max_bill_amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $contract_stmt->execute([
                $customer_id,
                $_POST['contract_number'],
                $_POST['contract_type'] ?? 'insurance',
                !empty($_POST['card_number']) ? $_POST['card_number'] : null,
                !empty($_POST['patient_card_number']) ? $_POST['patient_card_number'] : null,
                !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                floatval($_POST['coverage_percent'] ?? 100),
                floatval($_POST['max_bill_amount'] ?? 0)
            ]);
        } else {
            $db->prepare("DELETE FROM customer_contracts WHERE customer_id = ?")->execute([$customer_id]);
        }

        $db->commit();
        header("Location: view.php?id=" . $customer_id);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
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
        .phone-row, .address-row, .employee-row { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 15px; position: relative; }
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
                <h2><i class="bi bi-pencil-square"></i> <?= $page_title ?></h2>
                <div>
                    <a href="view.php?id=<?= $customer_id ?>" class="btn btn-info me-2"><i class="bi bi-eye"></i> عرض</a>
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> العودة</a>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="customerForm">
                <div class="card">
                    <div class="card-header p-0">
                        <ul class="nav nav-tabs" id="customerTabs" role="tablist">
                            <li class="nav-item"><a class="nav-link active" id="basic-tab" data-bs-toggle="tab" href="#basic" role="tab"><i class="bi bi-info-circle"></i> البيانات الأساسية</a></li>
                            <li class="nav-item"><a class="nav-link" id="phones-tab" data-bs-toggle="tab" href="#phones" role="tab"><i class="bi bi-telephone"></i> الهواتف</a></li>
                            <li class="nav-item"><a class="nav-link" id="addresses-tab" data-bs-toggle="tab" href="#addresses" role="tab"><i class="bi bi-geo-alt"></i> العناوين</a></li>
                            <li class="nav-item"><a class="nav-link" id="company-tab" data-bs-toggle="tab" href="#company" role="tab" style="display:<?= $customer['customer_type'] == 'company' ? 'block' : 'none' ?>;"><i class="bi bi-building"></i> الشركة</a></li>
                            <li class="nav-item"><a class="nav-link" id="contract-tab" data-bs-toggle="tab" href="#contract" role="tab"><i class="bi bi-file-earmark-text"></i> التعاقد</a></li>
                            <li class="nav-item"><a class="nav-link" id="settings-tab" data-bs-toggle="tab" href="#settings" role="tab"><i class="bi bi-gear"></i> الإعدادات</a></li>
                        </ul>
                    </div>

                    <div class="card-body">
                        <div class="tab-content" id="customerTabContent">

                            <!-- Basic Info Tab -->
                            <div class="tab-pane fade show active" id="basic" role="tabpanel">
                                <h5 class="section-title">معلومات العميل الأساسية</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">الكود التسلسلي</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($customer['customer_code'] ?? $customer['id']) ?>" readonly style="background: #e9ecef;">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">فرع العميل</label>
                                            <select name="branch_id" class="form-select">
                                                <option value="">-- اختر الفرع --</option>
                                                <?php foreach ($branches as $branch): ?>
                                                    <option value="<?= $branch['id'] ?>" <?= dataSelect('branch_id', $branch['id'], $customer) ?>><?= $branch['branch_name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم العميل بالعربي <span class="text-danger">*</span></label>
                                            <input type="text" name="customer_name" class="form-control" required value="<?= old('customer_name', $customer['customer_name']) ?>" placeholder="اسم العميل" onblur="validateField(this, 'customer_name')">
                                            <div class="validation-msg"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">اسم العميل بالإنجليزي</label>
                                            <input type="text" name="customer_name_en" class="form-control" value="<?= old('customer_name_en', $customer['customer_name_en'] ?? '') ?>" placeholder="Customer Name" onblur="validateField(this, 'customer_name_en')">
                                            <div class="validation-msg"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">البريد الإلكتروني</label>
                                            <input type="email" name="email" class="form-control" value="<?= old('email', $customer['email'] ?? '') ?>" placeholder="email@example.com" onblur="validateField(this, 'email')">
                                            <div class="validation-msg"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">نوع العميل</label>
                                            <select name="customer_type" id="customer_type" class="form-select" onchange="toggleCompanyTab()">
                                                <option value="individual" <?= dataSelect('customer_type', 'individual', $customer) ?>>فرد</option>
                                                <option value="company" <?= dataSelect('customer_type', 'company', $customer) ?>>شركة</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">ملاحظات</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات عامة..."><?= old('notes', $customer['notes'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Phones Tab -->
                            <div class="tab-pane fade" id="phones" role="tabpanel">
                                <h5 class="section-title">أرقام الهاتف</h5>
                                <div id="phonesContainer">
                                    <?php if (!empty($phones)): ?>
                                        <?php foreach ($phones as $pIndex => $phone): ?>
                                        <div class="phone-row">
                                            <span class="remove-btn" onclick="this.closest('.phone-row').remove()" <?= $pIndex === 0 ? 'style="display:none;"' : '' ?>><i class="bi bi-trash"></i></span>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label class="form-label">كود الدولة</label>
                                                    <div class="country-select">
                                                        <span class="flag"><?= htmlspecialchars($phone['country_code'] == '+20' ? '🇪🇬' : '') ?></span>
                                                        <select name="phones[<?= $pIndex ?>][country_code]" class="form-select country-code-select" onchange="updateFlag(this)" id="country_code_<?= $pIndex ?>">
                                                            <?php foreach ($country_codes as $cc): ?>
                                                                <option value="<?= $cc['country_code'] ?>" data-flag="<?= $cc['flag_emoji'] ?>" <?= $phone['country_code'] == $cc['country_code'] ? 'selected' : '' ?>><?= $cc['country_name_ar'] ?> (<?= $cc['country_code'] ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">رقم الهاتف</label>
                                                    <input type="text" name="phones[<?= $pIndex ?>][number]" class="form-control" value="<?= htmlspecialchars($phone['phone_number']) ?>" placeholder="01xxxxxxxxx" onblur="validatePhoneField(this, <?= $pIndex ?>)">
                                                    <div class="validation-msg" id="phone_msg_<?= $pIndex ?>"></div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">نوع</label>
                                                    <select name="phones[<?= $pIndex ?>][type]" class="form-select">
                                                        <option value="mobile" <?= $phone['phone_type'] == 'mobile' ? 'selected' : '' ?>>موبايل</option>
                                                        <option value="home" <?= $phone['phone_type'] == 'home' ? 'selected' : '' ?>>منزل</option>
                                                        <option value="work" <?= $phone['phone_type'] == 'work' ? 'selected' : '' ?>>عمل</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">واتساب</label>
                                                    <div class="form-check">
                                                        <input type="checkbox" name="phones[<?= $pIndex ?>][whatsapp]" value="1" class="form-check-input" <?= $phone['is_whatsapp'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label">مفعل</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="phone-row">
                                            <span class="remove-btn" onclick="this.closest('.phone-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label class="form-label">كود الدولة</label>
                                                    <div class="country-select">
                                                        <span class="flag">🇪🇬</span>
                                                        <select name="phones[0][country_code]" class="form-select country-code-select" onchange="updateFlag(this)" id="country_code_0">
                                                            <?php foreach ($country_codes as $cc): ?>
                                                                <option value="<?= $cc['country_code'] ?>" data-flag="<?= $cc['flag_emoji'] ?>" <?= $cc['country_code'] == '+20' ? 'selected' : '' ?>><?= $cc['country_name_ar'] ?> (<?= $cc['country_code'] ?>)</option>
                                                            <?php endforeach; ?>
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
                                                        <option value="home">منزل</option>
                                                        <option value="work">عمل</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">واتساب</label>
                                                    <div class="form-check">
                                                        <input type="checkbox" name="phones[0][whatsapp]" value="1" class="form-check-input">
                                                        <label class="form-check-label">مفعل</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addPhone()">
                                    <i class="bi bi-plus-lg"></i> إضافة رقم هاتف
                                </button>
                            </div>

                            <!-- Addresses Tab -->
                            <div class="tab-pane fade" id="addresses" role="tabpanel">
                                <h5 class="section-title">عناوين العميل</h5>
                                <div id="addressesContainer">
                                    <?php if (!empty($addresses)): ?>
                                        <?php foreach ($addresses as $aIndex => $addr): ?>
                                        <div class="address-row">
                                            <span class="remove-btn" onclick="this.closest('.address-row').remove()" <?= $aIndex === 0 ? 'style="display:none;"' : '' ?>><i class="bi bi-trash"></i></span>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label class="form-label">نوع العنوان</label>
                                                    <select name="addresses[<?= $aIndex ?>][type]" class="form-select">
                                                        <option value="home" <?= $addr['address_type'] == 'home' ? 'selected' : '' ?>>منزل</option>
                                                        <option value="work" <?= $addr['address_type'] == 'work' ? 'selected' : '' ?>>عمل</option>
                                                        <option value="other" <?= $addr['address_type'] == 'other' ? 'selected' : '' ?>>آخر</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">رقم العمارة/الفيلا</label>
                                                    <input type="text" name="addresses[<?= $aIndex ?>][building]" class="form-control" value="<?= htmlspecialchars($addr['building_number'] ?? '') ?>" placeholder="مثال: 15">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">الدور</label>
                                                    <input type="text" name="addresses[<?= $aIndex ?>][floor]" class="form-control" value="<?= htmlspecialchars($addr['floor_number'] ?? '') ?>" placeholder="مثال: 3">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">الشقة</label>
                                                    <input type="text" name="addresses[<?= $aIndex ?>][apartment]" class="form-control" value="<?= htmlspecialchars($addr['apartment_number'] ?? '') ?>" placeholder="مثال: 5">
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">اسم الشارع</label>
                                                    <input type="text" name="addresses[<?= $aIndex ?>][street_name]" class="form-control" value="<?= htmlspecialchars($addr['street_name'] ?? '') ?>" placeholder="اسم الشارع">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">علامة مميزة</label>
                                                    <input type="text" name="addresses[<?= $aIndex ?>][landmark]" class="form-control" value="<?= htmlspecialchars($addr['landmark'] ?? '') ?>" placeholder="بجوار... / أمام...">
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">المحافظة</label>
                                                    <select name="addresses[<?= $aIndex ?>][governorate_id]" class="form-select governorate-select" onchange="loadAreas(this, <?= $aIndex ?>)">
                                                        <option value="">-- اختر المحافظة --</option>
                                                        <?php foreach ($governorates as $gov): ?>
                                                            <option value="<?= $gov['id'] ?>" <?= $addr['governorate_id'] == $gov['id'] ? 'selected' : '' ?>><?= $gov['governorate_name_ar'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">المنطقة</label>
                                                    <select name="addresses[<?= $aIndex ?>][area_id]" class="form-select area-select" id="area_<?= $aIndex ?>">
                                                        <option value="">-- اختر المنطقة --</option>
                                                        <?php if ($addr['governorate_id']): 
                                                            $areas_list = $db->prepare("SELECT id, area_name_ar FROM areas WHERE governorate_id = ? AND is_active = 1");
                                                            $areas_list->execute([$addr['governorate_id']]);
                                                            foreach ($areas_list->fetchAll() as $area): ?>
                                                            <option value="<?= $area['id'] ?>" <?= $addr['area_id'] == $area['id'] ? 'selected' : '' ?>><?= $area['area_name_ar'] ?></option>
                                                        <?php endforeach; endif; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">زون التوصيل</label>
                                                    <select name="addresses[<?= $aIndex ?>][zone_id]" class="form-select">
                                                        <option value="">-- اختر الزون --</option>
                                                        <?php foreach ($delivery_zones as $zone): ?>
                                                            <option value="<?= $zone['id'] ?>" <?= $addr['delivery_zone_id'] == $zone['id'] ? 'selected' : '' ?>><?= $zone['zone_name_ar'] ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="address-row">
                                            <span class="remove-btn" onclick="this.closest('.address-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label class="form-label">نوع العنوان</label>
                                                    <select name="addresses[0][type]" class="form-select">
                                                        <option value="home">منزل</option>
                                                        <option value="work">عمل</option>
                                                        <option value="other">آخر</option>
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
                                                    <label class="form-label">اسم الشارع</label>
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
                                                        <?php foreach ($delivery_zones as $zone): ?>
                                                            <option value="<?= $zone['id'] ?>"><?= $zone['zone_name_ar'] ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addAddress()">
                                    <i class="bi bi-plus-lg"></i> إضافة عنوان
                                </button>
                            </div>

                            <!-- Company Tab -->
                            <div class="tab-pane fade" id="company" role="tabpanel">
                                <h5 class="section-title">موظفين الشركة</h5>
                                <div id="employeesContainer">
                                    <?php if (!empty($employees)): ?>
                                        <?php foreach ($employees as $eIndex => $emp): ?>
                                        <div class="employee-row">
                                            <span class="remove-btn" onclick="this.closest('.employee-row').remove()"><i class="bi bi-trash"></i></span>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">اسم الموظف</label>
                                                    <input type="text" name="employees[<?= $eIndex ?>][name]" class="form-control" value="<?= htmlspecialchars($emp['employee_name']) ?>" placeholder="اسم الموظف">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">الرقم القومي</label>
                                                    <input type="text" name="employees[<?= $eIndex ?>][national_id]" class="form-control" value="<?= htmlspecialchars($emp['national_id'] ?? '') ?>" placeholder="الرقم القومي">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">رقم الهاتف</label>
                                                    <input type="text" name="employees[<?= $eIndex ?>][phone]" class="form-control" value="<?= htmlspecialchars($emp['phone'] ?? '') ?>" placeholder="رقم الهاتف">
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">البريد الإلكتروني</label>
                                                    <input type="email" name="employees[<?= $eIndex ?>][email]" class="form-control" value="<?= htmlspecialchars($emp['email'] ?? '') ?>" placeholder="email@example.com">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">المسمى الوظيفي</label>
                                                    <input type="text" name="employees[<?= $eIndex ?>][job_title]" class="form-control" value="<?= htmlspecialchars($emp['job_title'] ?? '') ?>" placeholder="مثال: مدير">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">القسم</label>
                                                    <input type="text" name="employees[<?= $eIndex ?>][department]" class="form-control" value="<?= htmlspecialchars($emp['department'] ?? '') ?>" placeholder="مثال: المبيعات">
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="employee-row">
                                            <span class="remove-btn" onclick="this.closest('.employee-row').remove()" style="display:none;"><i class="bi bi-trash"></i></span>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">اسم الموظف</label>
                                                    <input type="text" name="employees[0][name]" class="form-control" placeholder="اسم الموظف">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">الرقم القومي</label>
                                                    <input type="text" name="employees[0][national_id]" class="form-control" placeholder="الرقم القومي">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">رقم الهاتف</label>
                                                    <input type="text" name="employees[0][phone]" class="form-control" placeholder="رقم الهاتف">
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">البريد الإلكتروني</label>
                                                    <input type="email" name="employees[0][email]" class="form-control" placeholder="email@example.com">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">المسمى الوظيفي</label>
                                                    <input type="text" name="employees[0][job_title]" class="form-control" placeholder="مثال: مدير">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">القسم</label>
                                                    <input type="text" name="employees[0][department]" class="form-control" placeholder="مثال: المبيعات">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="addEmployee()">
                                    <i class="bi bi-plus-lg"></i> إضافة موظف
                                </button>
                            </div>

                            <!-- Contract Tab -->
                            <div class="tab-pane fade" id="contract" role="tabpanel">
                                <h5 class="section-title">بيانات التعاقد</h5>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="has_contract" value="1" class="form-check-input" id="has_contract" onchange="toggleContractFields()" <?= !empty($customer_contract) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="has_contract">عميل متعاقد</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="contractFields" style="display: <?= !empty($customer_contract) ? 'block' : 'none' ?>;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">رقم التعاقد</label>
                                                <input type="text" name="contract_number" class="form-control" value="<?= htmlspecialchars($customer_contract['contract_number'] ?? '') ?>" placeholder="رقم التعاقد" onblur="validateField(this, 'contract_number')">
                                                <div class="validation-msg"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نوع التعاقد</label>
                                                <select name="contract_type" class="form-select">
                                                    <option value="insurance" <?= ($customer_contract['contract_type'] ?? '') == 'insurance' ? 'selected' : '' ?>>تأمين</option>
                                                    <option value="company" <?= ($customer_contract['contract_type'] ?? '') == 'company' ? 'selected' : '' ?>>شركة</option>
                                                    <option value="government" <?= ($customer_contract['contract_type'] ?? '') == 'government' ? 'selected' : '' ?>>حكومي</option>
                                                    <option value="other" <?= ($customer_contract['contract_type'] ?? '') == 'other' ? 'selected' : '' ?>>آخر</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">تاريخ انتهاء التعاقد</label>
                                                <input type="date" name="expiry_date" class="form-control" value="<?= !empty($customer_contract['expiry_date']) ? date('Y-m-d', strtotime($customer_contract['expiry_date'])) : '' ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">رقم الكارنية</label>
                                                <input type="text" name="card_number" class="form-control" value="<?= htmlspecialchars($customer_contract['card_number'] ?? '') ?>" placeholder="رقم الكارنية" onblur="validateField(this, 'card_number')">
                                                <div class="validation-msg"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">رقم بطاقة المريض</label>
                                                <input type="text" name="patient_card_number" class="form-control" value="<?= htmlspecialchars($customer_contract['patient_card_number'] ?? '') ?>" placeholder="رقم بطاقة المريض" onblur="validateField(this, 'patient_card_number')">
                                                <div class="validation-msg"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">نسبة التغطية (%)</label>
                                                <input type="number" name="coverage_percent" class="form-control" min="0" max="100" step="0.01" value="<?= number_format($customer_contract['coverage_percent'] ?? 100, 2) ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">الحد الأقصى للفاتورة</label>
                                                <div class="input-group">
                                                    <input type="number" name="max_bill_amount" class="form-control" step="0.01" value="<?= number_format($customer_contract['max_bill_amount'] ?? 0, 2) ?>">
                                                    <span class="input-group-text">ج</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Settings Tab -->
                            <div class="tab-pane fade" id="settings" role="tabpanel">
                                <h5 class="section-title">إعدادات العميل</h5>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">تصنيف العميل</label>
                                            <select name="customer_class_id" id="customer_class" class="form-select" onchange="toggleClassFields()">
                                                <option value="">-- اختر التصنيف --</option>
                                                <?php foreach ($customer_classes as $class): ?>
                                                    <option value="<?= $class['id'] ?>" data-type="<?= $class['class_type'] ?>" <?= dataSelect('customer_class_id', $class['id'], $customer) ?>><?= $class['class_name_ar'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">طريقة الدفع</label>
                                            <select name="payment_type" id="payment_type" class="form-select" onchange="toggleCreditFields()">
                                                <option value="cash" <?= dataSelect('payment_type', 'cash', $customer) ?>>نقدي</option>
                                                <option value="credit" <?= dataSelect('payment_type', 'credit', $customer) ?>>آجل</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">الحالة</label>
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" <?= dataCheck('is_active', $customer) ?>>
                                                <label class="form-check-label" for="is_active">عميل نشط</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Credit Fields -->
                                <div id="creditFields" style="display: <?= ($customer['payment_type'] ?? 'cash') == 'credit' ? 'block' : 'none' ?>;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">الحد الأقصى للآجل</label>
                                                <div class="input-group">
                                                    <input type="number" name="credit_limit" class="form-control" step="0.01" value="<?= old('credit_limit', $customer['credit_limit'] ?? '0') ?>">
                                                    <span class="input-group-text">ج</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">باسورد التجاوز</label>
                                                <input type="password" name="credit_password" class="form-control" placeholder="اتركه فارغاً للإبقاء على القديم">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Wholesale Fields -->
                                <?php 
                                $class_type = '';
                                foreach ($customer_classes as $class) {
                                    if ($class['id'] == ($customer['customer_class_id'] ?? 0)) {
                                        $class_type = $class['class_type'];
                                        break;
                                    }
                                }
                                ?>
                                <div id="wholesaleFields" style="display: <?= $class_type == 'wholesale' ? 'block' : 'none' ?>;">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> هامش ربح الصيدلية (جملة)
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">هامش ربح الصيدلية - محلي (%)</label>
                                                <input type="number" name="local_margin" class="form-control" step="0.01" min="0" max="100" value="<?= old('local_margin', $customer['local_margin'] ?? '0') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">هامش ربح الصيدلية - مستورد (%)</label>
                                                <input type="number" name="imported_margin" class="form-control" step="0.01" min="0" max="100" value="<?= old('imported_margin', $customer['imported_margin'] ?? '0') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Retail Fields -->
                                <div id="retailFields" style="display: <?= $class_type == 'retail' ? 'block' : 'none' ?>;">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> نسبة الخصم للعميل (تجزئة)
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">نسبة الخصم - محلي (%)</label>
                                                <input type="number" name="local_discount" class="form-control" step="0.01" min="0" max="100" value="<?= old('local_discount', $customer['local_discount'] ?? '0') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">نسبة الخصم - مستورد (%)</label>
                                                <input type="number" name="imported_discount" class="form-control" step="0.01" min="0" max="100" value="<?= old('imported_discount', $customer['imported_discount'] ?? '0') ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cost Fields -->
                                <div id="costFields" style="display: <?= $class_type == 'cost' ? 'block' : 'none' ?>;">
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i> العميل سيتم بيع الأصناف له بسعر التكلفة
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> حفظ التعديلات
                        </button>
                        <a href="view.php?id=<?= $customer_id ?>" class="btn btn-secondary btn-lg">
                            <i class="bi bi-x-lg"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle Company Tab
    function toggleCompanyTab() {
        const type = document.getElementById('customer_type').value;
        const companyTab = document.getElementById('company-tab');
        companyTab.style.display = type === 'company' ? 'block' : 'none';
    }

    // Toggle Credit Fields
    function toggleCreditFields() {
        const paymentType = document.getElementById('payment_type').value;
        document.getElementById('creditFields').style.display = paymentType === 'credit' ? 'block' : 'none';
    }

    // Toggle Class Fields - FIXED: Added costFields
    function toggleClassFields() {
        const select = document.getElementById('customer_class');
        const type = select.options[select.selectedIndex].dataset.type;
        document.getElementById('wholesaleFields').style.display = type === 'wholesale' ? 'block' : 'none';
        document.getElementById('retailFields').style.display = type === 'retail' ? 'block' : 'none';
        document.getElementById('costFields').style.display = type === 'cost' ? 'block' : 'none';
    }

    // Toggle Contract Fields - FIXED: Added missing function
    function toggleContractFields() {
        const hasContract = document.getElementById('has_contract').checked;
        document.getElementById('contractFields').style.display = hasContract ? 'block' : 'none';
    }

    // Update Flag Emoji
    function updateFlag(select) {
        const flag = select.options[select.selectedIndex].dataset.flag;
        select.closest('.country-select').querySelector('.flag').textContent = flag;
    }

    // Live Validation - FIXED: Added missing function
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

    // Phone Validation with Country Code - FIXED: Added missing function
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
                            <?php foreach ($country_codes as $cc): ?>
                                <option value="<?= $cc['country_code'] ?>" data-flag="<?= $cc['flag_emoji'] ?>" <?= $cc['country_code'] == '+20' ? 'selected' : '' ?>><?= $cc['country_name_ar'] ?> (<?= $cc['country_code'] ?>)</option>
                            <?php endforeach; ?>
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
                        <option value="home">منزل</option>
                        <option value="work">عمل</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">واتساب</label>
                    <div class="form-check">
                        <input type="checkbox" name="phones[${phoneIndex}][whatsapp]" value="1" class="form-check-input">
                        <label class="form-check-label">مفعل</label>
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
                        <option value="home">منزل</option>
                        <option value="work">عمل</option>
                        <option value="other">آخر</option>
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
                    <label class="form-label">اسم الشارع</label>
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
                        <?php foreach ($delivery_zones as $zone): ?>
                            <option value="<?= $zone['id'] ?>"><?= $zone['zone_name_ar'] ?> (<?= $zone['delivery_fee'] ?> ج)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        `;
        container.appendChild(newRow);
        addressIndex++;
    }

    // Add Employee
    let employeeIndex = 1;
    function addEmployee() {
        const container = document.getElementById('employeesContainer');
        const newRow = document.createElement('div');
        newRow.className = 'employee-row';
        newRow.innerHTML = `
            <span class="remove-btn" onclick="this.closest('.employee-row').remove()"><i class="bi bi-trash"></i></span>
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">اسم الموظف</label>
                    <input type="text" name="employees[${employeeIndex}][name]" class="form-control" placeholder="اسم الموظف">
                </div>
                <div class="col-md-4">
                    <label class="form-label">الرقم القومي</label>
                    <input type="text" name="employees[${employeeIndex}][national_id]" class="form-control" placeholder="الرقم القومي">
                </div>
                <div class="col-md-4">
                    <label class="form-label">رقم الهاتف</label>
                    <input type="text" name="employees[${employeeIndex}][phone]" class="form-control" placeholder="رقم الهاتف">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="employees[${employeeIndex}][email]" class="form-control" placeholder="email@example.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label">المسمى الوظيفي</label>
                    <input type="text" name="employees[${employeeIndex}][job_title]" class="form-control" placeholder="مثال: مدير">
                </div>
                <div class="col-md-4">
                    <label class="form-label">القسم</label>
                    <input type="text" name="employees[${employeeIndex}][department]" class="form-control" placeholder="مثال: المبيعات">
                </div>
            </div>
        `;
        container.appendChild(newRow);
        employeeIndex++;
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

    // Initialize - FIXED: Added toggleContractFields and toggleClassFields
    document.addEventListener('DOMContentLoaded', function() {
        toggleCompanyTab();
        toggleCreditFields();
        toggleClassFields();
        toggleContractFields();
    });
    </script>
</body>
</html>