<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$field = $_GET['field'] ?? '';
$value = trim($_GET['value'] ?? '');
$exclude_id = intval($_GET['exclude_id'] ?? 0);

if (empty($field) || empty($value)) {
    echo json_encode(['exists' => false, 'error' => 'Missing parameters']);
    exit;
}

$allowed_fields = ['product_name', 'product_name_en', 'barcode', 'qr_code', 'manual_code'];
if (!in_array($field, $allowed_fields)) {
    echo json_encode(['exists' => false, 'error' => 'Invalid field']);
    exit;
}

try {
    switch ($field) {
        case 'product_name':
        case 'product_name_en':
        case 'manual_code':
            $sql = "SELECT id FROM products WHERE {$field} = :value AND is_active = 1";
            if ($exclude_id > 0) {
                $sql .= " AND id != :exclude_id";
            }
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':value', $value);
            if ($exclude_id > 0) {
                $stmt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
            }
            break;
            
        case 'barcode':
        case 'qr_code':
            $sql = "SELECT pb.id FROM product_barcodes pb 
                    JOIN products p ON pb.product_id = p.id 
                    WHERE pb.barcode = :value AND p.is_active = 1";
            if ($exclude_id > 0) {
                $sql .= " AND pb.product_id != :exclude_id";
            }
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':value', $value);
            if ($exclude_id > 0) {
                $stmt->bindValue(':exclude_id', $exclude_id, PDO::PARAM_INT);
            }
            break;
    }
    
    $stmt->execute();
    $exists = $stmt->fetch() !== false;
    
    echo json_encode([
        'exists' => $exists,
        'message' => $exists ? 'هذه القيمة مستخدمة بالفعل!' : 'متاح'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}