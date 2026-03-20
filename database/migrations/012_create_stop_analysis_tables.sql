-- Migration: 012_create_stop_analysis_tables.sql
-- Description: Creates tables for stop analysis tracking (per-subscriber stop data with reasons)
-- Date: 2026-03-03
--
-- Tables:
--   - stop_events: Individual subscriber stop events with contact info, reasons, and remarks
--   - stop_daily_summary: Daily aggregated stop counts by paper
--
-- Usage:
--   ssh nas "/usr/local/mariadb10/bin/mysql -uroot -p'PASSWORD' -S /run/mysqld/mysqld10.sock circulation_dashboard" \
--     < database/migrations/012_create_stop_analysis_tables.sql

-- ============================================================================
-- stop_events - Individual Subscriber Stop Events
-- ============================================================================

CREATE TABLE IF NOT EXISTS stop_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_filename VARCHAR(255) NOT NULL COMMENT 'Original CSV filename',
    sub_num VARCHAR(50) NOT NULL COMMENT 'Subscriber number',
    first_name VARCHAR(100) DEFAULT NULL COMMENT 'Subscriber first name',
    last_name VARCHAR(100) DEFAULT NULL COMMENT 'Subscriber last name',
    street_address VARCHAR(255) DEFAULT NULL COMMENT 'Street address line 1',
    address2 VARCHAR(255) DEFAULT NULL COMMENT 'Street address line 2',
    city VARCHAR(100) DEFAULT NULL COMMENT 'City',
    state VARCHAR(10) DEFAULT NULL COMMENT 'State abbreviation',
    zip VARCHAR(10) DEFAULT NULL COMMENT 'ZIP code',
    phone VARCHAR(20) DEFAULT NULL COMMENT 'Phone number',
    email VARCHAR(255) DEFAULT NULL COMMENT 'Email address',
    start_date DATE DEFAULT NULL COMMENT 'Original subscription start date',
    rate VARCHAR(20) DEFAULT NULL COMMENT 'Rate code',
    stop_date DATE NOT NULL COMMENT 'Date subscription stopped',
    paid_date DATE DEFAULT NULL COMMENT 'Paid-through date',
    stop_reason VARCHAR(255) DEFAULT NULL COMMENT 'Reason for stop (e.g., STOP - AUTO EXPIRE, STOP - COST)',
    remark TEXT DEFAULT NULL COMMENT 'Additional remarks from customer service',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code (TJ, TA, TR, LJ, WRN)',
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When record was imported',

    -- Indexes for performance
    INDEX idx_stop_date (stop_date),
    INDEX idx_sub_num (sub_num),
    INDEX idx_paper_code (paper_code),
    INDEX idx_stop_reason (stop_reason(50)),

    -- Unique constraint: one stop per subscriber per paper per stop date (UPSERT safe)
    UNIQUE KEY uq_sub_paper_stopdate (sub_num, paper_code, stop_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual subscriber stop events from Newzware Stop Analysis Report';

-- ============================================================================
-- stop_daily_summary - Daily Aggregated Stop Counts
-- ============================================================================

CREATE TABLE IF NOT EXISTS stop_daily_summary (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL COMMENT 'Date of the snapshot',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code (TJ, TA, TR, LJ, WRN)',
    stop_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total stop events for this date/paper',
    calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When this summary was last calculated',

    -- Indexes for performance
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_paper_code (paper_code),

    -- Unique constraint for UPSERT operations
    UNIQUE KEY uq_date_paper (snapshot_date, paper_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily aggregated stop counts by paper from Stop Analysis Report';

-- ============================================================================
-- Verification
-- ============================================================================

SELECT 'stop_events' AS table_name, COUNT(*) AS row_count FROM stop_events
UNION ALL
SELECT 'stop_daily_summary' AS table_name, COUNT(*) AS row_count FROM stop_daily_summary;
