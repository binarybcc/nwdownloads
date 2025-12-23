-- ============================================================================
-- Migration Tracking System
-- ============================================================================
-- Created: 2025-12-22
-- Purpose: Prevent catastrophic re-runs of migrations by tracking execution
--
-- CRITICAL: This migration creates the tracking infrastructure
-- It MUST be run before any other migrations
--
-- Design Principles:
-- 1. Every migration is recorded when executed
-- 2. No migration can run twice (prevents data loss)
-- 3. Tracks success/failure status
-- 4. Records execution time and checksum
-- ============================================================================

-- Create migration tracking table if it doesn't exist
CREATE TABLE IF NOT EXISTS migration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Migration identification
    migration_file VARCHAR(255) NOT NULL UNIQUE COMMENT 'Filename of migration (e.g., 006_create_file_processing_tables.sql)',
    migration_number INT NOT NULL COMMENT 'Numeric prefix for ordering (e.g., 6)',
    migration_description VARCHAR(500) NULL COMMENT 'Human-readable description',

    -- Execution tracking
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When migration was run',
    execution_time_seconds DECIMAL(10,3) NULL COMMENT 'How long it took to execute',
    status ENUM('pending', 'running', 'completed', 'failed', 'rolled_back') NOT NULL DEFAULT 'pending',

    -- Verification
    file_checksum VARCHAR(64) NULL COMMENT 'SHA-256 hash of migration file content',
    executed_by VARCHAR(100) DEFAULT 'migration_runner' COMMENT 'Who/what ran this migration',

    -- Error tracking
    error_message TEXT NULL COMMENT 'Error details if migration failed',

    -- Backup reference
    backup_created BOOLEAN DEFAULT FALSE COMMENT 'Was a backup created before this migration?',
    backup_path VARCHAR(500) NULL COMMENT 'Path to backup file if created',

    -- Indexes
    INDEX idx_migration_number (migration_number),
    INDEX idx_status (status),
    INDEX idx_executed_at (executed_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks which database migrations have been executed to prevent destructive re-runs';

-- ============================================================================
-- Verification
-- ============================================================================

SELECT
    'Migration tracking system initialized' AS status,
    COUNT(*) as migrations_tracked
FROM migration_log;

-- ============================================================================
-- Usage Notes
-- ============================================================================
--
-- Before running ANY migration:
-- 1. Check if it's already been run: SELECT * FROM migration_log WHERE migration_file = 'XXX.sql';
-- 2. Create backup: Run backup script
-- 3. Record migration start: INSERT INTO migration_log (migration_file, ...) VALUES (...);
-- 4. Run migration
-- 5. Update status: UPDATE migration_log SET status = 'completed', execution_time_seconds = X WHERE migration_file = 'XXX.sql';
--
-- The migration runner script automates this process.
-- ============================================================================
