-- Migration: Add phone_normalized to subscriber_snapshots
-- Created: 2026-03-20
-- Purpose: Bare 10-digit phone column for JOIN against call_logs.
--          Backfills existing rows using REGEXP_REPLACE (MariaDB 10.0+).

-- Step 1: Add column + index
ALTER TABLE subscriber_snapshots
    ADD COLUMN phone_normalized CHAR(10) DEFAULT NULL
        COMMENT 'Bare 10-digit phone, no punctuation, for call log matching'
    AFTER phone,
    ADD INDEX idx_phone_normalized (phone_normalized);

-- Step 2: Backfill existing rows
-- Production data: 7107 of 7119 unique phones already 10-digit bare.
-- Matches PHP normalizePhone() logic: 10-digit as-is, 11-digit strip leading '1', else NULL.
UPDATE subscriber_snapshots
SET phone_normalized = CASE
    WHEN LENGTH(REGEXP_REPLACE(phone, '[^0-9]', '')) = 10
        THEN REGEXP_REPLACE(phone, '[^0-9]', '')
    WHEN LENGTH(REGEXP_REPLACE(phone, '[^0-9]', '')) = 11
         AND LEFT(REGEXP_REPLACE(phone, '[^0-9]', ''), 1) = '1'
        THEN RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10)
    ELSE NULL
END
WHERE phone IS NOT NULL
  AND phone != '';

-- Rollback (for reference):
-- ALTER TABLE subscriber_snapshots DROP INDEX idx_phone_normalized, DROP COLUMN phone_normalized;
