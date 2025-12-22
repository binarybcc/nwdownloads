-- Migration: 007_create_renewal_tables.sql
-- Description: Creates tables for renewal churn tracking
-- Date: 2025-12-17
--
-- Tables:
--   - renewal_events: Individual subscriber renewal/expiration events (append-only)
--   - churn_daily_summary: Daily aggregated renewal statistics by paper and subscription type
--
-- Usage:
--   cat database/migrations/007_create_renewal_tables.sql | \
--     docker exec -i circulation_db mariadb -uroot -prootpass circulation_dashboard

-- ============================================================================
-- Drop existing tables (for clean migration)
-- ============================================================================

DROP TABLE IF EXISTS renewal_events;
DROP TABLE IF EXISTS churn_daily_summary;

-- ============================================================================
-- renewal_events - Individual Renewal/Expiration Events
-- ============================================================================

CREATE TABLE renewal_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_filename VARCHAR(255) NOT NULL COMMENT 'Original CSV filename',
    event_date DATE NOT NULL COMMENT 'Issue date when renewal/expiration occurred',
    sub_num VARCHAR(50) NOT NULL COMMENT 'Subscriber number',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code (TJ, TA, TR, LJ, WRN)',
    status ENUM('RENEW', 'EXPIRE') NOT NULL COMMENT 'Renewal or expiration event',
    subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY') NOT NULL COMMENT 'Type of subscription',
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When record was imported',

    -- Indexes for performance
    INDEX idx_event_date (event_date),
    INDEX idx_paper_code (paper_code),
    INDEX idx_status (status),
    INDEX idx_subscription_type (subscription_type),
    INDEX idx_sub_num (sub_num),

    -- Unique constraint for deduplication
    UNIQUE KEY uk_renewal_event (event_date, sub_num, paper_code, status, subscription_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual subscriber renewal and expiration events (append-only with deduplication)';

-- ============================================================================
-- churn_daily_summary - Daily Aggregated Renewal Statistics
-- ============================================================================

CREATE TABLE churn_daily_summary (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL COMMENT 'Date of the snapshot',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code (TJ, TA, TR, LJ, WRN)',
    subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY') NOT NULL COMMENT 'Type of subscription',
    expiring_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of subscriptions expiring',
    renewed_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of subscriptions renewed',
    stopped_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of subscriptions that stopped (not renewed)',
    renewal_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Renewal rate percentage (0-100)',
    churn_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Churn rate percentage (0-100)',
    calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this summary was calculated',

    -- Indexes for performance
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_paper_code (paper_code),
    INDEX idx_subscription_type (subscription_type),

    -- Unique constraint for UPSERT operations
    UNIQUE KEY uk_churn_summary (snapshot_date, paper_code, subscription_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily aggregated renewal and churn statistics by paper and subscription type';

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Show table structures
SHOW CREATE TABLE renewal_events\G
SHOW CREATE TABLE churn_daily_summary\G

-- Show table statistics
SELECT 'renewal_events' as table_name, COUNT(*) as row_count FROM renewal_events
UNION ALL
SELECT 'churn_daily_summary' as table_name, COUNT(*) as row_count FROM churn_daily_summary;
