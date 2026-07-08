-- ============================================
-- موديول المبيعات - Sales Module
-- تاريخ: 8 يوليو 2026
-- ============================================

-- 1. فواتير البيع
CREATE TABLE IF NOT EXISTS sale_invoices (
    id INT(11) NOT NULL AUTO_INCREMENT,
    invoice_number VARCHAR(50) NOT NULL,
    customer_id INT(11) DEFAULT NULL,
    store_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL COMMENT 'البائع',
    invoice_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    payment_method ENUM('cash','visa','credit','pending','delivery') NOT NULL DEFAULT 'cash',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_pct DECIMAL(5,2) DEFAULT 0.00,
    discount_val DECIMAL(12,2) DEFAULT 0.00,
    extra_discount_pct DECIMAL(5,2) DEFAULT 0.00,
    extra_discount_val DECIMAL(12,2) DEFAULT 0.00,
    vat_amount DECIMAL(12,2) DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) DEFAULT 0.00,
    remaining_amount DECIMAL(12,2) DEFAULT 0.00,
    profit_amount DECIMAL(12,2) DEFAULT 0.00,
    cost_amount DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('open','paid','partial','cancelled') NOT NULL DEFAULT 'open',
    notes TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_invoice_number (invoice_number),
    KEY idx_customer (customer_id),
    KEY idx_store (store_id),
    KEY idx_user (user_id),
    KEY idx_date (invoice_date),
    KEY idx_status (status),
    KEY idx_payment (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. أصناف فاتورة البيع
CREATE TABLE IF NOT EXISTS sale_invoice_items (
    id INT(11) NOT NULL AUTO_INCREMENT,
    invoice_id INT(11) NOT NULL,
    product_id INT(11) DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_code VARCHAR(50) DEFAULT NULL,
    barcode VARCHAR(50) DEFAULT NULL,
    unit_name VARCHAR(50) DEFAULT 'علبة',
    quantity DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    unit_cost DECIMAL(12,2) DEFAULT 0.00,
    sell_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_pct DECIMAL(5,2) DEFAULT 0.00,
    discount_val DECIMAL(12,2) DEFAULT 0.00,
    vat_pct DECIMAL(5,2) DEFAULT 0.00,
    vat_val DECIMAL(12,2) DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    profit_val DECIMAL(12,2) DEFAULT 0.00,
    expiry_date DATE DEFAULT NULL,
    batch_number VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_invoice (invoice_id),
    KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. مرتجعات المبيعات
CREATE TABLE IF NOT EXISTS sale_returns (
    id INT(11) NOT NULL AUTO_INCREMENT,
    return_number VARCHAR(50) NOT NULL,
    invoice_id INT(11) NOT NULL,
    customer_id INT(11) DEFAULT NULL,
    store_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    return_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_return_number (return_number),
    KEY idx_invoice (invoice_id),
    KEY idx_customer (customer_id),
    KEY idx_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. أصناف مرتجع المبيعات
CREATE TABLE IF NOT EXISTS sale_return_items (
    id INT(11) NOT NULL AUTO_INCREMENT,
    return_id INT(11) NOT NULL,
    invoice_item_id INT(11) NOT NULL,
    product_id INT(11) DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_code VARCHAR(50) DEFAULT NULL,
    barcode VARCHAR(50) DEFAULT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    unit_cost DECIMAL(12,2) DEFAULT 0.00,
    sell_price DECIMAL(12,2) DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reason TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_return (return_id),
    KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. دفعات العملاء
CREATE TABLE IF NOT EXISTS customer_payments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    payment_number VARCHAR(50) NOT NULL,
    customer_id INT(11) NOT NULL,
    invoice_id INT(11) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
    notes TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_payment_number (payment_number),
    KEY idx_customer (customer_id),
    KEY idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. حركات حساب العملاء
CREATE TABLE IF NOT EXISTS customer_transactions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    customer_id INT(11) NOT NULL,
    transaction_type ENUM('sale','sale_return','payment','opening_balance') NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL COMMENT 'sale_invoice, sale_return, customer_payment',
    reference_id INT(11) DEFAULT NULL,
    reference_number VARCHAR(50) DEFAULT NULL,
    debit DECIMAL(12,2) DEFAULT 0.00 COMMENT 'مدين = عليه (مبيعات)',
    credit DECIMAL(12,2) DEFAULT 0.00 COMMENT 'دائن = ليه (دفعات/مرتجعات)',
    balance_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer (customer_id),
    KEY idx_type (transaction_type),
    KEY idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ملاحظات:
-- ============================================
-- 1. sale_invoices: كل فاتورة بيع مرتبطة بعميل (customer) ومخزن (store) وبائع (user)
-- 2. payment_method: cash=كاش, visa=فيزا, credit=آجل, pending=معلقة, delivery=توصيل منزلي
-- 3. sale_invoice_items: كل صنف في الفاتورة مع سعر البيع والخصم والربح
-- 4. sale_returns: مرتجع مرتبط بفاتورة بيع أصلية
-- 5. customer_transactions: 
--    - debit (مدين) = بيع (العميل عليه)
--    - credit (دائن) = دفعة أو مرتجع (العميل ليه)
-- 6. inventory_movements بيسجل حركة type='sale' لكل بيع و type='sale_return' لكل مرتجع
-- ============================================
