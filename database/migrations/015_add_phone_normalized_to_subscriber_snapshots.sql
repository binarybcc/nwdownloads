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
-- Strip non-digit chars, take rightmost 10 digits, only for rows with 10+ digits.
UPDATE subscriber_snapshots
SET phone_normalized = RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10)
WHERE phone IS NOT NULL
  AND phone != ''
  AND REGEXP_REPLACE(phone, '[^0-9]', '') REGEXP '^[0-9]{10,}$';

-- Rollback (for reference):
-- ALTER TABLE subscriber_snapshots DROP INDEX idx_phone_normalized, DROP COLUMN phone_normalized;
