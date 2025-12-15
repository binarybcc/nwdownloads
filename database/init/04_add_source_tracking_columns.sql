-- Migration: Add source tracking columns for softBackfill system
-- Required for tracking which upload owns which data
-- Run date: 2025-12-08

-- Add source tracking to daily_snapshots
ALTER TABLE daily_snapshots
ADD COLUMN IF NOT EXISTS source_filename VARCHAR(255) COMMENT 'Original CSV filename that created this snapshot' AFTER created_at,
ADD COLUMN IF NOT EXISTS source_date DATE COMMENT 'Date extracted from source filename' AFTER source_filename,
ADD COLUMN IF NOT EXISTS is_backfilled TINYINT(1) DEFAULT 0 COMMENT '1 if backfilled, 0 if real data' AFTER source_date,
ADD COLUMN IF NOT EXISTS backfill_weeks INT COMMENT 'Number of weeks backfilled (0 = real data)' AFTER is_backfilled;

-- Add indexes for source tracking
CREATE INDEX IF NOT EXISTS idx_source_date ON daily_snapshots(source_date);
CREATE INDEX IF NOT EXISTS idx_backfilled ON daily_snapshots(is_backfilled);

-- Add source tracking to subscriber_snapshots
ALTER TABLE subscriber_snapshots
ADD COLUMN IF NOT EXISTS source_filename VARCHAR(255) COMMENT 'Original CSV filename that created this snapshot' AFTER created_at,
ADD COLUMN IF NOT EXISTS source_date DATE COMMENT 'Date extracted from source filename' AFTER source_filename,
ADD COLUMN IF NOT EXISTS is_backfilled TINYINT(1) DEFAULT 0 COMMENT '1 if backfilled, 0 if real data' AFTER source_date,
ADD COLUMN IF NOT EXISTS backfill_weeks INT COMMENT 'Number of weeks backfilled (0 = real data)' AFTER is_backfilled;

-- Add indexes for source tracking
CREATE INDEX IF NOT EXISTS idx_source_date_subscriber ON subscriber_snapshots(source_date);
CREATE INDEX IF NOT EXISTS idx_backfilled_subscriber ON subscriber_snapshots(is_backfilled);
