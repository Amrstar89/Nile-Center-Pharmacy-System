-- ============================================
-- تعديلات جداول المشتريات - 6 يوليو 2026
-- ============================================
-- شغل الملف ده في phpMyAdmin (SQL tab) على الداتا بيز بتاعتك

-- 1. إضافة أعمدة خصم إضافي وطريقة الدفع لجدول فواتير الشراء
ALTER TABLE purchase_invoices
    ADD COLUMN IF NOT EXISTS extra_discount_pct DECIMAL(5,2) DEFAULT 0 AFTER paid_amount,
    ADD COLUMN IF NOT EXISTS extra_discount_val DECIMAL(12,2) DEFAULT 0 AFTER extra_discount_pct,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) DEFAULT 'credit' AFTER extra_discount_val;

-- 2. إضافة أعمدة قيمة الضريبة والبونص لجدول أصناف فواتير الشراء
ALTER TABLE purchase_invoice_items
    ADD COLUMN IF NOT EXISTS vat_value DECIMAL(12,2) DEFAULT 0 AFTER vat_percent,
    ADD COLUMN IF NOT EXISTS bonus_qty DECIMAL(10,3) DEFAULT 0 AFTER quantity;

-- 3. إضافة أعمدة قيمة الضريبة والبونص لجدول أصناف أوامر الشراء
ALTER TABLE purchase_order_items
    ADD COLUMN IF NOT EXISTS vat_value DECIMAL(12,2) DEFAULT 0 AFTER vat_percent,
    ADD COLUMN IF NOT EXISTS bonus_qty DECIMAL(10,3) DEFAULT 0 AFTER quantity;

-- 4. إضافة عمود payment_method لجدول مرتجعات الشراء (لو مش موجود)
ALTER TABLE purchase_returns
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) DEFAULT 'credit' AFTER grand_total;

-- 5. تحديث الفواتير الموجودة: لو طريقة الدفع فاضية حطها آجل
UPDATE purchase_invoices SET payment_method = 'credit' WHERE payment_method IS NULL OR payment_method = '';

-- ============================================
-- ملاحظات:
-- - كل أمر ALTER TABLE هيتجاهل لو العمود موجود (IF NOT EXISTS)
-- - لو MySQL بتاعك قديم ومش بيدعم IF NOT EXISTS مع ALTER TABLE
--   شغل الأوامر واحد واحد واتجاهل أي خطأ "Duplicate column name"
-- ============================================