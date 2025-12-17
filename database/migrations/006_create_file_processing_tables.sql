-- Migration 006: Create File Processing Tables
-- Date: 2025-12-16
-- Purpose: Add automated file processing infrastructure
--
-- Tables created:
-- 1. file_processing_log: Audit trail of all processing attempts
-- 2. file_processing_patterns: Configurable filename → processor mapping

-- ============================================================================
-- Table: file_processing_log
-- ============================================================================
-- Tracks every file processing attempt with detailed status and timing
-- Provides complete audit trail for troubleshooting and analytics

CREATE TABLE IF NOT EXISTS file_processing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- File identification
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL COMMENT 'allsubscriber, vacation, renewals',
    processor_class VARCHAR(100) NOT NULL COMMENT 'Class name that processed the file',

    -- Processing status
    status ENUM('processing', 'completed', 'failed', 'skipped') NOT NULL,
    records_processed INT DEFAULT 0 COMMENT 'Number of records imported',
    error_message TEXT NULL COMMENT 'Error details if failed',

    -- Timing information
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    processing_duration_seconds DECIMAL(10,2) NULL COMMENT 'Total processing time',

    -- File metadata
    file_size_bytes INT NULL,
    file_moved_to VARCHAR(255) NULL COMMENT 'completed/ or failed/ directory',

    -- Backfill tracking
    is_backfill BOOLEAN DEFAULT FALSE COMMENT 'Was this a backfill week?',
    backfill_weeks INT DEFAULT 0 COMMENT 'How many weeks back from upload date',

    -- Audit timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for common queries
    INDEX idx_filename (filename),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    INDEX idx_file_type (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail of automated file processing attempts';

-- ============================================================================
-- Table: file_processing_patterns
-- ============================================================================
-- Configurable filename patterns for routing files to appropriate processors
-- Allows Settings page configuration without code changes

CREATE TABLE IF NOT EXISTS file_processing_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Pattern matching
    pattern VARCHAR(255) NOT NULL COMMENT 'Filename pattern with wildcards',
    processor_class VARCHAR(100) NOT NULL COMMENT 'Processor class to handle matches',
    description TEXT NULL COMMENT 'Human-readable description',

    -- Configuration
    enabled BOOLEAN DEFAULT TRUE COMMENT 'Enable/disable pattern',
    is_default BOOLEAN DEFAULT FALSE COMMENT 'System default vs user-added',
    priority INT DEFAULT 100 COMMENT 'Match priority (lower = higher priority)',

    -- Audit timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Ensure unique patterns
    UNIQUE KEY unique_pattern (pattern),

    -- Indexes for pattern matching performance
    INDEX idx_enabled (enabled),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Filename patterns for automated file processor routing';

-- ============================================================================
-- Default Pattern Seeds
-- ============================================================================
-- Pre-populate with standard Newzware file patterns
-- These can be edited via Settings page

INSERT INTO file_processing_patterns
    (pattern, processor_class, description, is_default, enabled, priority)
VALUES
    (
        'AllSubscriberReport*.csv',
        'AllSubscriberProcessor',
        'Newzware All Subscriber Report - weekly circulation data with complete subscriber details',
        TRUE,
        TRUE,
        10
    ),
    (
        'SubscribersOnVacation*.csv',
        'VacationProcessor',
        'Newzware Subscribers On Vacation export - tracks temporary delivery holds',
        TRUE,
        FALSE,
        20
    ),
    (
        '*Renewal*.csv',
        'RenewalProcessor',
        'Renewal and churn tracking data - subscriber retention metrics',
        TRUE,
        FALSE,
        30
    )
ON DUPLICATE KEY UPDATE
    -- If pattern already exists, don't overwrite user changes
    pattern = pattern;

-- ============================================================================
-- Verification Queries
-- ============================================================================
-- Run these to verify migration success:
--
-- SHOW TABLES LIKE 'file_processing_%';
-- SELECT * FROM file_processing_patterns ORDER BY priority;
-- DESCRIBE file_processing_log;
-- DESCRIBE file_processing_patterns;
