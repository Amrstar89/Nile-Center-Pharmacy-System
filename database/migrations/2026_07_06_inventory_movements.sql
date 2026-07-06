-- ============================================
-- ربط موديول المشتريات بالمخازن والموردين
-- تاريخ: 6 يوليو 2026
-- ============================================

-- 1. إنشاء جدول حركات المخزون (لو مش موجود)
CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT(11) NOT NULL AUTO_INCREMENT,
    store_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    movement_type ENUM('purchase','purchase_return','sale','sale_return','transfer_in','transfer_out','adjustment','opening_balance') NOT NULL,
    reference_type VARCHAR(50) DEFAULT NULL COMMENT 'purchase_invoice, purchase_return, sale_invoice, inventory_transfer, adjustment',
    reference_id INT(11) DEFAULT NULL,
    reference_number VARCHAR(50) DEFAULT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    unit_cost DECIMAL(12,2) DEFAULT 0.00,
    total_cost DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_store_product (store_id, product_id),
    INDEX idx_movement_type (movement_type),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. إضافة عمود reference_type و reference_id لـ purchase_returns (لو مش موجودين)
ALTER TABLE purchase_returns 
    ADD COLUMN IF NOT EXISTS reference_type VARCHAR(50) DEFAULT 'purchase_return' AFTER notes,
    ADD COLUMN IF NOT EXISTS reference_id INT(11) DEFAULT NULL AFTER reference_type;

-- 3. إضافة عمود source_id لـ inventory_items (لتتبع مصدر الصنف)
ALTER TABLE inventory_items 
    ADD COLUMN IF NOT EXISTS source_type VARCHAR(50) DEFAULT NULL COMMENT 'purchase_invoice, opening_balance, transfer' AFTER batch_id,
    ADD COLUMN IF NOT EXISTS source_id INT(11) DEFAULT NULL AFTER source_type;

-- ============================================
-- ملاحظات التنفيذ:
-- ============================================
-- 1. جدول inventory_movements بيسجل كل حركة:
--    - purchase: فاتورة شراء (زيادة)
--    - purchase_return: مرتجع مشتريات (نقصان)
--    - sale: فاتورة بيع (نقصان)
--    - sale_return: مرتجع مبيعات (زيادة)
--    - transfer_in: تحويل وارد (زيادة)
--    - transfer_out: تحويل صادر (نقصان)
--    - adjustment: تسوية
--
-- 2. فواتير الشراء هتسجل حركة type='purchase' لكل صنف
-- 3. مرتجعات المشتريات هتسجل حركة type='purchase_return' لكل صنف
-- 4. كل حركة مربوطة بمصدرها (فاتورة، مرتجع، تحويل)
-- ============================================