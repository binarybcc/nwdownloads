-- Migration: Add week_num and year columns to daily_snapshots and subscriber_snapshots
-- Required for week-based upload system implemented in Dec 6, 2025
-- Run date: 2025-12-08

-- Add to daily_snapshots
ALTER TABLE daily_snapshots
ADD COLUMN IF NOT EXISTS week_num INT COMMENT 'ISO week number (1-53)' AFTER snapshot_date,
ADD COLUMN IF NOT EXISTS year INT COMMENT 'Year for the week' AFTER week_num;

-- Add index if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_week ON daily_snapshots(week_num, year);

-- Backfill existing data
UPDATE daily_snapshots
SET
    week_num = WEEK(snapshot_date, 3),  -- ISO week mode
    year = YEAR(snapshot_date)
WHERE week_num IS NULL OR year IS NULL;

-- Add to subscriber_snapshots
ALTER TABLE subscriber_snapshots
ADD COLUMN IF NOT EXISTS week_num INT COMMENT 'ISO week number (1-53)' AFTER snapshot_date,
ADD COLUMN IF NOT EXISTS year INT COMMENT 'Year for the week' AFTER week_num;

-- Add index if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_week_subscriber ON subscriber_snapshots(week_num, year);

-- Backfill existing data
UPDATE subscriber_snapshots
SET
    week_num = WEEK(snapshot_date, 3),
    year = YEAR(snapshot_date)
WHERE week_num IS NULL OR year IS NULL;
