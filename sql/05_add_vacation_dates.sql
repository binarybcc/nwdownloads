-- Add vacation date tracking to subscriber_snapshots
-- Migration: 05_add_vacation_dates.sql
-- Date: 2025-12-08

-- Add vacation start/end dates and calculated weeks
ALTER TABLE subscriber_snapshots
ADD COLUMN IF NOT EXISTS vacation_start DATE NULL COMMENT 'Vacation start date from Newzware',
ADD COLUMN IF NOT EXISTS vacation_end DATE NULL COMMENT 'Vacation end/return date from Newzware',
ADD COLUMN IF NOT EXISTS vacation_weeks DECIMAL(5,1) NULL COMMENT 'Calculated weeks on vacation (calendar weeks)';

-- Create index for vacation queries
CREATE INDEX IF NOT EXISTS idx_vacation_dates ON subscriber_snapshots(vacation_start, vacation_end);
CREATE INDEX IF NOT EXISTS idx_vacation_weeks ON subscriber_snapshots(vacation_weeks);

-- Add comment to table
ALTER TABLE subscriber_snapshots COMMENT = 'Weekly subscriber snapshots with vacation tracking';
