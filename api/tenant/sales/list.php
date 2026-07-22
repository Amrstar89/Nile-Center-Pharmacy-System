<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/sales                                                      ║
 * ║                                                                             ║
 *  Query: ?customer_id=&branch_id=&from_date=&to_date=&status=&page=1          ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

$userPayload = apiAuth();
$tenant = requireTenant();
$params = apiParams();

$customerId = !empty($params['customer_id']) ? (int) $params['customer_id'] : null;
$branchId = !empty($params['branch_id']) ? (int) $params['branch_id'] : null;
$storeId = !empty($params['store_id']) ? (int) $params['store_id'] : null;
$fromDate = apiSanitize($params['from_date'] ?? '');
$toDate = apiSanitize($params['to_date'] ?? '');
$status = apiSanitize($params['status'] ?? '');
$minAmount = !empty($params['min_amount']) ? (float) $params['min_amount'] : null;
[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);
    
    // Ensure table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS sale_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(50) NOT NULL,
            customer_id INT,
            branch_id INT,
            store_id INT,
            user_id INT,
            payment_method VARCHAR(20) DEFAULT 'cash',
            subtotal DECIMAL(15,4) DEFAULT 0,
            discount DECIMAL(15,4) DEFAULT 0,
            tax DECIMAL(15,4) DEFAULT 0,
            final_total DECIMAL(15,4) DEFAULT 0,
            paid_amount DECIMAL(15,4) DEFAULT 0,
            remaining_amount DECIMAL(15,4) DEFAULT 0,
            total_profit DECIMAL(15,4) DEFAULT 0,
            notes TEXT,
            status VARCHAR(20) DEFAULT 'confirmed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (customer_id),
            INDEX (branch_id),
            INDEX (created_at),
            INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $where = ["1=1"];
    $bindParams = [];
    
    if ($customerId) {
        $where[] = "si.customer_id = ?";
        $bindParams[] = $customerId;
    }
    if ($branchId) {
        $where[] = "si.branch_id = ?";
        $bindParams[] = $branchId;
    }
    if ($storeId) {
        $where[] = "si.store_id = ?";
        $bindParams[] = $storeId;
    }
    if ($fromDate) {
        $where[] = "DATE(si.created_at) >= ?";
        $bindParams[] = $fromDate;
    }
    if ($toDate) {
        $where[] = "DATE(si.created_at) <= ?";
        $bindParams[] = $toDate;
    }
    if ($status) {
        $where[] = "si.status = ?";
        $bindParams[] = $status;
    }
    if ($minAmount) {
        $where[] = "si.final_total >= ?";
        $bindParams[] = $minAmount;
    }
    
    $whereSql = implode(' AND ', $where);
    
    // Count
    $countSql = "SELECT COUNT(*) FROM sale_invoices si WHERE {$whereSql}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($bindParams);
    $total = (int) $stmt->fetchColumn();
    
    // Main query
    $sql = "SELECT 
        si.id, si.invoice_number, si.customer_id, c.customer_name,
        si.branch_id, b.branch_name, si.store_id, s.store_name,
        si.user_id, u.full_name as user_name,
        si.payment_method, si.subtotal, si.discount, si.tax,
        si.final_total, si.paid_amount, si.remaining_amount, si.total_profit,
        si.notes, si.status, si.created_at,
        (SELECT COUNT(*) FROM sale_invoice_items sii WHERE sii.invoice_id = si.id) as items_count
    FROM sale_invoices si
    LEFT JOIN customers c ON si.customer_id = c.id
    LEFT JOIN branches b ON si.branch_id = b.id
    LEFT JOIN stores s ON si.store_id = s.id
    LEFT JOIN users u ON si.user_id = u.id
    WHERE {$whereSql}
    ORDER BY si.created_at DESC
    LIMIT ? OFFSET ?";
    
    $bindParams[] = $perPage;
    $bindParams[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($bindParams);
    $invoices = $stmt->fetchAll();
    
    $formatted = array_map(function($inv) {
        return [
            'id' => (int) $inv['id'],
            'invoice_number' => $inv['invoice_number'],
            'customer' => ['id' => (int) $inv['customer_id'], 'name' => $inv['customer_name']],
            'branch' => ['id' => (int) $inv['branch_id'], 'name' => $inv['branch_name']],
            'store' => ['id' => (int) $inv['store_id'], 'name' => $inv['store_name']],
            'user' => ['id' => (int) $inv['user_id'], 'name' => $inv['user_name']],
            'payment_method' => $inv['payment_method'],
            'financial' => [
                'subtotal' => (float) $inv['subtotal'],
                'discount' => (float) $inv['discount'],
                'tax' => (float) $inv['tax'],
                'final_total' => (float) $inv['final_total'],
                'paid' => (float) $inv['paid_amount'],
                'remaining' => (float) $inv['remaining_amount'],
                'profit' => (float) $inv['total_profit'],
            ],
            'items_count' => (int) $inv['items_count'],
            'status' => $inv['status'],
            'notes' => $inv['notes'],
            'created_at' => $inv['created_at'],
        ];
    }, $invoices);
    
    apiSuccess([
        'invoices' => $formatted,
        'pagination' => apiPaginationMeta($page, $perPage, $total),
    ]);
    
} catch (Exception $e) {
    error_log("[API Sales List] Error: " . $e->getMessage());
    apiError('LIST_ERROR', 'حدث خطأ أثناء جلب قائمة المبيعات', 500);
}
