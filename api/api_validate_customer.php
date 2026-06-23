<?php
require_once __DIR__ . '/../core/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$field = $_GET['field'] ?? '';
$value = trim($_GET['value'] ?? '');
$customer_id = intval($_GET['customer_id'] ?? 0); // For edit mode (exclude current customer)

if (empty($field) || empty($value)) {
    echo json_encode(['valid' => true, 'message' => '']);
    exit;
}

$valid = true;
$message = '';

switch ($field) {
    case 'customer_name':
        // Check Arabic name uniqueness in customers table
        $sql = "SELECT id FROM customers WHERE customer_name = ?";
        $params = [$value];
        if ($customer_id > 0) {
            $sql .= " AND id != ?";
            $params[] = $customer_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $valid = false;
            $message = 'اسم العميل موجود بالفعل';
        }
        break;

    case 'customer_name_en':
        // Check English name uniqueness (only if not empty)
        $sql = "SELECT id FROM customers WHERE customer_name_en = ? AND customer_name_en IS NOT NULL AND customer_name_en != ''";
        $params = [$value];
        if ($customer_id > 0) {
            $sql .= " AND id != ?";
            $params[] = $customer_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $valid = false;
            $message = 'الاسم الإنجليزي موجود بالفعل';
        }
        break;

    case 'email':
        // Check email uniqueness (only if not empty)
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $valid = false;
            $message = 'بريد إلكتروني غير صالح';
        } else {
            $sql = "SELECT id FROM customers WHERE email = ? AND email IS NOT NULL AND email != ''";
            $params = [$value];
            if ($customer_id > 0) {
                $sql .= " AND id != ?";
                $params[] = $customer_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $valid = false;
                $message = 'البريد الإلكتروني مستخدم بالفعل';
            }
        }
        break;

    case 'phone':
        // Check phone uniqueness in customer_phones table
        $sql = "SELECT id FROM customer_phones WHERE phone_number = ?";
        $params = [$value];
        if ($customer_id > 0) {
            $sql .= " AND customer_id != ?";
            $params[] = $customer_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $valid = false;
            $message = 'رقم الهاتف مستخدم بالفعل';
        }
        break;

    case 'contract_number':
        // Check contract number uniqueness
        $sql = "SELECT id FROM customer_contracts WHERE contract_number = ?";
        $params = [$value];
        if ($customer_id > 0) {
            $sql .= " AND customer_id != ?";
            $params[] = $customer_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $valid = false;
            $message = 'رقم التعاقد موجود بالفعل';
        }
        break;

    case 'card_number':
        // Check card number uniqueness
        $sql = "SELECT id FROM customer_contracts WHERE card_number = ? AND card_number IS NOT NULL AND card_number != ''";
        $params = [$value];
        if ($customer_id > 0) {
            $sql .= " AND customer_id != ?";
            $params[] = $customer_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $valid = false;
            $message = 'رقم الكارنية موجود بالفعل';
        }
        break;

    case 'patient_card_number':
        // Check patient card number uniqueness
        $sql = "SELECT id FROM customer_contracts WHERE patient_card_number = ? AND patient_card_number IS NOT NULL AND patient_card_number != ''";
        $params = [$value];
        if ($customer_id > 0) {
            $sql .= " AND customer_id != ?";
            $params[] = $customer_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $valid = false;
            $message = 'رقم بطاقة المريض موجود بالفعل';
        }
        break;

    case 'manual_code':
        // Check manual_code uniqueness
        $sql = "SELECT id FROM customers WHERE manual_code = ? AND manual_code IS NOT NULL AND manual_code != ''";
        $params = [$value];
        if ($customer_id > 0) {
            $sql .= " AND id != ?";
            $params[] = $customer_id;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $valid = false;
            $message = 'الكود المختصر مستخدم بالفعل';
        }
        break;

    default:
        $valid = false;
        $message = 'حقل غير معروف';
}

echo json_encode(['valid' => $valid, 'message' => $message]);