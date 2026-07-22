<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/sales/create - MATCHES REAL DATABASE SCHEMA              ║
 * ║  sale_invoices: grand_total, profit_amount, cost_amount, discount_pct/val  ║
 * ║  sale_invoice_items: sell_price, line_total, profit_val, discount_pct/val  ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/../../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Only POST method is allowed', 405);
}

$userPayload = apiAuth();
$userId = $userPayload['sub'] ?? null;
$userName = $userPayload['name'] ?? 'API User';
$tenant = requireTenant();

$params = apiInput();
apiRequire($params, ['store_id', 'items']);

$storeId      = (int) $params['store_id'];
$customerId   = !empty($params['customer_id']) ? (int) $params['customer_id'] : null;
$invoiceDate  = apiSanitize($params['invoice_date'] ?? date('Y-m-d'));
$invoiceTime  = apiSanitize($params['invoice_time'] ?? date('H:i:s'));
$paymentMethod = in_array($params['payment_method'] ?? '', ['cash','visa','credit','pending','delivery']) ? $params['payment_method'] : 'cash';
$paidAmount   = (float) ($params['paid_amount'] ?? 0);
$discountPct  = (float) ($params['discount_pct'] ?? 0);
$discountVal  = (float) ($params['discount_val'] ?? 0);
$extraDiscPct = (float) ($params['extra_discount_pct'] ?? 0);
$extraDiscVal = (float) ($params['extra_discount_val'] ?? 0);
$vatAmount    = (float) ($params['vat_amount'] ?? 0);
$notes        = apiSanitize($params['notes'] ?? '');
$items        = $params['items'];

if (empty($items) || !is_array($items)) {
    apiError('INVALID_ITEMS', 'Items array is required and cannot be empty', 400);
}

try {
    $db = getTenantDB($tenant);
    $db->beginTransaction();

    // Validate Store
    $stmt = $db->prepare("SELECT id FROM stores WHERE id = ? LIMIT 1");
    $stmt->execute([$storeId]);
    if (!$stmt->fetch()) {
        $db->rollBack();
        apiError('STORE_NOT_FOUND', 'المخزن غير موجود', 404);
    }

    // Validate Customer (if provided)
    if ($customerId) {
        $stmt = $db->prepare("SELECT id, customer_name, credit_limit, balance FROM customers WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$customerId]);
        if (!$stmt->fetch()) {
            $db->rollBack();
            apiError('CUSTOMER_NOT_FOUND', 'العميل غير موجود أو غير نشط', 404);
        }
    }

    // Process Items
    $subtotal = 0; $totalCost = 0; $totalProfit = 0;
    $processedItems = [];

    foreach ($items as $index => $item) {
        $productId    = (int) ($item['product_id'] ?? 0);
        $quantity     = (float) ($item['quantity'] ?? 0);
        $sellPrice    = (float) ($item['sell_price'] ?? $item['price'] ?? 0);
        $itemDiscPct  = (float) ($item['discount_pct'] ?? 0);
        $itemDiscVal  = (float) ($item['discount_val'] ?? 0);
        $itemVatPct   = (float) ($item['vat_pct'] ?? 0);
        $itemVatVal   = (float) ($item['vat_val'] ?? 0);
        $batchId      = !empty($item['batch_id']) ? (int) $item['batch_id'] : null;

        if ($productId <= 0 || $quantity <= 0) {
            $db->rollBack();
            apiError('INVALID_ITEM', "Item #{$index}: product_id و quantity مطلوبين", 400);
        }

        $stmt = $db->prepare("SELECT id, product_name, product_code, barcode, sell_price, cost_price, unit_name, is_active FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product || !$product['is_active']) {
            $db->rollBack();
            apiError('PRODUCT_NOT_FOUND', "Item #{$index}: المنتج #{$productId} غير موجود", 404);
        }

        if ($sellPrice <= 0) $sellPrice = (float) $product['sell_price'];

        $lineSubtotal = $sellPrice * $quantity;
        $lineDiscountVal = $itemDiscVal > 0 ? $itemDiscVal : ($lineSubtotal * $itemDiscPct / 100);
        $lineAfterDiscount = $lineSubtotal - $lineDiscountVal;
        $lineVatVal = $itemVatVal > 0 ? $itemVatVal : ($lineAfterDiscount * $itemVatPct / 100);
        $lineTotal = $lineAfterDiscount + $lineVatVal;

        $unitCost = (float) $product['cost_price'];
        $lineCost = $unitCost * $quantity;
        $lineProfit = $lineAfterDiscount - $lineCost;

        $processedItems[] = [
            'product_id'    => $productId,
            'product_name'  => $product['product_name'],
            'product_code'  => $product['product_code'],
            'barcode'       => $product['barcode'],
            'unit_name'     => $product['unit_name'] ?? 'علبة',
            'quantity'      => $quantity,
            'unit_cost'     => $unitCost,
            'sell_price'    => $sellPrice,
            'discount_pct'  => $itemDiscPct,
            'discount_val'  => $lineDiscountVal,
            'vat_pct'       => $itemVatPct,
            'vat_val'       => $lineVatVal,
            'line_total'    => $lineTotal,
            'profit_val'    => $lineProfit,
            'batch_id'      => $batchId,
            'batch_number'  => $item['batch_number'] ?? null,
            'expiry_date'   => $item['expiry_date'] ?? null,
        ];

        $subtotal += $lineSubtotal;
        $totalCost += $lineCost;
        $totalProfit += $lineProfit;
    }

    // Invoice-level calculations
    $discVal = $discountVal > 0 ? $discountVal : ($subtotal * $discountPct / 100);
    $extraDiscValCalc = $extraDiscVal > 0 ? $extraDiscVal : (($subtotal - $discVal) * $extraDiscPct / 100);
    $afterDiscounts = $subtotal - $discVal - $extraDiscValCalc;
    $grandTotal = $afterDiscounts + $vatAmount;

    $status = 'open';
    if ($paidAmount >= $grandTotal) $status = 'paid';
    elseif ($paidAmount > 0) $status = 'partial';
    $remaining = $grandTotal - $paidAmount;

    // Generate Invoice Number
    $year = date('Y');
    $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(invoice_number, 13) AS UNSIGNED)) as max_num FROM sale_invoices WHERE invoice_number LIKE 'SINV-{$year}-%' FOR UPDATE");
    $maxNum = (int) $stmt->fetchColumn();
    $invoiceNumber = 'SINV-' . $year . '-' . str_pad($maxNum + 1, 5, '0', STR_PAD_LEFT);

    // Insert Invoice
    $stmt = $db->prepare("INSERT INTO sale_invoices (invoice_number, customer_id, store_id, user_id, invoice_date, invoice_time, payment_method, subtotal, discount_pct, discount_val, extra_discount_pct, extra_discount_val, vat_amount, grand_total, paid_amount, remaining_amount, profit_amount, cost_amount, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$invoiceNumber, $customerId, $storeId, $userId, $invoiceDate, $invoiceTime, $paymentMethod, $subtotal, $discountPct, $discVal, $extraDiscPct, $extraDiscValCalc, $vatAmount, $grandTotal, $paidAmount, max(0, $remaining), $totalProfit, $totalCost, $status, $notes, $userId]);
    $invoiceId = (int) $db->lastInsertId();

    // Insert Items + Deduct Inventory
    foreach ($processedItems as $item) {
        $stmt = $db->prepare("INSERT INTO sale_invoice_items (invoice_id, product_id, product_name, product_code, barcode, unit_name, quantity, unit_cost, sell_price, discount_pct, discount_val, vat_pct, vat_val, line_total, profit_val, expiry_date, batch_number, batch_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoiceId, $item['product_id'], $item['product_name'], $item['product_code'], $item['barcode'], $item['unit_name'], $item['quantity'], $item['unit_cost'], $item['sell_price'], $item['discount_pct'], $item['discount_val'], $item['vat_pct'], $item['vat_val'], $item['line_total'], $item['profit_val'], $item['expiry_date'], $item['batch_number'], $item['batch_id']]);

        // Deduct inventory (FIFO)
        $remainingQty = $item['quantity'];
        $fifoStmt = $db->prepare("SELECT ii.id, ii.quantity, ii.batch_id FROM inventory_items ii WHERE ii.product_id = ? AND ii.store_id = ? AND ii.quantity > 0 ORDER BY ii.created_at ASC, ii.id ASC");
        $fifoStmt->execute([$item['product_id'], $storeId]);
        $batches = $fifoStmt->fetchAll();

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) break;
            $deduct = min($remainingQty, (float) $batch['quantity']);
            $db->prepare("UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?")->execute([$deduct, $batch['id']]);
            $db->prepare("INSERT INTO inventory_transactions (product_id, store_id, type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at) VALUES (?, ?, 'sale', ?, ?, 'sale_invoice', ?, ?, ?, NOW())")->execute([$item['product_id'], $storeId, -$deduct, $item['unit_cost'], $invoiceId, 'Sale #' . $invoiceNumber, $userId]);
            $remainingQty -= $deduct;
        }
    }

    // Update Customer Balance
    if ($customerId && $grandTotal > 0) {
        $db->prepare("UPDATE customers SET balance = COALESCE(balance, 0) + ? WHERE id = ?")->execute([max(0, $remaining), $customerId]);
        $db->prepare("INSERT INTO customer_transactions (customer_id, type, amount, reference_type, reference_id, notes, created_by, created_at) VALUES (?, 'debit', ?, 'sale_invoice', ?, ?, ?, NOW())")->execute([$customerId, $grandTotal, $invoiceId, $notes, $userId]);
    }

    // Log Activity
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'api';
        $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_value, ip_address, created_at) VALUES (?, 'api_sale_create', 'sale_invoices', ?, ?, ?, NOW())")->execute([$userId, $invoiceId, json_encode(['invoice_number' => $invoiceNumber, 'grand_total' => $grandTotal, 'customer_id' => $customerId]), $ip]);
    } catch (Exception $e) {}

    $db->commit();

    apiSuccess([
        'invoice' => [
            'id'              => $invoiceId,
            'invoice_number'  => $invoiceNumber,
            'customer_id'     => $customerId,
            'store_id'        => $storeId,
            'user_id'         => $userId,
            'invoice_date'    => $invoiceDate,
            'invoice_time'    => $invoiceTime,
            'payment_method'  => $paymentMethod,
            'subtotal'        => $subtotal,
            'discount_pct'    => $discountPct,
            'discount_val'    => $discVal,
            'extra_discount_pct' => $extraDiscPct,
            'extra_discount_val' => $extraDiscValCalc,
            'vat_amount'      => $vatAmount,
            'grand_total'     => $grandTotal,
            'paid_amount'     => $paidAmount,
            'remaining'       => max(0, $remaining),
            'profit_amount'   => $totalProfit,
            'cost_amount'     => $totalCost,
            'status'          => $status,
            'items_count'     => count($processedItems),
            'created_at'      => date('Y-m-d H:i:s'),
        ],
        'items' => array_map(fn($it) => [
            'product_id'   => $it['product_id'],
            'product_name' => $it['product_name'],
            'quantity'     => $it['quantity'],
            'sell_price'   => $it['sell_price'],
            'discount_pct' => $it['discount_pct'],
            'discount_val' => $it['discount_val'],
            'vat_pct'      => $it['vat_pct'],
            'vat_val'      => $it['vat_val'],
            'line_total'   => $it['line_total'],
            'profit_val'   => $it['profit_val'],
        ], $processedItems),
    ], 'Invoice created successfully', 201);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log("[API Sales Create] Error: " . $e->getMessage());
    apiError('INVOICE_ERROR', 'حدث خطأ أثناء إنشاء الفاتورة: ' . $e->getMessage(), 500);
}
