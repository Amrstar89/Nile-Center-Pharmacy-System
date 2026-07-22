<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  POST /api/tenant/sales/create                                              ║
 * ║                                                                             ║
 *  Create a new sales invoice via API                                           ║
 *  Body: {                                                                      ║
 *    "customer_id": 1, "branch_id": 1, "store_id": 1,                          ║
 *    "payment_method": "cash|credit|installment",                                ║
 *    "paid_amount": 100, "discount": 0, "tax": 0, "notes": "",                 ║
 *    "items": [                                                                  ║
 *      { "product_id": 1, "quantity": 2, "price": 10.5, "discount": 0, "batch_id": null }
 *    ]                                                                           ║
 *  }                                                                             ║
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
apiRequire($params, ['customer_id', 'branch_id', 'store_id', 'items']);

$customerId = (int) $params['customer_id'];
$branchId = (int) $params['branch_id'];
$storeId = (int) $params['store_id'];
$paymentMethod = in_array($params['payment_method'] ?? '', ['cash', 'credit', 'installment']) 
    ? $params['payment_method'] : 'cash';
$paidAmount = (float) ($params['paid_amount'] ?? 0);
$discount = (float) ($params['discount'] ?? 0);
$tax = (float) ($params['tax'] ?? 0);
$notes = apiSanitize($params['notes'] ?? '');
$items = $params['items'];

if (empty($items) || !is_array($items)) {
    apiError('INVALID_ITEMS', 'Items array is required and cannot be empty', 400);
}

try {
    $db = getTenantDB($tenant);
    $db->beginTransaction();
    
    // ─── Validate Customer ───────────────────────────────────────────────
    $stmt = $db->prepare("SELECT id, customer_name, max_credit_limit, max_credit_days FROM customers WHERE id = ? AND is_active = 1");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        $db->rollBack();
        apiError('CUSTOMER_NOT_FOUND', 'العميل غير موجود أو غير نشط', 404);
    }
    
    // ─── Validate Branch & Store ─────────────────────────────────────────
    $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND is_active = 1");
    $stmt->execute([$branchId]);
    if (!$stmt->fetch()) {
        $db->rollBack();
        apiError('BRANCH_NOT_FOUND', 'الفرع غير موجود', 404);
    }
    
    $stmt = $db->prepare("SELECT id FROM stores WHERE id = ? AND is_active = 1");
    $stmt->execute([$storeId]);
    if (!$stmt->fetch()) {
        $db->rollBack();
        apiError('STORE_NOT_FOUND', 'المخزن غير موجود', 404);
    }
    
    // ─── Process Items & Calculate Totals ────────────────────────────────
    $subtotal = 0;
    $processedItems = [];
    
    foreach ($items as $index => $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = (float) ($item['quantity'] ?? 0);
        $price = (float) ($item['price'] ?? 0);
        $itemDiscount = (float) ($item['discount'] ?? 0);
        $batchId = !empty($item['batch_id']) ? (int) $item['batch_id'] : null;
        
        if ($productId <= 0 || $quantity <= 0 || $price < 0) {
            $db->rollBack();
            apiError('INVALID_ITEM', "Item #{$index}: product_id, quantity, and price are required", 400);
        }
        
        // Get product info
        $stmt = $db->prepare("
            SELECT id, product_name, product_code, barcode, sale_price, min_price, 
                   avg_purchase_price, is_active, is_suspended 
            FROM products WHERE id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product || !$product['is_active']) {
            $db->rollBack();
            apiError('PRODUCT_NOT_FOUND', "Item #{$index}: المنتج #{$productId} غير موجود", 404, ['item_index' => $index]);
        }
        
        // Check stock
        $stmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) as stock FROM inventory_items WHERE product_id = ? AND store_id = ?");
        $stmt->execute([$productId, $storeId]);
        $stock = (float) $stmt->fetchColumn();
        
        if ($stock < $quantity) {
            $db->rollBack();
            apiError('INSUFFICIENT_STOCK', 
                "Item #{$index}: الكمية غير متوفرة للمنتج {$product['product_name']} (المتوفر: {$stock})", 
                400, 
                ['item_index' => $index, 'product_id' => $productId, 'available' => $stock, 'requested' => $quantity]
            );
        }
        
        // Use product price if not provided
        if ($price <= 0) {
            $price = (float) $product['sale_price'];
        }
        
        $itemTotal = $price * $quantity;
        $itemTotalAfterDiscount = $itemTotal - $itemDiscount;
        $costTotal = (float) $product['avg_purchase_price'] * $quantity;
        
        $processedItems[] = [
            'product_id' => $productId,
            'product_name' => $product['product_name'],
            'product_code' => $product['product_code'],
            'quantity' => $quantity,
            'unit_price' => $price,
            'discount' => $itemDiscount,
            'total' => $itemTotalAfterDiscount,
            'cost' => $costTotal,
            'profit' => $itemTotalAfterDiscount - $costTotal,
            'batch_id' => $batchId,
        ];
        
        $subtotal += $itemTotalAfterDiscount;
    }
    
    // Calculate final totals
    $discountTotal = $discount;
    $taxTotal = $tax;
    $finalTotal = $subtotal - $discountTotal + $taxTotal;
    $totalProfit = array_sum(array_column($processedItems, 'profit'));
    
    // ─── Generate Invoice Number ──────────────────────────────────────────
    $year = date('Y');
    $stmt = $db->query("SELECT COUNT(*) FROM sale_invoices WHERE YEAR(created_at) = {$year} FOR UPDATE");
    $count = (int) $stmt->fetchColumn() + 1;
    $invoiceNumber = 'SI-' . $year . str_pad($count, 6, '0', STR_PAD_LEFT);
    
    // ─── Insert Invoice ────────────────────────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO sale_invoices 
        (invoice_number, customer_id, branch_id, store_id, user_id,
         payment_method, subtotal, discount, tax, final_total,
         paid_amount, remaining_amount, total_profit,
         notes, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW())
    ");
    $stmt->execute([
        $invoiceNumber, $customerId, $branchId, $storeId, $userId,
        $paymentMethod, $subtotal, $discountTotal, $taxTotal, $finalTotal,
        $paidAmount, $finalTotal - $paidAmount, $totalProfit,
        $notes
    ]);
    $invoiceId = (int) $db->lastInsertId();
    
    // ─── Insert Invoice Items ──────────────────────────────────────────────
    foreach ($processedItems as $item) {
        $stmt = $db->prepare("
            INSERT INTO sale_invoice_items
            (invoice_id, product_id, product_name, product_code, quantity,
             unit_price, discount, total, cost, profit, batch_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $invoiceId, $item['product_id'], $item['product_name'], $item['product_code'],
            $item['quantity'], $item['unit_price'], $item['discount'],
            $item['total'], $item['cost'], $item['profit'], $item['batch_id']
        ]);
        
        // ─── Deduct Inventory ──────────────────────────────────────────────
        // Deduct from inventory (FIFO - oldest batch first)
        $remainingQty = $item['quantity'];
        
        $stmt = $db->prepare("
            SELECT id, quantity FROM inventory_items 
            WHERE product_id = ? AND store_id = ? AND quantity > 0
            ORDER BY created_at ASC
        ");
        $stmt->execute([$item['product_id'], $storeId]);
        $batches = $stmt->fetchAll();
        
        foreach ($batches as $batch) {
            if ($remainingQty <= 0) break;
            $deduct = min($remainingQty, (float) $batch['quantity']);
            
            $stmt = $db->prepare("UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$deduct, $batch['id']]);
            
            // Log inventory movement
            $stmt = $db->prepare("
                INSERT INTO inventory_transactions 
                (product_id, store_id, branch_id, type, quantity, 
                 reference_type, reference_id, notes, created_by, created_at)
                VALUES (?, ?, ?, 'sale', ?, 'sale_invoice', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $item['product_id'], $storeId, $branchId, 
                -$deduct, $invoiceId, 'Sale #' . $invoiceNumber, $userId
            ]);
            
            $remainingQty -= $deduct;
        }
    }
    
    // ─── Update Customer Balance ───────────────────────────────────────────
    if ($finalTotal > 0) {
        // Check if customer_balances table exists, if not create it
        $db->exec("
            CREATE TABLE IF NOT EXISTS customer_balances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL UNIQUE,
                balance DECIMAL(15,4) DEFAULT 0,
                total_debit DECIMAL(15,4) DEFAULT 0,
                total_credit DECIMAL(15,4) DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (customer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Update or insert balance
        $db->prepare("
            INSERT INTO customer_balances (customer_id, balance, total_debit)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                balance = balance + VALUES(balance),
                total_debit = total_debit + VALUES(total_debit)
        ")->execute([$customerId, $finalTotal, $finalTotal]);
        
        // Add transaction record
        $db->exec("
            CREATE TABLE IF NOT EXISTS customer_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                type VARCHAR(20) NOT NULL COMMENT 'debit|credit|payment|return',
                amount DECIMAL(15,4) NOT NULL,
                balance_after DECIMAL(15,4) NOT NULL,
                reference_type VARCHAR(50),
                reference_id INT,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (customer_id),
                INDEX (reference_type, reference_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $stmt = $db->prepare("
            SELECT balance FROM customer_balances WHERE customer_id = ?
        ");
        $stmt->execute([$customerId]);
        $currentBalance = (float) $stmt->fetchColumn();
        
        $stmt = $db->prepare("
            INSERT INTO customer_transactions 
            (customer_id, type, amount, balance_after, reference_type, reference_id, notes, created_by)
            VALUES (?, 'debit', ?, ?, 'sale_invoice', ?, ?, ?)
        ");
        $stmt->execute([$customerId, $finalTotal, $currentBalance, $invoiceId, $notes, $userId]);
    }
    
    // ─── Log Activity ──────────────────────────────────────────────────────
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'api';
        $db->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, table_name, record_id, new_value, ip_address)
            VALUES (?, ?, 'api_sale_create', 'sale_invoices', ?, ?, ?)
        ")->execute([$userId, $userName, $invoiceId, 
            json_encode(['invoice_number' => $invoiceNumber, 'total' => $finalTotal, 'customer_id' => $customerId]),
            $ip
        ]);
    } catch (Exception $e) {
        // Non-critical
    }
    
    $db->commit();
    
    // ─── Response ──────────────────────────────────────────────────────────
    apiSuccess([
        'invoice' => [
            'id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'store_id' => $storeId,
            'payment_method' => $paymentMethod,
            'subtotal' => $subtotal,
            'discount' => $discountTotal,
            'tax' => $taxTotal,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'remaining' => $finalTotal - $paidAmount,
            'total_profit' => $totalProfit,
            'notes' => $notes,
            'status' => 'confirmed',
            'items_count' => count($processedItems),
            'created_at' => date('Y-m-d H:i:s'),
        ],
        'items' => $processedItems,
    ], 'Invoice created successfully', 201);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("[API Sales Create] Error: " . $e->getMessage());
    apiError('INVOICE_ERROR', 'حدث خطأ أثناء إنشاء الفاتورة: ' . $e->getMessage(), 500);
}
