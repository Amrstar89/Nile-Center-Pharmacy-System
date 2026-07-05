-- ============================================================
-- Migration: Purchase Module Updates (Simplified Version)
-- Date: 2026-07-06
-- Run this in phpMyAdmin SQL tab or MySQL client
-- If a column already exists, you'll get a warning - just ignore it
-- ============================================================

-- ---------------------------------------------------------------
-- 1. Add missing columns to purchase_invoices
--    (if you get "Duplicate column" error, skip that line - it means it already exists)
-- ---------------------------------------------------------------

ALTER TABLE purchase_invoices 
ADD COLUMN extra_discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER paid_amount,
ADD COLUMN extra_discount_val DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER extra_discount_pct;

ALTER TABLE purchase_invoices 
ADD COLUMN payment_method VARCHAR(20) NOT NULL DEFAULT 'credit' AFTER status;

ALTER TABLE purchase_invoices 
ADD COLUMN supplier_invoice_no VARCHAR(50) NULL AFTER payment_method;

-- Add unique index on supplier_invoice_no per supplier
ALTER TABLE purchase_invoices ADD UNIQUE INDEX uk_supplier_inv_no (supplier_id, supplier_invoice_no);

-- ---------------------------------------------------------------
-- 2. Add vat_value column to purchase_invoice_items
-- ---------------------------------------------------------------

ALTER TABLE purchase_invoice_items 
ADD COLUMN vat_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER vat_percent;

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
-- 4. Create purchase_returns table
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
-- 5. Create purchase_return_items table
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
-- 6. Create supplier_payments table
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