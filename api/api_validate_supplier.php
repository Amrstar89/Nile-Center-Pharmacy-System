<?php
require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$action = $_GET['action'] ?? '';
$response = ['valid' => true, 'message' => ''];

try {
    switch ($action) {
        case 'check_name':
            $name = trim($_GET['name'] ?? '');
            $exclude_id = intval($_GET['exclude_id'] ?? 0);
            if (empty($name)) {
                $response = ['valid' => false, 'message' => 'اسم المورد مطلوب'];
                break;
            }
            $sql = "SELECT id FROM suppliers WHERE supplier_name = ?";
            $params = [$name];
            if ($exclude_id > 0) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $response = ['valid' => false, 'message' => 'اسم المورد موجود بالفعل'];
            }
            break;

        case 'check_name_en':
            $name_en = trim($_GET['name_en'] ?? '');
            $exclude_id = intval($_GET['exclude_id'] ?? 0);
            if (empty($name_en)) {
                $response = ['valid' => true]; // English name is optional
                break;
            }
            $sql = "SELECT id FROM suppliers WHERE supplier_name_en = ?";
            $params = [$name_en];
            if ($exclude_id > 0) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $response = ['valid' => false, 'message' => 'الاسم الإنجليزي موجود بالفعل'];
            }
            break;

        case 'check_phone':
            $phone = trim($_GET['phone'] ?? '');
            $exclude_id = intval($_GET['exclude_id'] ?? 0);
            if (empty($phone)) {
                $response = ['valid' => false, 'message' => 'رقم الهاتف مطلوب'];
                break;
            }
            $sql = "SELECT sp.*, s.supplier_name FROM supplier_phones sp 
                    JOIN suppliers s ON sp.supplier_id = s.id 
                    WHERE sp.phone_number = ?";
            $params = [$phone];
            if ($exclude_id > 0) {
                $sql .= " AND sp.supplier_id != ?";
                $params[] = $exclude_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($existing = $stmt->fetch()) {
                $response = ['valid' => false, 'message' => 'رقم الهاتف مسجل لمورد: ' . $existing['supplier_name']];
            }
            break;

        case 'check_code':
            $code = trim($_GET['code'] ?? '');
            $exclude_id = intval($_GET['exclude_id'] ?? 0);
            if (empty($code)) {
                $response = ['valid' => false, 'message' => 'كود المورد مطلوب'];
                break;
            }
            $sql = "SELECT id FROM suppliers WHERE supplier_code = ?";
            $params = [$code];
            if ($exclude_id > 0) {
                $sql .= " AND id != ?";
                $params[] = $exclude_id;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                $response = ['valid' => false, 'message' => 'كود المورد موجود بالفعل'];
            }
            break;

        default:
            $response = ['valid' => false, 'message' => 'إجراء غير معروف'];
    }
} catch (PDOException $e) {
    $response = ['valid' => false, 'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()];
}

echo json_encode($response);