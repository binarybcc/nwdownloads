-- Fix duplicate subscriber records issue
-- Date: 2025-12-05
-- Problem: Multiple uploads create duplicate rows for same subscriber on same snapshot_date
-- Solution: Add unique constraint and clean up duplicates

USE circulation_dashboard;

-- Step 1: Remove duplicate records, keeping only the most complete record
-- (Keep rows with non-NULL contact info over NULL contact info)

DELETE s1 FROM subscriber_snapshots s1
INNER JOIN subscriber_snapshots s2
WHERE s1.id < s2.id
  AND s1.snapshot_date = s2.snapshot_date
  AND s1.sub_num = s2.sub_num
  AND s1.paper_code = s2.paper_code
  AND (
    -- Keep s2 if it has more complete data
    (s2.phone IS NOT NULL AND s1.phone IS NULL) OR
    (s2.email IS NOT NULL AND s1.email IS NULL) OR
    (s2.address IS NOT NULL AND s1.address IS NULL) OR
    -- Or if they're equally complete, just keep the newer one (higher ID)
    (COALESCE(s1.phone, '') = COALESCE(s2.phone, '') AND
     COALESCE(s1.email, '') = COALESCE(s2.email, '') AND
     COALESCE(s1.address, '') = COALESCE(s2.address, ''))
  );

-- Step 2: For any remaining exact duplicates, keep only the highest ID
DELETE s1 FROM subscriber_snapshots s1
INNER JOIN (
    SELECT MAX(id) as keep_id, snapshot_date, sub_num, paper_code
    FROM subscriber_snapshots
    GROUP BY snapshot_date, sub_num, paper_code
    HAVING COUNT(*) > 1
) s2 ON s1.snapshot_date = s2.snapshot_date
    AND s1.sub_num = s2.sub_num
    AND s1.paper_code = s2.paper_code
    AND s1.id < s2.keep_id;

-- Step 3: Add unique constraint to prevent future duplicates
ALTER TABLE subscriber_snapshots
ADD UNIQUE KEY unique_snapshot_subscriber (snapshot_date, sub_num, paper_code);

-- Verify cleanup
SELECT
    'Duplicate cleanup complete' AS status,
    COUNT(*) AS total_records,
    COUNT(DISTINCT CONCAT(snapshot_date, '-', sub_num, '-', paper_code)) AS unique_combinations
FROM subscriber_snapshots;

-- Show any remaining duplicates (should be zero)
SELECT snapshot_date, sub_num, paper_code, COUNT(*) as count
FROM subscriber_snapshots
GROUP BY snapshot_date, sub_num, paper_code
HAVING count > 1;
