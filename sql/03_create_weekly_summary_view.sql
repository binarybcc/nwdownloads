-- Phase 1A: Create weekly_summary view
-- Purpose: Aggregate daily snapshots into weekly metrics (print days only)
-- Date: 2025-12-01

-- Drop existing view if it exists
DROP VIEW IF EXISTS weekly_summary;

-- Create the view
CREATE VIEW weekly_summary AS
SELECT
    -- Week identification (Monday = start of week)
    DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY) as week_start_date,
    CONCAT(
        DATE_FORMAT(DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY), '%b %d'),
        ' - ',
        DATE_FORMAT(DATE_ADD(DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY), INTERVAL 6 DAY), '%b %d, %Y')
    ) as week_label,

    -- Paper identification
    ds.paper_code,
    ds.paper_name,
    ds.business_unit,

    -- Weekly metrics (only from print days)
    COUNT(DISTINCT ds.snapshot_date) as print_days_reported,
    ROUND(AVG(ds.total_active), 0) as avg_total_active,
    ROUND(AVG(ds.deliverable), 0) as avg_deliverable,
    MAX(ds.total_active) as max_total_active,
    MIN(ds.total_active) as min_total_active,
    MAX(ds.total_active) - MIN(ds.total_active) as weekly_variation,

    -- Delivery method averages
    ROUND(AVG(ds.mail_delivery), 0) as avg_mail,
    ROUND(AVG(ds.carrier_delivery), 0) as avg_carrier,
    ROUND(AVG(ds.digital_only), 0) as avg_digital,
    ROUND(AVG(ds.on_vacation), 0) as avg_vacation,

    -- Data quality
    MAX(ds.snapshot_date) as latest_snapshot_in_week,

    -- Calculate expected print days for this paper
    (SELECT COUNT(*)
     FROM publication_schedule ps2
     WHERE ps2.paper_code = ds.paper_code
     AND ps2.has_print = TRUE) as expected_print_days,

    -- Data completeness flag
    (COUNT(DISTINCT ds.snapshot_date) >=
        (SELECT COUNT(*) FROM publication_schedule ps3
         WHERE ps3.paper_code = ds.paper_code AND ps3.has_print = TRUE)
    ) as is_week_complete

FROM daily_snapshots ds
INNER JOIN publication_schedule ps
    ON ds.paper_code = ps.paper_code
    AND DAYOFWEEK(ds.snapshot_date) - 1 = ps.day_of_week  -- Match day of week
    AND ps.has_print = TRUE  -- Only include print publication days

WHERE ds.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)  -- Last 90 days

GROUP BY
    week_start_date,
    week_label,
    ds.paper_code,
    ds.paper_name,
    ds.business_unit

ORDER BY
    week_start_date DESC,
    ds.business_unit,
    ds.paper_name;

-- Verify view creation
SELECT 'weekly_summary view created successfully' AS status;

-- Test the view (will return 0 rows until we have print day data)
SELECT
    week_label,
    paper_code,
    print_days_reported,
    expected_print_days,
    is_week_complete,
    avg_total_active
FROM weekly_summary
ORDER BY week_start_date DESC, paper_code
LIMIT 10;
