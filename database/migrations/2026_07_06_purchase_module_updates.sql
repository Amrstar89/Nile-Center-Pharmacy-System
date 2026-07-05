-- ============================================================
-- Migration: Purchase Module Updates
-- Date: 2026-07-06
-- Author: System
-- Description: Adds missing columns and tables for the new
--              purchase invoice, payment, and returns system
-- ============================================================

-- ---------------------------------------------------------------
-- 1. Add missing columns to purchase_invoices
-- ---------------------------------------------------------------

-- Check and add extra_discount_pct
SET @col_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'purchase_invoices' 
    AND COLUMN_NAME = 'extra_discount_pct');
    
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE purchase_invoices ADD COLUMN extra_discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER paid_amount', 
    'SELECT "extra_discount_pct already exists" as msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add extra_discount_val
SET @col_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'purchase_invoices' 
    AND COLUMN_NAME = 'extra_discount_val');
    
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE purchase_invoices ADD COLUMN extra_discount_val DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER extra_discount_pct', 
    'SELECT "extra_discount_val already exists" as msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add supplier_invoice_no
SET @col_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'purchase_invoices' 
    AND COLUMN_NAME = 'supplier_invoice_no');
    
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE purchase_invoices ADD COLUMN supplier_invoice_no VARCHAR(50) NULL AFTER payment_method', 
    'SELECT "supplier_invoice_no already exists" as msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add payment_method
SET @col_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'purchase_invoices' 
    AND COLUMN_NAME = 'payment_method');
    
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE purchase_invoices ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT "credit" AFTER status', 
    'SELECT "payment_method already exists" as msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unique index on supplier_invoice_no + supplier_id
SET @idx_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'purchase_invoices' 
    AND INDEX_NAME = 'uk_supplier_inv_no');
    
SET @sql = IF(@idx_exists = 0, 
    'ALTER TABLE purchase_invoices ADD UNIQUE INDEX uk_supplier_inv_no (supplier_id, supplier_invoice_no)', 
    'SELECT "uk_supplier_inv_no already exists" as msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------
-- 2. Add vat_value column to purchase_invoice_items (if missing)
-- ---------------------------------------------------------------

SET @col_exists = (SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'purchase_invoice_items' 
    AND COLUMN_NAME = 'vat_value');
    
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE purchase_invoice_items ADD COLUMN vat_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER vat_percent', 
    'SELECT "vat_value already exists in purchase_invoice_items" as msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------
-- 3. Create supplier_transactions table (for deferred amounts)
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS supplier_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    invoice_id INT NULL,
    transaction_type VARCHAR(30) NOT NULL COMMENT 'invoice_deferred, payment, return, adjustment',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_supplier (supplier_id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_type (transaction_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 4. Create purchase_returns table (if not exists)
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS purchase_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_number VARCHAR(30) NOT NULL UNIQUE,
    invoice_id INT NULL,
    supplier_id INT NOT NULL,
    store_id INT NULL,
    return_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_return_number (return_number),
    INDEX idx_supplier (supplier_id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_date (return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 5. Create purchase_return_items table (if not exists)
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS purchase_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    invoice_item_id INT NULL,
    product_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_code VARCHAR(50) NULL,
    barcode VARCHAR(50) NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255) NULL,
    INDEX idx_return (return_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 6. Create supplier_payments table (if not exists)
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(30) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    invoice_id INT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_number (payment_number),
    INDEX idx_supplier (supplier_id),
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- 7. Verify: Show current columns in purchase_invoices
-- ---------------------------------------------------------------

SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_invoices'
ORDER BY ORDINAL_POSITION;