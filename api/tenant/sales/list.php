<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/sales/list - MATCHES REAL DATABASE SCHEMA                ║
 * ║  sale_invoices: grand_total, profit_amount, discount_pct/val, vat_amount  ║
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
$storeId = !empty($params['store_id']) ? (int) $params['store_id'] : null;
$userId = !empty($params['user_id']) ? (int) $params['user_id'] : null;
$fromDate = apiSanitize($params['from_date'] ?? '');
$toDate = apiSanitize($params['to_date'] ?? '');
$status = apiSanitize($params['status'] ?? '');
$payment = apiSanitize($params['payment_method'] ?? '');
$search = apiSanitize($params['q'] ?? '');
[$page, $perPage, $offset] = apiPagination();

try {
    $db = getTenantDB($tenant);

    $where = ["1=1"];
    $bindParams = [];

    if ($customerId) { $where[] = "si.customer_id = ?"; $bindParams[] = $customerId; }
    if ($storeId) { $where[] = "si.store_id = ?"; $bindParams[] = $storeId; }
    if ($userId) { $where[] = "si.user_id = ?"; $bindParams[] = $userId; }
    if ($fromDate) { $where[] = "si.invoice_date >= ?"; $bindParams[] = $fromDate; }
    if ($toDate) { $where[] = "si.invoice_date <= ?"; $bindParams[] = $toDate; }
    if ($status) { $where[] = "si.status = ?"; $bindParams[] = $status; }
    if ($payment) { $where[] = "si.payment_method = ?"; $bindParams[] = $payment; }
    if ($search) {
        $where[] = "(si.invoice_number LIKE ? OR c.customer_name LIKE ?)";
        $like = "%{$search}%"; array_push($bindParams, $like, $like);
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "SELECT COUNT(*) FROM sale_invoices si LEFT JOIN customers c ON si.customer_id = c.id WHERE {$whereSql}";
    $stmt = $db->prepare($countSql);
    $stmt->execute($bindParams);
    $total = (int) $stmt->fetchColumn();

    $sql = "SELECT 
        si.id, si.invoice_number, si.customer_id, c.customer_name,
        si.store_id, s.store_name, si.user_id, u.full_name as user_name,
        si.invoice_date, si.invoice_time, si.payment_method,
        si.subtotal, si.discount_pct, si.discount_val, si.extra_discount_pct, si.extra_discount_val,
        si.vat_amount, si.grand_total, si.paid_amount, si.remaining_amount,
        si.profit_amount, si.cost_amount, si.status, si.notes, si.created_at,
        (SELECT COUNT(*) FROM sale_invoice_items sii WHERE sii.invoice_id = si.id) as items_count
    FROM sale_invoices si
    LEFT JOIN customers c ON si.customer_id = c.id
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
            'store' => ['id' => (int) $inv['store_id'], 'name' => $inv['store_name']],
            'user' => ['id' => (int) $inv['user_id'], 'name' => $inv['user_name']],
            'invoice_date' => $inv['invoice_date'],
            'invoice_time' => $inv['invoice_time'],
            'payment_method' => $inv['payment_method'],
            'financial' => [
                'subtotal' => (float) $inv['subtotal'],
                'discount_pct' => (float) $inv['discount_pct'],
                'discount_val' => (float) $inv['discount_val'],
                'extra_discount_pct' => (float) $inv['extra_discount_pct'],
                'extra_discount_val' => (float) $inv['extra_discount_val'],
                'vat_amount' => (float) $inv['vat_amount'],
                'grand_total' => (float) $inv['grand_total'],
                'paid' => (float) $inv['paid_amount'],
                'remaining' => (float) $inv['remaining_amount'],
                'profit' => (float) $inv['profit_amount'],
                'cost' => (float) $inv['cost_amount'],
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
