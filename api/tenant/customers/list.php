<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/customers/list - MATCHES REAL DATABASE SCHEMA            ║
 * ║  customers: customer_code, customer_class_id, balance, credit_limit       ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

$tenant = requireTenant();
$params = apiParams();

$search = apiSanitize($params['q'] ?? '');
$classId = !empty($params['class_id']) ? (int) $params['class_id'] : null;
$type = apiSanitize($params['type'] ?? '');
$hasBalance = isset($params['has_balance']) ? (int) $params['has_balance'] : null;
$isActive = isset($params['is_active']) ? (int) $params['is_active'] : 1;
[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);

    $where = ["c.is_active = ?"];
    $bindParams = [$isActive];

    if ($search) {
        $where[] = "(c.customer_name LIKE ? OR c.phone LIKE ? OR c.customer_code LIKE ?)";
        $like = "%{$search}%";
        array_push($bindParams, $like, $like, $like);
    }
    if ($classId) { $where[] = "c.customer_class_id = ?"; $bindParams[] = $classId; }
    if ($type) { $where[] = "c.customer_type = ?"; $bindParams[] = $type; }
    if ($hasBalance === 1) { $where[] = "COALESCE(c.balance, 0) != 0"; }

    $whereSql = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) FROM customers c WHERE {$whereSql}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($bindParams);
    $total = (int) $stmt->fetchColumn();

    $sql = "SELECT 
        c.id, c.customer_code, c.customer_name, c.customer_name_en,
        c.customer_type, c.customer_class_id, cc.class_name,
        c.local_margin, c.imported_margin, c.local_discount, c.imported_discount,
        c.payment_type, c.credit_limit, c.phone, c.phone2, c.email,
        c.tax_number, c.address, c.balance, c.notes, c.is_active, c.created_at
    FROM customers c
    LEFT JOIN customer_classes cc ON c.customer_class_id = cc.id
    WHERE {$whereSql}
    ORDER BY c.customer_name ASC
    LIMIT ? OFFSET ?";

    $bindParams[] = $perPage;
    $bindParams[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($bindParams);
    $customers = $stmt->fetchAll();

    $formatted = array_map(function($c) {
        return [
            'id' => (int) $c['id'],
            'code' => $c['customer_code'],
            'name' => $c['customer_name'],
            'name_en' => $c['customer_name_en'],
            'type' => $c['customer_type'],
            'class' => ['id' => (int) $c['customer_class_id'], 'name' => $c['class_name']],
            'margins' => [
                'local_margin' => (float) $c['local_margin'],
                'imported_margin' => (float) $c['imported_margin'],
                'local_discount' => (float) $c['local_discount'],
                'imported_discount' => (float) $c['imported_discount'],
            ],
            'contact' => [
                'phone' => $c['phone'],
                'phone2' => $c['phone2'],
                'email' => $c['email'],
                'address' => $c['address'],
                'tax_number' => $c['tax_number'],
            ],
            'financial' => [
                'payment_type' => $c['payment_type'],
                'credit_limit' => (float) $c['credit_limit'],
                'balance' => (float) $c['balance'],
            ],
            'notes' => $c['notes'],
            'is_active' => (bool) $c['is_active'],
        ];
    }, $customers);

    apiSuccess([
        'customers' => $formatted,
        'pagination' => apiPaginationMeta($page, $perPage, $total),
    ]);

} catch (Exception $e) {
    error_log("[API Customers List] Error: " . $e->getMessage());
    apiError('LIST_ERROR', 'حدث خطأ أثناء جلب قائمة العملاء', 500);
}
