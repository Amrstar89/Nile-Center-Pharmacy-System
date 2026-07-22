<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  GET /api/tenant/reports/dashboard                                          ║
 * ║                                                                             ║
 *  Returns KPI data for dashboard charts and summary cards                      ║
 *  Query: ?branch_id=&store_id=&from_date=2026-07-01&to_date=2026-07-22       ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Only GET method is allowed', 405);
}

$userPayload = apiAuth();
$tenant = requireTenant();
$params = apiParams();

$branchId = !empty($params['branch_id']) ? (int) $params['branch_id'] : null;
$storeId = !empty($params['store_id']) ? (int) $params['store_id'] : null;
$fromDate = apiSanitize($params['from_date'] ?? date('Y-m-01'));
$toDate = apiSanitize($params['to_date'] ?? date('Y-m-d'));

try {
    $db = getTenantDB($tenant);
    
    // Build common filters
    $filters = [];
    $bindSales = [$fromDate, $toDate];
    $bindInventory = [];
    
    $salesWhere = "DATE(si.created_at) BETWEEN ? AND ?";
    if ($branchId) {
        $salesWhere .= " AND si.branch_id = ?";
        $bindSales[] = $branchId;
    }
    if ($storeId) {
        $salesWhere .= " AND si.store_id = ?";
        $bindSales[] = $storeId;
    }
    
    $invWhere = "p.is_active = 1";
    if ($storeId) {
        $invWhere .= " AND ii.store_id = " . (int) $storeId;
    }
    
    // ─── KPI Cards ─────────────────────────────────────────────────────────
    
    // Total Sales
    $stmt = $db->prepare("SELECT COALESCE(SUM(final_total), 0) as total, COUNT(*) as count, 
                          COALESCE(SUM(total_profit), 0) as profit
                          FROM sale_invoices si WHERE {$salesWhere}");
    $stmt->execute($bindSales);
    $sales = $stmt->fetch();
    
    // Today's Sales
    $todayWhere = str_replace("DATE(si.created_at) BETWEEN ? AND ?", "DATE(si.created_at) = CURDATE()", $salesWhere);
    $todayBind = array_slice($bindSales, 2); // Remove date params
    $stmt = $db->prepare("SELECT COALESCE(SUM(final_total), 0) as total, COUNT(*) as count 
                          FROM sale_invoices si WHERE {$todayWhere}");
    $stmt->execute($todayBind);
    $todaySales = $stmt->fetch();
    
    // Unpaid/Remaining amounts
    $stmt = $db->prepare("SELECT COALESCE(SUM(remaining_amount), 0) as total, COUNT(*) as count 
                          FROM sale_invoices si WHERE remaining_amount > 0 AND {$salesWhere}");
    $stmt->execute($bindSales);
    $unpaid = $stmt->fetch();
    
    // Active Customers
    $stmt = $db->query("SELECT COUNT(*) FROM customers WHERE is_active = 1");
    $customersCount = (int) $stmt->fetchColumn();
    
    // Active Products
    $stmt = $db->query("SELECT COUNT(*) FROM products WHERE is_active = 1");
    $productsCount = (int) $stmt->fetchColumn();
    
    // Low Stock Count
    $lowStockWhere = $storeId ? "ii.store_id = " . (int) $storeId : "1=1";
    $stmt = $db->query("SELECT COUNT(DISTINCT p.id) FROM products p 
                        LEFT JOIN inventory_items ii ON p.id = ii.product_id 
                        WHERE p.is_active = 1 AND p.reorder_point > 0 
                        GROUP BY p.id 
                        HAVING COALESCE(SUM(ii.quantity), 0) <= p.reorder_point");
    $lowStockCount = count($stmt->fetchAll());
    
    // ─── Daily Sales Trend (for chart) ─────────────────────────────────────
    $stmt = $db->prepare("SELECT 
        DATE(si.created_at) as date,
        COALESCE(SUM(si.final_total), 0) as total_sales,
        COALESCE(SUM(si.total_profit), 0) as total_profit,
        COUNT(*) as invoice_count
    FROM sale_invoices si
    WHERE {$salesWhere}
    GROUP BY DATE(si.created_at)
    ORDER BY date ASC");
    $stmt->execute($bindSales);
    $dailyTrend = $stmt->fetchAll();
    
    // ─── Top Selling Products ──────────────────────────────────────────────
    $stmt = $db->prepare("SELECT 
        sii.product_id, sii.product_name, sii.product_code,
        SUM(sii.quantity) as total_qty,
        SUM(sii.total) as total_revenue,
        SUM(sii.profit) as total_profit
    FROM sale_invoice_items sii
    JOIN sale_invoices si ON sii.invoice_id = si.id
    WHERE {$salesWhere}
    GROUP BY sii.product_id
    ORDER BY total_qty DESC
    LIMIT 10");
    $stmt->execute($bindSales);
    $topProducts = $stmt->fetchAll();
    
    // ─── Top Customers ─────────────────────────────────────────────────────
    $stmt = $db->prepare("SELECT 
        si.customer_id, c.customer_name,
        COUNT(*) as purchase_count,
        SUM(si.final_total) as total_amount,
        SUM(si.total_profit) as total_profit
    FROM sale_invoices si
    LEFT JOIN customers c ON si.customer_id = c.id
    WHERE {$salesWhere} AND si.customer_id IS NOT NULL
    GROUP BY si.customer_id
    ORDER BY total_amount DESC
    LIMIT 10");
    $stmt->execute($bindSales);
    $topCustomers = $stmt->fetchAll();
    
    // ─── Payment Methods Breakdown ─────────────────────────────────────────
    $stmt = $db->prepare("SELECT 
        si.payment_method,
        COUNT(*) as count,
        COALESCE(SUM(si.final_total), 0) as total
    FROM sale_invoices si
    WHERE {$salesWhere}
    GROUP BY si.payment_method");
    $stmt->execute($bindSales);
    $paymentMethods = $stmt->fetchAll();
    
    // ─── Inventory Value ───────────────────────────────────────────────────
    $stmt = $db->query("SELECT 
        COALESCE(SUM(ii.quantity * p.avg_purchase_price), 0) as inventory_value,
        COALESCE(SUM(ii.quantity * p.sale_price), 0) as retail_value,
        COUNT(DISTINCT p.id) as stocked_products
    FROM products p
    LEFT JOIN inventory_items ii ON p.id = ii.product_id
    WHERE p.is_active = 1");
    $inventoryValue = $stmt->fetch();
    
    // ─── Response ──────────────────────────────────────────────────────────
    apiSuccess([
        'period' => ['from' => $fromDate, 'to' => $toDate],
        'kpis' => [
            'total_sales' => [
                'amount' => (float) $sales['total'],
                'count' => (int) $sales['count'],
                'profit' => (float) $sales['profit'],
            ],
            'today_sales' => [
                'amount' => (float) $todaySales['total'],
                'count' => (int) $todaySales['count'],
            ],
            'unpaid_amount' => [
                'amount' => (float) $unpaid['total'],
                'count' => (int) $unpaid['count'],
            ],
            'customers' => $customersCount,
            'products' => $productsCount,
            'low_stock_items' => $lowStockCount,
            'inventory_value' => [
                'cost' => (float) $inventoryValue['inventory_value'],
                'retail' => (float) $inventoryValue['retail_value'],
                'potential_profit' => (float) $inventoryValue['retail_value'] - (float) $inventoryValue['inventory_value'],
                'stocked_products' => (int) $inventoryValue['stocked_products'],
            ],
        ],
        'daily_trend' => array_map(fn($d) => [
            'date' => $d['date'],
            'sales' => (float) $d['total_sales'],
            'profit' => (float) $d['total_profit'],
            'invoices' => (int) $d['invoice_count'],
        ], $dailyTrend),
        'top_products' => array_map(fn($p) => [
            'id' => (int) $p['product_id'],
            'name' => $p['product_name'],
            'code' => $p['product_code'],
            'quantity_sold' => (float) $p['total_qty'],
            'revenue' => (float) $p['total_revenue'],
            'profit' => (float) $p['total_profit'],
        ], $topProducts),
        'top_customers' => array_map(fn($c) => [
            'id' => (int) $c['customer_id'],
            'name' => $c['customer_name'],
            'purchases' => (int) $c['purchase_count'],
            'amount' => (float) $c['total_amount'],
        ], $topCustomers),
        'payment_methods' => array_map(fn($pm) => [
            'method' => $pm['payment_method'],
            'count' => (int) $pm['count'],
            'total' => (float) $pm['total'],
        ], $paymentMethods),
    ]);
    
} catch (Exception $e) {
    error_log("[API Dashboard] Error: " . $e->getMessage());
    apiError('DASHBOARD_ERROR', 'حدث خطأ أثناء جلب بيانات لوحة التحكم', 500);
}
