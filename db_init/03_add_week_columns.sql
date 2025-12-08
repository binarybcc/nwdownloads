-- Migration: Add week_num and year columns to daily_snapshots
-- Required for week-based upload system implemented in Dec 6, 2025
-- Run date: 2025-12-08

ALTER TABLE daily_snapshots
ADD COLUMN week_num INT COMMENT 'ISO week number (1-53)' AFTER snapshot_date,
ADD COLUMN year INT COMMENT 'Year for the week' AFTER week_num,
ADD INDEX idx_week (week_num, year);

-- Backfill existing data with week/year values
UPDATE daily_snapshots
SET
    week_num = WEEK(snapshot_date, 3),  -- ISO week mode
    year = YEAR(snapshot_date);
