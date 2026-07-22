<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/customers                                                  ║
 * ║                                                                             ║
 *  Query: ?q=search&class_id=&area_id=&has_balance=0&page=1&per_page=25       ║
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
$areaId = !empty($params['area_id']) ? (int) $params['area_id'] : null;
$hasBalance = isset($params['has_balance']) ? (int) $params['has_balance'] : null;
$isActive = isset($params['is_active']) ? (int) $params['is_active'] : 1;
[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);
    
    $where = ["c.is_active = ?"];
    $bindParams = [$isActive];
    
    if ($search) {
        $where[] = "(c.customer_name LIKE ? OR c.phone LIKE ? OR c.phone2 LIKE ? OR c.code LIKE ?)";
        $like = "%{$search}%";
        array_push($bindParams, $like, $like, $like, $like);
    }
    if ($classId) {
        $where[] = "c.class_id = ?";
        $bindParams[] = $classId;
    }
    if ($areaId) {
        $where[] = "c.area_id = ?";
        $bindParams[] = $areaId;
    }
    if ($hasBalance === 1) {
        $where[] = "cb.balance != 0";
    }
    
    $whereSql = implode(' AND ', $where);
    
    // Count
    $countSql = "SELECT COUNT(*) FROM customers c 
                 LEFT JOIN customer_balances cb ON c.id = cb.customer_id 
                 WHERE {$whereSql}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($bindParams);
    $total = (int) $stmt->fetchColumn();
    
    // Main query
    $sql = "SELECT 
        c.id, c.code, c.customer_name, c.phone, c.phone2, c.email,
        c.address, c.address2, c.whatsapp,
        c.class_id, cc.class_name,
        c.area_id, a.area_name_ar as area_name,
        c.governorate_id, g.governorate_name_ar as governorate_name,
        c.max_credit_limit, c.max_credit_days, c.discount_percent,
        c.birth_date, c.is_active, c.created_at,
        COALESCE(cb.balance, 0) as balance,
        COALESCE(cb.total_debit, 0) as total_debit,
        COALESCE(cb.total_credit, 0) as total_credit
    FROM customers c
    LEFT JOIN customer_classes cc ON c.class_id = cc.id
    LEFT JOIN areas a ON c.area_id = a.id
    LEFT JOIN governorates g ON c.governorate_id = g.id
    LEFT JOIN customer_balances cb ON c.id = cb.customer_id
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
            'code' => $c['code'],
            'name' => $c['customer_name'],
            'phone' => $c['phone'],
            'phone2' => $c['phone2'],
            'whatsapp' => $c['whatsapp'],
            'email' => $c['email'],
            'address' => $c['address'],
            'address2' => $c['address2'],
            'area' => ['id' => (int) $c['area_id'], 'name' => $c['area_name']],
            'governorate' => ['id' => (int) $c['governorate_id'], 'name' => $c['governorate_name']],
            'class' => ['id' => (int) $c['class_id'], 'name' => $c['class_name']],
            'financial' => [
                'balance' => (float) $c['balance'],
                'total_debit' => (float) $c['total_debit'],
                'total_credit' => (float) $c['total_credit'],
                'max_credit_limit' => (float) $c['max_credit_limit'],
                'max_credit_days' => (int) $c['max_credit_days'],
                'discount_percent' => (float) $c['discount_percent'],
            ],
            'birth_date' => $c['birth_date'],
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
