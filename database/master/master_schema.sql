-- ╔══════════════════════════════════════════════════════════════════════════╗
-- ║          NILE CENTER ERP v3.0 - MASTER DATABASE SCHEMA                  ║
-- ║          (SaaS Multi-Tenant Platform - Central Management)              ║
-- ╚══════════════════════════════════════════════════════════════════════════╝
--
-- This database manages all tenants, subscriptions, billing, and platform-level
-- configuration. Each tenant gets their own isolated database.
--
-- Database Name: nile_center_master
-- Created: 2026-07-22
-- Version: 3.0.0

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. PLANS & PRICING (Subscription Tiers)
-- ============================================

CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_code` VARCHAR(50) NOT NULL COMMENT 'e.g., free, basic, pro, enterprise',
  `plan_name_ar` VARCHAR(100) NOT NULL COMMENT 'اسم الباقة بالعربي',
  `plan_name_en` VARCHAR(100) NOT NULL COMMENT 'Plan name in English',
  `description_ar` TEXT DEFAULT NULL,
  `description_en` TEXT DEFAULT NULL,
  `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `yearly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Discounted annual price',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'EGP',
  `trial_days` INT(11) NOT NULL DEFAULT 14,
  `max_users` INT(11) NOT NULL DEFAULT 5 COMMENT 'Maximum users per tenant',
  `max_branches` INT(11) NOT NULL DEFAULT 1 COMMENT 'Maximum branches per tenant',
  `max_products` INT(11) NOT NULL DEFAULT 1000 COMMENT 'Maximum products per tenant',
  `max_customers` INT(11) NOT NULL DEFAULT 500,
  `max_monthly_invoices` INT(11) NOT NULL DEFAULT 1000,
  `max_storage_mb` INT(11) NOT NULL DEFAULT 1024 COMMENT 'Storage limit in MB',
  `features_json` JSON DEFAULT NULL COMMENT 'Feature flags: {"pos": true, "api": true, ...}',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plan_code` (`plan_code`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Subscription plans/tiers';

-- Insert default plans
INSERT INTO `plans` (`plan_code`, `plan_name_ar`, `plan_name_en`, `monthly_price`, `yearly_price`, `trial_days`, `max_users`, `max_branches`, `max_products`, `max_customers`, `max_monthly_invoices`, `max_storage_mb`, `features_json`, `display_order`) VALUES
('free', 'مجاني', 'Free', 0.00, 0.00, 0, 2, 1, 100, 50, 100, 256, 
 '{"pos": true, "sales": true, "purchases": false, "inventory": true, "customers": true, "suppliers": false, "reports_basic": true, "reports_advanced": false, "api": false, "whatsapp": false, "multi_branch": false, "advanced_analytics": false, "ai_features": false, "ecommerce": false, "hr": false, "accounting": false, "finance": false}', 1),

('basic', 'أساسي', 'Basic', 299.00, 2990.00, 14, 5, 2, 500, 300, 500, 512, 
 '{"pos": true, "sales": true, "purchases": true, "inventory": true, "customers": true, "suppliers": true, "reports_basic": true, "reports_advanced": false, "api": true, "whatsapp": false, "multi_branch": true, "advanced_analytics": false, "ai_features": false, "ecommerce": false, "hr": false, "accounting": false, "finance": false}', 2),

('pro', 'احترافي', 'Professional', 599.00, 5990.00, 14, 15, 5, 5000, 2000, 5000, 2048, 
 '{"pos": true, "sales": true, "purchases": true, "inventory": true, "customers": true, "suppliers": true, "reports_basic": true, "reports_advanced": true, "api": true, "whatsapp": true, "multi_branch": true, "advanced_analytics": true, "ai_features": true, "ecommerce": true, "hr": false, "accounting": true, "finance": true}', 3),

('enterprise', 'مؤسسي', 'Enterprise', 1299.00, 12990.00, 30, 50, 20, 50000, 20000, 50000, 10240, 
 '{"pos": true, "sales": true, "purchases": true, "inventory": true, "customers": true, "suppliers": true, "reports_basic": true, "reports_advanced": true, "api": true, "whatsapp": true, "multi_branch": true, "advanced_analytics": true, "ai_features": true, "ecommerce": true, "hr": true, "accounting": true, "finance": true}', 4);

-- ============================================
-- 2. MASTER ADMINS (Super Admin Users)
-- ============================================

CREATE TABLE IF NOT EXISTS `master_admins` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'support', 'billing', 'readonly') NOT NULL DEFAULT 'support',
  `permissions_json` JSON DEFAULT NULL COMMENT 'Additional granular permissions',
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `login_count` INT(11) NOT NULL DEFAULT 0,
  `failed_login_attempts` INT(11) NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Super admin users for platform management';

-- Default super admin (change password after first login!)
-- Password: NileCenter@2026
INSERT INTO `master_admins` (`full_name`, `email`, `username`, `password_hash`, `role`, `is_active`) VALUES
('System Administrator', 'admin@nilecenter.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1);

-- ============================================
-- 3. TENANTS (Pharmacy Clients)
-- ============================================

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_code` VARCHAR(50) NOT NULL COMMENT 'Unique identifier e.g., pharmacy01',
  `business_name_ar` VARCHAR(200) NOT NULL COMMENT 'اسم الصيدلية',
  `business_name_en` VARCHAR(200) DEFAULT NULL,
  `slug` VARCHAR(100) NOT NULL COMMENT 'Subdomain slug e.g., alshifa',
  `custom_domain` VARCHAR(255) DEFAULT NULL COMMENT 'Custom domain e.g., pharmacy.com',
  `database_name` VARCHAR(100) NOT NULL COMMENT 'Isolated DB name: nile_tenant_{id}',
  `database_host` VARCHAR(100) NOT NULL DEFAULT 'localhost',
  `plan_id` INT(11) UNSIGNED NOT NULL,
  `status` ENUM('pending', 'trial', 'active', 'suspended', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
  `trial_ends_at` DATETIME DEFAULT NULL,
  `subscription_started_at` DATETIME DEFAULT NULL,
  `subscription_ends_at` DATETIME DEFAULT NULL,
  `billing_cycle` ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
  `country` VARCHAR(2) NOT NULL DEFAULT 'EG' COMMENT 'ISO country code',
  `city` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `tax_number` VARCHAR(50) DEFAULT NULL COMMENT 'الرقم الضريبي',
  `commercial_registration` VARCHAR(50) DEFAULT NULL COMMENT 'السجل التجاري',
  `owner_name` VARCHAR(100) DEFAULT NULL,
  `owner_phone` VARCHAR(20) DEFAULT NULL,
  `owner_email` VARCHAR(100) DEFAULT NULL,
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `favicon_url` VARCHAR(255) DEFAULT NULL,
  `primary_color` VARCHAR(7) DEFAULT '#667eea' COMMENT 'Brand primary color',
  `secondary_color` VARCHAR(7) DEFAULT '#764ba2',
  `timezone` VARCHAR(50) NOT NULL DEFAULT 'Africa/Cairo',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'EGP',
  `language` VARCHAR(5) NOT NULL DEFAULT 'ar',
  `settings_json` JSON DEFAULT NULL COMMENT 'Tenant-specific settings',
  `current_users_count` INT(11) NOT NULL DEFAULT 0,
  `current_branches_count` INT(11) NOT NULL DEFAULT 0,
  `current_products_count` INT(11) NOT NULL DEFAULT 0,
  `current_customers_count` INT(11) NOT NULL DEFAULT 0,
  `current_storage_used_mb` INT(11) NOT NULL DEFAULT 0,
  `api_key` VARCHAR(64) DEFAULT NULL COMMENT 'For API authentication',
  `api_secret` VARCHAR(64) DEFAULT NULL,
  `webhook_url` VARCHAR(255) DEFAULT NULL COMMENT 'Webhook for events',
  `ip_whitelist` TEXT DEFAULT NULL COMMENT 'Comma-separated allowed IPs',
  `notes` TEXT DEFAULT NULL,
  `suspended_reason` VARCHAR(255) DEFAULT NULL,
  `suspended_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `cancelled_reason` VARCHAR(255) DEFAULT NULL,
  `created_by` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_code`),
  UNIQUE KEY `uk_slug` (`slug`),
  UNIQUE KEY `uk_custom_domain` (`custom_domain`),
  UNIQUE KEY `uk_database_name` (`database_name`),
  KEY `idx_status` (`status`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_trial_ends` (`trial_ends_at`),
  KEY `idx_subscription_ends` (`subscription_ends_at`),
  CONSTRAINT `fk_tenants_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant (pharmacy) accounts';

-- ============================================
-- 4. SUBSCRIPTIONS (Billing Records)
-- ============================================

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) UNSIGNED NOT NULL,
  `plan_id` INT(11) UNSIGNED NOT NULL,
  `previous_plan_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'For upgrade/downgrade tracking',
  `billing_cycle` ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'EGP',
  `status` ENUM('trial', 'active', 'past_due', 'cancelled', 'expired') NOT NULL DEFAULT 'trial',
  `started_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `trial_ends_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `cancellation_reason` VARCHAR(255) DEFAULT NULL,
  `auto_renew` TINYINT(1) NOT NULL DEFAULT 1,
  `renewal_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Reminder 7 days before expiry',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_ends_at` (`ends_at`),
  CONSTRAINT `fk_subs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subs_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_subs_prev_plan` FOREIGN KEY (`previous_plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Subscription billing records';

-- ============================================
-- 5. PAYMENTS (Transaction Records)
-- ============================================

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) UNSIGNED NOT NULL,
  `subscription_id` INT(11) UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'EGP',
  `payment_method` ENUM('cash', 'bank_transfer', 'instapay', 'vodafone_cash', 'paymob', 'fawry', 'credit_card', 'free') NOT NULL,
  `payment_status` ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
  `transaction_id` VARCHAR(255) DEFAULT NULL COMMENT 'External payment gateway transaction ID',
  `gateway_response` JSON DEFAULT NULL COMMENT 'Raw response from payment gateway',
  `paid_at` DATETIME DEFAULT NULL,
  `refunded_at` DATETIME DEFAULT NULL,
  `refund_amount` DECIMAL(10,2) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_sub` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment transactions';

-- ============================================
-- 6. MASTER ACTIVITY LOGS (Platform-wide)
-- ============================================

CREATE TABLE IF NOT EXISTS `master_activity_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = platform action',
  `admin_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = system/automated',
  `action` VARCHAR(50) NOT NULL COMMENT 'e.g., tenant_created, subscription_updated',
  `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., tenant, subscription, payment',
  `entity_id` INT(11) UNSIGNED DEFAULT NULL,
  `description_ar` TEXT DEFAULT NULL,
  `description_en` TEXT DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`, `entity_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Platform-wide audit trail';

-- ============================================
-- 7. NOTIFICATIONS (Platform Notifications)
-- ============================================

CREATE TABLE IF NOT EXISTS `master_notifications` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = broadcast to all',
  `admin_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Target admin (NULL = all admins)',
  `type` ENUM('info', 'warning', 'success', 'error', 'billing', 'security') NOT NULL DEFAULT 'info',
  `title_ar` VARCHAR(200) NOT NULL,
  `title_en` VARCHAR(200) DEFAULT NULL,
  `message_ar` TEXT NOT NULL,
  `message_en` TEXT DEFAULT NULL,
  `action_url` VARCHAR(255) DEFAULT NULL,
  `action_text` VARCHAR(100) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifications for admins and tenants';

-- ============================================
-- 8. API KEYS (For tenant API access)
-- ============================================

CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) UNSIGNED NOT NULL,
  `key_name` VARCHAR(100) NOT NULL COMMENT 'e.g., Mobile App, POS Device 1',
  `api_key` VARCHAR(64) NOT NULL COMMENT 'Hashed API key',
  `api_secret` VARCHAR(64) NOT NULL COMMENT 'Hashed secret',
  `permissions_json` JSON DEFAULT NULL COMMENT '{"read_sales": true, "write_products": false}',
  `ip_restrictions` TEXT DEFAULT NULL COMMENT 'Allowed IPs, comma-separated',
  `last_used_at` DATETIME DEFAULT NULL,
  `usage_count` INT(11) NOT NULL DEFAULT 0,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_key` (`api_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_apikeys_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API keys for tenant integrations';

-- ============================================
-- 9. WEBHOOK LOGS
-- ============================================

CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) UNSIGNED NOT NULL,
  `event_type` VARCHAR(50) NOT NULL COMMENT 'e.g., sale.created, payment.received',
  `payload` JSON NOT NULL,
  `response_status` INT(11) DEFAULT NULL COMMENT 'HTTP response status',
  `response_body` TEXT DEFAULT NULL,
  `attempt_count` INT(11) NOT NULL DEFAULT 1,
  `last_attempt_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `next_retry_at` DATETIME DEFAULT NULL,
  `is_delivered` TINYINT(1) NOT NULL DEFAULT 0,
  `delivered_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_is_delivered` (`is_delivered`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook delivery logs';

-- ============================================
-- 10. SYSTEM SETTINGS
-- ============================================

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'general, email, sms, payment, security',
  `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`),
  KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Platform configuration settings';

-- Default settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('platform_name_ar', 'نايل سنتر', 'general', 'Platform name in Arabic'),
('platform_name_en', 'Nile Center', 'general', 'Platform name in English'),
('default_timezone', 'Africa/Cairo', 'general', 'Default timezone for new tenants'),
('default_currency', 'EGP', 'general', 'Default currency'),
('default_language', 'ar', 'general', 'Default language'),
('max_login_attempts', '5', 'security', 'Max failed login attempts before lockout'),
('lockout_duration_minutes', '15', 'security', 'Account lockout duration'),
('jwt_access_token_ttl', '3600', 'security', 'JWT access token lifetime in seconds'),
('jwt_refresh_token_ttl', '604800', 'security', 'JWT refresh token lifetime in seconds'),
('password_min_length', '8', 'security', 'Minimum password length'),
('require_strong_password', '1', 'security', 'Require uppercase, lowercase, number, symbol'),
('smtp_host', '', 'email', 'SMTP server host'),
('smtp_port', '587', 'email', 'SMTP server port'),
('smtp_username', '', 'email', 'SMTP username'),
('smtp_password', '', 'email', 'SMTP password'),
('smtp_encryption', 'tls', 'email', 'SMTP encryption (tls/ssl)'),
('email_from_address', 'noreply@nilecenter.com', 'email', 'Default sender email'),
('email_from_name', 'Nile Center', 'email', 'Default sender name'),
('sms_provider', '', 'sms', 'SMS provider (twilio, vonage, custom)'),
('sms_api_key', '', 'sms', 'SMS API key'),
('sms_api_secret', '', 'sms', 'SMS API secret'),
('whatsapp_provider', '', 'whatsapp', 'WhatsApp provider (meta, wati, 360dialog)'),
('whatsapp_api_key', '', 'whatsapp', 'WhatsApp API key'),
('paymob_integration_key', '', 'payment', 'Paymob integration key'),
('paymob_iframe_id', '', 'payment', 'Paymob iframe ID'),
('instapay_api_key', '', 'payment', 'InstaPay API key'),
('maintenance_mode', '0', 'general', 'Platform maintenance mode'),
('allow_self_registration', '1', 'general', 'Allow public tenant registration'),
('require_manual_approval', '1', 'general', 'Require admin approval for new tenants');

-- ============================================
-- 11. BACKUP LOGS
-- ============================================

CREATE TABLE IF NOT EXISTS `backup_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL = full platform backup',
  `backup_type` ENUM('full', 'database', 'files') NOT NULL DEFAULT 'full',
  `file_path` VARCHAR(500) NOT NULL,
  `file_size_mb` DECIMAL(10,2) DEFAULT NULL,
  `started_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `status` ENUM('running', 'completed', 'failed') NOT NULL DEFAULT 'running',
  `error_message` TEXT DEFAULT NULL,
  `created_by` INT(11) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Backup operation logs';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- VIEWS FOR CONVENIENCE
-- ============================================

CREATE OR REPLACE VIEW `v_tenant_overview` AS
SELECT 
  t.id,
  t.tenant_code,
  t.business_name_ar,
  t.slug,
  t.custom_domain,
  t.status,
  p.plan_name_ar,
  p.plan_name_en,
  p.monthly_price,
  p.yearly_price,
  s.status as subscription_status,
  s.ends_at as subscription_ends_at,
  t.current_users_count,
  t.current_branches_count,
  t.current_products_count,
  t.current_customers_count,
  t.created_at,
  t.updated_at,
  CASE 
    WHEN t.status = 'trial' AND t.trial_ends_at < NOW() THEN 'trial_expired'
    WHEN t.status = 'active' AND s.ends_at < NOW() THEN 'subscription_expired'
    ELSE t.status 
  END as computed_status
FROM tenants t
LEFT JOIN plans p ON t.plan_id = p.id
LEFT JOIN subscriptions s ON t.id = s.tenant_id AND s.status IN ('trial', 'active', 'past_due');

CREATE OR REPLACE VIEW `v_revenue_summary` AS
SELECT 
  DATE_FORMAT(p.created_at, '%Y-%m') as month,
  COUNT(*) as payment_count,
  SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
  SUM(CASE WHEN p.payment_method = 'instapay' AND p.payment_status = 'completed' THEN p.amount ELSE 0 END) as instapay_revenue,
  SUM(CASE WHEN p.payment_method = 'vodafone_cash' AND p.payment_status = 'completed' THEN p.amount ELSE 0 END) as vodafone_revenue,
  SUM(CASE WHEN p.payment_method = 'paymob' AND p.payment_status = 'completed' THEN p.amount ELSE 0 END) as paymob_revenue
FROM payments p
GROUP BY DATE_FORMAT(p.created_at, '%Y-%m');

-- ============================================
-- TRIGGERS FOR AUDITING
-- ============================================

DELIMITER //

CREATE TRIGGER `trg_tenants_insert_log` 
AFTER INSERT ON `tenants`
FOR EACH ROW
BEGIN
  INSERT INTO `master_activity_logs` 
    (`tenant_id`, `action`, `entity_type`, `entity_id`, `description_ar`, `description_en`, `new_values`)
  VALUES 
    (NEW.id, 'tenant_created', 'tenant', NEW.id, 
     CONCAT('تم إنشاء مستأجر جديد: ', NEW.business_name_ar),
     CONCAT('New tenant created: ', NEW.business_name_en),
     JSON_OBJECT('tenant_code', NEW.tenant_code, 'plan_id', NEW.plan_id, 'status', NEW.status));
END//

CREATE TRIGGER `trg_tenants_update_log`
AFTER UPDATE ON `tenants`
FOR EACH ROW
BEGIN
  IF OLD.status != NEW.status THEN
    INSERT INTO `master_activity_logs` 
      (`tenant_id`, `action`, `entity_type`, `entity_id`, `description_ar`, `description_en`, `old_values`, `new_values`)
    VALUES 
      (NEW.id, 'tenant_status_changed', 'tenant', NEW.id,
       CONCAT('تغيير حالة المستأجر من ', OLD.status, ' إلى ', NEW.status),
       CONCAT('Tenant status changed from ', OLD.status, ' to ', NEW.status),
       JSON_OBJECT('status', OLD.status),
       JSON_OBJECT('status', NEW.status));
  END IF;
END//

CREATE TRIGGER `trg_subscriptions_insert_log`
AFTER INSERT ON `subscriptions`
FOR EACH ROW
BEGIN
  INSERT INTO `master_activity_logs` 
    (`tenant_id`, `action`, `entity_type`, `entity_id`, `description_ar`, `description_en`, `new_values`)
  VALUES 
    (NEW.tenant_id, 'subscription_created', 'subscription', NEW.id,
     CONCAT('تم إنشاء اشتراك جديد - الحالة: ', NEW.status),
     CONCAT('New subscription created - Status: ', NEW.status),
     JSON_OBJECT('plan_id', NEW.plan_id, 'amount', NEW.amount, 'billing_cycle', NEW.billing_cycle));
END//

DELIMITER ;

-- ═══════════════════════════════════════════════════════════════════════════
-- END OF MASTER DATABASE SCHEMA
-- ═══════════════════════════════════════════════════════════════════════════
