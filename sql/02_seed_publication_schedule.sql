-- Phase 1A: Seed publication_schedule with paper schedules
-- Date: 2025-12-01

-- Clear any existing data (for re-runs)
DELETE FROM publication_schedule;

-- The Journal (TJ): Print Wed/Sat, Digital Tue-Sat
INSERT INTO publication_schedule (paper_code, day_of_week, has_print, has_digital) VALUES
('TJ', 2, FALSE, TRUE),   -- Tuesday: Digital only
('TJ', 3, TRUE, TRUE),    -- Wednesday: Print + Digital
('TJ', 4, FALSE, TRUE),   -- Thursday: Digital only
('TJ', 5, FALSE, TRUE),   -- Friday: Digital only
('TJ', 6, TRUE, TRUE);    -- Saturday: Print + Digital

-- The Advertiser (TA): Print Wed only, no digital
INSERT INTO publication_schedule (paper_code, day_of_week, has_print, has_digital) VALUES
('TA', 3, TRUE, FALSE);   -- Wednesday: Print only

-- The Ranger (TR): Print Wed/Sat, Digital on print days
INSERT INTO publication_schedule (paper_code, day_of_week, has_print, has_digital) VALUES
('TR', 3, TRUE, TRUE),    -- Wednesday: Print + Digital
('TR', 6, TRUE, TRUE);    -- Saturday: Print + Digital

-- The Lander Journal (LJ): Print Wed/Sat, Digital on print days
INSERT INTO publication_schedule (paper_code, day_of_week, has_print, has_digital) VALUES
('LJ', 3, TRUE, TRUE),    -- Wednesday: Print + Digital
('LJ', 6, TRUE, TRUE);    -- Saturday: Print + Digital

-- Wind River News (WRN): Print Thu only, Digital on print days
INSERT INTO publication_schedule (paper_code, day_of_week, has_print, has_digital) VALUES
('WRN', 4, TRUE, TRUE);   -- Thursday: Print + Digital

-- Verify data insertion
SELECT
    paper_code,
    COUNT(*) AS total_days,
    SUM(has_print) AS print_days,
    SUM(has_digital) AS digital_days
FROM publication_schedule
GROUP BY paper_code
ORDER BY paper_code;

-- Expected output:
-- TJ: 5 total_days, 2 print_days, 5 digital_days
-- TA: 1 total_days, 1 print_days, 0 digital_days
-- TR: 2 total_days, 2 print_days, 2 digital_days
-- LJ: 2 total_days, 2 print_days, 2 digital_days
-- WRN: 1 total_days, 1 print_days, 1 digital_days

-- Show print days in human-readable format
SELECT
    paper_code,
    GROUP_CONCAT(
        CASE day_of_week
            WHEN 0 THEN 'Sun'
            WHEN 1 THEN 'Mon'
            WHEN 2 THEN 'Tue'
            WHEN 3 THEN 'Wed'
            WHEN 4 THEN 'Thu'
            WHEN 5 THEN 'Fri'
            WHEN 6 THEN 'Sat'
        END
        ORDER BY day_of_week SEPARATOR ', '
    ) AS print_days
FROM publication_schedule
WHERE has_print = TRUE
GROUP BY paper_code
ORDER BY paper_code;

-- Expected output:
-- TJ: Wed, Sat
-- TA: Wed
-- TR: Wed, Sat
-- LJ: Wed, Sat
-- WRN: Thu

SELECT 'publication_schedule seeded successfully - 13 rows' AS status;
