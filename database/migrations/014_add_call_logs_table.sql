-- Migration: Add call_logs table
-- Created: 2026-03-20
-- Purpose: Store BroadWorks call log entries for subscriber contact matching.
--          Phone numbers normalized to bare 10-digit for JOIN against subscriber_snapshots.

CREATE TABLE IF NOT EXISTS call_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_direction  ENUM('placed','received','missed') NOT NULL,
    call_timestamp  DATETIME NOT NULL,
    duration_sec    INT UNSIGNED DEFAULT 0,
    remote_number   VARCHAR(50) NOT NULL COMMENT 'Raw number from BroadWorks',
    phone_normalized CHAR(10) DEFAULT NULL COMMENT 'Bare 10-digit, no punctuation',
    local_extension VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'BC or CW extension, empty if unknown',
    source_group    VARCHAR(20) DEFAULT NULL COMMENT 'BC or CW (BroadWorks group)',
    raw_payload     TEXT DEFAULT NULL COMMENT 'Optional: raw row for debugging',
    imported_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_call_timestamp    (call_timestamp),
    INDEX idx_phone_normalized  (phone_normalized),
    INDEX idx_direction         (call_direction),
    INDEX idx_source_group      (source_group),
    UNIQUE KEY uq_call (call_timestamp, remote_number, local_extension, call_direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='BroadWorks call log entries, normalized for subscriber matching';

-- Rollback (for reference):
-- DROP TABLE IF EXISTS call_logs;
