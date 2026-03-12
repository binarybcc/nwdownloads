-- Migration 013: Add comp_count column to daily_snapshots
-- Tracks complimentary (non-paying) subscribers separately from total_active
-- so dashboard can show both total reach and paying-only counts.

ALTER TABLE daily_snapshots ADD COLUMN comp_count INT NOT NULL DEFAULT 0 AFTER digital_only;

-- Backfill comp_count from subscriber_snapshots historical data
UPDATE daily_snapshots ds
SET comp_count = (
    SELECT COUNT(*)
    FROM subscriber_snapshots ss
    WHERE ss.week_num = ds.week_num
      AND ss.year = ds.year
      AND ss.paper_code = ds.paper_code
      AND UPPER(ss.payment_status) = 'COMP'
);
