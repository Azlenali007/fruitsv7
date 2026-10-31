-- Migration: 005_automatic_invoices.sql
-- N.A Fresh Fruits & Coconuts
-- Automatic Professional Invoice / Bill Generation System

-- 1. Create Invoices Table
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(40) NOT NULL UNIQUE,
    `order_id` INT UNSIGNED NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `customer_email` VARCHAR(100) NULL,
    `delivery_address` TEXT NULL,
    `delivery_area_name` VARCHAR(150) NULL,
    `invoice_date` DATETIME NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(20) NOT NULL,
    `payment_status` VARCHAR(40) NOT NULL,
    `payment_reference` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_inv_number` (`invoice_number`),
    INDEX `idx_inv_order` (`order_id`),
    INDEX `idx_inv_customer` (`customer_id`),
    INDEX `idx_inv_phone` (`customer_phone`),
    INDEX `idx_inv_date` (`invoice_date`),
    INDEX `idx_inv_pay_status` (`payment_status`),
    CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Invoice Sequences Table for race-condition-safe numbering
CREATE TABLE IF NOT EXISTS `invoice_sequences` (
    `sequence_year` INT NOT NULL PRIMARY KEY,
    `last_number` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
