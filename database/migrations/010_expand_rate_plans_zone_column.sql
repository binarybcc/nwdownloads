-- ============================================================================
-- Migration: 010_expand_rate_plans_zone_column
-- ============================================================================
-- Created: 2025-12-22
-- Purpose: Expand rate_plans.zone column from VARCHAR(4) to VARCHAR(20)
--          to accommodate longer zone codes like 'INTERNET', 'CITYGOV', etc.
--
-- Dependencies:
--   - Modifies table: rate_plans
--
-- Rollback:
--   ALTER TABLE rate_plans MODIFY COLUMN zone VARCHAR(4);
--
-- Testing:
--   - [x] Tested on development
--   - [ ] Verified idempotent (can re-run safely)
--   - [ ] Backup created before production run
-- ============================================================================

-- Expand zone column to accommodate longer values
ALTER TABLE rate_plans
MODIFY COLUMN zone VARCHAR(20) NULL;

-- Verification
SELECT 'Zone column expanded successfully' AS status;

SHOW CREATE TABLE rate_plans;

-- ============================================================================
-- Migration Complete
-- ============================================================================
