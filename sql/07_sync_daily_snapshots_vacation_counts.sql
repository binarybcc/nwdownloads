-- Sync vacation counts from subscriber_snapshots to daily_snapshots
-- Migration: 07_sync_daily_snapshots_vacation_counts.sql
-- Date: 2025-12-09
--
-- Problem: daily_snapshots.on_vacation was not updated when subscriber_snapshots.on_vacation was set
-- Solution: Recalculate vacation counts in daily_snapshots from subscriber_snapshots aggregates

USE circulation_dashboard;

-- Recalculate vacation counts for all snapshots
UPDATE daily_snapshots ds
INNER JOIN (
    SELECT
        snapshot_date,
        paper_code,
        SUM(on_vacation) as vacation_count
    FROM subscriber_snapshots
    WHERE on_vacation = 1
    GROUP BY snapshot_date, paper_code
) ss ON ds.snapshot_date = ss.snapshot_date AND ds.paper_code = ss.paper_code
SET ds.on_vacation = ss.vacation_count;

-- Verify synchronization
SELECT
    'Vacation counts synchronized' AS status,
    COUNT(DISTINCT ds.paper_code) as papers_updated,
    SUM(ds.on_vacation) as total_vacations
FROM daily_snapshots ds
WHERE ds.on_vacation > 0;

-- Show vacation counts by business unit
SELECT
    business_unit,
    snapshot_date,
    SUM(on_vacation) as vacation_count
FROM daily_snapshots
WHERE on_vacation > 0
GROUP BY business_unit, snapshot_date
ORDER BY snapshot_date DESC, business_unit;
