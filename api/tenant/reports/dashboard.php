<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/reports/dashboard - MATCHES REAL DATABASE SCHEMA         ║
 * ║  sale_invoices: grand_total, profit_amount, cost_amount                   ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

$userPayload = apiAuth();
$tenant = requireTenant();
$params = apiParams();

$storeId = !empty($params['store_id']) ? (int) $params['store_id'] : null;
$userId = !empty($params['user_id']) ? (int) $params['user_id'] : null;
$fromDate = apiSanitize($params['from_date'] ?? date('Y-m-01'));
$toDate = apiSanitize($params['to_date'] ?? date('Y-m-d'));

try {
    $db = getTenantDB($tenant);

    $filters = ["si.invoice_date BETWEEN ? AND ?"];
    $bindParams = [$fromDate, $toDate];

    if ($storeId) { $filters[] = "si.store_id = ?"; $bindParams[] = $storeId; }
    if ($userId) { $filters[] = "si.user_id = ?"; $bindParams[] = $userId; }

    $whereSql = implode(' AND ', $filters);

    // KPIs
    $stmt = $db->prepare("SELECT COALESCE(SUM(si.grand_total), 0) as total, COUNT(*) as count, COALESCE(SUM(si.profit_amount), 0) as profit FROM sale_invoices si WHERE {$whereSql}");
    $stmt->execute($bindParams);
    $sales = $stmt->fetch();

    $todayWhere = str_replace("si.invoice_date BETWEEN ? AND ?", "si.invoice_date = CURDATE()", $whereSql);
    $todayBind = array_slice($bindParams, 2);
    $stmt = $db->prepare("SELECT COALESCE(SUM(grand_total), 0) as total, COUNT(*) as count FROM sale_invoices si WHERE {$todayWhere}");
    $stmt->execute($todayBind);
    $todaySales = $stmt->fetch();

    $stmt = $db->prepare("SELECT COALESCE(SUM(remaining_amount), 0) as total, COUNT(*) as count FROM sale_invoices si WHERE remaining_amount > 0 AND {$whereSql}");
    $stmt->execute($bindParams);
    $unpaid = $stmt->fetch();

    $customersCount = (int) $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();
    $productsCount = (int) $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

    // Low stock
    $lowStockCount = 0;
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM (SELECT p.id FROM products p LEFT JOIN inventory_items ii ON p.id = ii.product_id WHERE p.is_active = 1 AND p.reorder_point > 0 GROUP BY p.id HAVING COALESCE(SUM(ii.quantity), 0) <= p.reorder_point) as low");
        $lowStockCount = (int) $stmt->fetchColumn();
    } catch (Exception $e) {}

    // Daily Trend
    $stmt = $db->prepare("SELECT si.invoice_date as date, COALESCE(SUM(si.grand_total), 0) as total_sales, COALESCE(SUM(si.profit_amount), 0) as total_profit, COUNT(*) as invoice_count FROM sale_invoices si WHERE {$whereSql} GROUP BY si.invoice_date ORDER BY si.invoice_date ASC");
    $stmt->execute($bindParams);
    $dailyTrend = $stmt->fetchAll();

    // Top Products
    $stmt = $db->prepare("SELECT sii.product_id, sii.product_name, sii.product_code, SUM(sii.quantity) as total_qty, SUM(sii.line_total) as total_revenue, SUM(sii.profit_val) as total_profit FROM sale_invoice_items sii JOIN sale_invoices si ON sii.invoice_id = si.id WHERE {$whereSql} GROUP BY sii.product_id ORDER BY total_qty DESC LIMIT 10");
    $stmt->execute($bindParams);
    $topProducts = $stmt->fetchAll();

    // Top Customers
    $stmt = $db->prepare("SELECT si.customer_id, c.customer_name, COUNT(*) as purchase_count, SUM(si.grand_total) as total_amount FROM sale_invoices si LEFT JOIN customers c ON si.customer_id = c.id WHERE {$whereSql} AND si.customer_id IS NOT NULL GROUP BY si.customer_id ORDER BY total_amount DESC LIMIT 10");
    $stmt->execute($bindParams);
    $topCustomers = $stmt->fetchAll();

    // Payment Methods
    $stmt = $db->prepare("SELECT si.payment_method, COUNT(*) as count, COALESCE(SUM(si.grand_total), 0) as total FROM sale_invoices si WHERE {$whereSql} GROUP BY si.payment_method");
    $stmt->execute($bindParams);
    $paymentMethods = $stmt->fetchAll();

    // Inventory Value
    try {
        $stmt = $db->query("SELECT COALESCE(SUM(ii.quantity * ii.unit_cost), 0) as cost_value, COALESCE(SUM(ii.quantity * ii.sell_price), 0) as retail_value FROM inventory_items ii JOIN products p ON ii.product_id = p.id WHERE p.is_active = 1 AND ii.is_active = 1");
        $inventoryValue = $stmt->fetch();
    } catch (Exception $e) {
        $inventoryValue = ['cost_value' => 0, 'retail_value' => 0];
    }

    apiSuccess([
        'period' => ['from' => $fromDate, 'to' => $toDate],
        'kpis' => [
            'total_sales' => ['amount' => (float) $sales['total'], 'count' => (int) $sales['count'], 'profit' => (float) $sales['profit']],
            'today_sales' => ['amount' => (float) $todaySales['total'], 'count' => (int) $todaySales['count']],
            'unpaid_amount' => ['amount' => (float) $unpaid['total'], 'count' => (int) $unpaid['count']],
            'customers' => $customersCount, 'products' => $productsCount, 'low_stock_items' => $lowStockCount,
            'inventory_value' => [
                'cost' => (float) $inventoryValue['cost_value'],
                'retail' => (float) $inventoryValue['retail_value'],
                'potential_profit' => (float) $inventoryValue['retail_value'] - (float) $inventoryValue['cost_value'],
            ],
        ],
        'daily_trend' => array_map(fn($d) => ['date' => $d['date'], 'sales' => (float) $d['total_sales'], 'profit' => (float) $d['total_profit'], 'invoices' => (int) $d['invoice_count']], $dailyTrend),
        'top_products' => array_map(fn($p) => ['id' => (int) $p['product_id'], 'name' => $p['product_name'], 'code' => $p['product_code'], 'quantity_sold' => (float) $p['total_qty'], 'revenue' => (float) $p['total_revenue'], 'profit' => (float) $p['total_profit']], $topProducts),
        'top_customers' => array_map(fn($c) => ['id' => (int) $c['customer_id'], 'name' => $c['customer_name'], 'purchases' => (int) $c['purchase_count'], 'amount' => (float) $c['total_amount']], $topCustomers),
        'payment_methods' => array_map(fn($pm) => ['method' => $pm['payment_method'], 'count' => (int) $pm['count'], 'total' => (float) $pm['total']], $paymentMethods),
    ]);

} catch (Exception $e) {
    error_log("[API Dashboard] Error: " . $e->getMessage());
    apiError('DASHBOARD_ERROR', 'حدث خطأ أثناء جلب بيانات لوحة التحكم', 500);
}
