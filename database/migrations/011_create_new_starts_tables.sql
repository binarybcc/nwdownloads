-- Migration: 011_create_new_starts_tables.sql
-- Description: Creates tables for new subscription starts tracking
-- Date: 2026-03-02
--
-- Tables:
--   - new_start_events: Individual new subscriber start events with cross-reference classification
--   - new_starts_daily_summary: Daily aggregated new start counts by paper
--
-- Usage:
--   cat database/migrations/011_create_new_starts_tables.sql | \
--     ssh nas "/usr/local/mariadb10/bin/mysql -uroot circulation_dashboard"

-- ============================================================================
-- new_start_events - Individual New Subscriber Start Events
-- ============================================================================

CREATE TABLE IF NOT EXISTS new_start_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_filename VARCHAR(255) NOT NULL COMMENT 'Original CSV filename',
    event_date DATE NOT NULL COMMENT 'Subscription start date',
    sub_num VARCHAR(50) NOT NULL COMMENT 'Subscriber number (cross-referenced against renewal_events)',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code (TJ, TA, TR, LJ, WRN)',
    issue_code VARCHAR(10) DEFAULT NULL COMMENT 'Issue code / frequency (5D, WA, WO, WS, THU)',
    delivery_type VARCHAR(10) DEFAULT NULL COMMENT 'Delivery method (MAIL, CARR, INTE)',
    remark_code VARCHAR(100) DEFAULT NULL COMMENT 'Acquisition source / remark',
    submit_code VARCHAR(50) DEFAULT NULL COMMENT 'Submission channel (CUSTSERV, WEB)',
    is_truly_new TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = genuinely new subscriber, 0 = restart/overlap with renewal_events',
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When record was imported',

    -- Indexes for performance
    INDEX idx_event_date (event_date),
    INDEX idx_sub_num (sub_num),
    INDEX idx_paper_code (paper_code),
    INDEX idx_is_truly_new (is_truly_new),

    -- Unique constraint: one start per subscriber per paper per date (UPSERT safe)
    UNIQUE KEY uq_sub_paper_date (sub_num, paper_code, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual new subscriber start events with cross-reference classification against renewal_events';

-- ============================================================================
-- new_starts_daily_summary - Daily Aggregated New Start Counts
-- ============================================================================

CREATE TABLE IF NOT EXISTS new_starts_daily_summary (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL COMMENT 'Date of the snapshot',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code (TJ, TA, TR, LJ, WRN)',
    total_new_starts INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total new start events for this date/paper',
    truly_new_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Count of genuinely new subscribers (no prior renewal_events)',
    restart_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Count of restarts/overlaps with renewal_events',
    calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When this summary was last calculated',

    -- Indexes for performance
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_paper_code (paper_code),

    -- Unique constraint for UPSERT operations
    UNIQUE KEY uq_date_paper (snapshot_date, paper_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily aggregated new subscription start counts by paper, split by truly-new vs restart';

-- ============================================================================
-- Verification
-- ============================================================================

SELECT 'new_start_events' AS table_name, COUNT(*) AS row_count FROM new_start_events
UNION ALL
SELECT 'new_starts_daily_summary' AS table_name, COUNT(*) AS row_count FROM new_starts_daily_summary;
