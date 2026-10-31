-- ==============================================================================
-- Migration: 007_visitor_analytics.sql
-- Project: N.A Fresh Fruits & Coconuts
-- Purpose: Adds Live Traffic & Visitor Analytics tracking tables
--          for session tracking, traffic sources, pageviews, device detection,
--          and approximate location tracking without storing private personal data.
-- ==============================================================================

-- 1. Visitor Sessions Table
CREATE TABLE IF NOT EXISTS `visitor_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL UNIQUE,
    `visitor_ip_hash` VARCHAR(64) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `device_type` ENUM('mobile', 'desktop', 'tablet', 'unknown') NOT NULL DEFAULT 'unknown',
    `traffic_source` VARCHAR(50) NOT NULL DEFAULT 'direct', -- 'whatsapp', 'instagram', 'youtube', 'facebook', 'google', 'direct', 'referral'
    `referrer_url` VARCHAR(255) NULL,
    `utm_source` VARCHAR(50) NULL,
    `utm_medium` VARCHAR(50) NULL,
    `utm_campaign` VARCHAR(50) NULL,
    `approx_city` VARCHAR(80) NOT NULL DEFAULT 'Jaora / Local',
    `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_active_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_vs_last_active` (`last_active_at`),
    INDEX `idx_vs_traffic_source` (`traffic_source`),
    INDEX `idx_vs_device_type` (`device_type`),
    INDEX `idx_vs_first_seen` (`first_seen_at`),
    INDEX `idx_vs_ip_hash` (`visitor_ip_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Pageviews Table
CREATE TABLE IF NOT EXISTS `visitor_pageviews` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL,
    `page_url` VARCHAR(150) NOT NULL,
    `page_title` VARCHAR(100) NULL,
    `viewed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_vp_session` (`session_id`),
    INDEX `idx_vp_page_url` (`page_url`),
    INDEX `idx_vp_viewed_at` (`viewed_at`),
    CONSTRAINT `fk_vp_session` FOREIGN KEY (`session_id`) REFERENCES `visitor_sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
