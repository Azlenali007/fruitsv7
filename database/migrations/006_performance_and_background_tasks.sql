-- ==============================================================================
-- Migration: 006_performance_and_background_tasks.sql
-- Project: N.A Fresh Fruits & Coconuts
-- Purpose: Adds database indexes for checkout speed, background tasks queue,
--          and future Telegram order notification configuration.
-- ==============================================================================

-- 1. Performance Indexes for Orders & Invoices (Additive & Safe)
ALTER TABLE `orders` 
    ADD INDEX IF NOT EXISTS `idx_orders_cust_created` (`customer_id`, `created_at`),
    ADD INDEX IF NOT EXISTS `idx_orders_idempotency` (`idempotency_key`);

ALTER TABLE `invoices`
    ADD INDEX IF NOT EXISTS `idx_inv_order_id` (`order_id`),
    ADD INDEX IF NOT EXISTS `idx_inv_number` (`invoice_number`),
    ADD INDEX IF NOT EXISTS `idx_inv_customer` (`customer_id`),
    ADD INDEX IF NOT EXISTS `idx_inv_date` (`invoice_date`),
    ADD INDEX IF NOT EXISTS `idx_inv_status` (`payment_status`);

-- 2. Reliable Database-Backed Queue for Non-Critical Post-Commit Tasks
CREATE TABLE IF NOT EXISTS `background_tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_type` VARCHAR(50) NOT NULL, -- e.g. 'telegram_order_notification', 'email_receipt', 'invoice_cache'
    `reference_id` INT UNSIGNED NULL, -- Associated order_id or invoice_id
    `idempotency_token` VARCHAR(120) NOT NULL UNIQUE,
    `payload_json` LONGTEXT NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
    `last_error` TEXT NULL,
    `next_attempt_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `locked_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_bg_status_next` (`status`, `next_attempt_at`),
    INDEX `idx_bg_type_ref` (`task_type`, `reference_id`),
    INDEX `idx_bg_token` (`idempotency_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed Telegram Configuration Settings (Disabled by Default as Instructed)
INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_notifications_enabled', '0', 'Enable or disable Telegram order notifications (0 = Disabled, 1 = Enabled)'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_notifications_enabled');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_bot_token', '', 'Telegram Bot API Token (stored securely on server, never exposed in client)'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_bot_token');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_chat_id', '', 'Telegram Target Chat / Channel ID for order alerts'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_chat_id');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_send_on_cod', '1', 'Send Telegram notification on new Cash on Delivery order'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_send_on_cod');

INSERT INTO `settings` (`key_name`, `value`, `description`) 
SELECT 'telegram_send_on_online_paid', '1', 'Send Telegram notification on verified captured online payment'
WHERE NOT EXISTS (SELECT 1 FROM `settings` WHERE `key_name` = 'telegram_send_on_online_paid');
