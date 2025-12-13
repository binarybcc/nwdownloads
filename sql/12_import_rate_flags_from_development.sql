-- ================================================================
-- Import Rate Flags from Development
-- ================================================================
-- Created: 2025-12-13
-- Purpose: Import rate classifications made in development environment
--
-- IMPORTANT: Only run this AFTER running 11_production_migration_revenue_intelligence.sql
--
-- DEPLOYMENT INSTRUCTIONS:
-- 1. Verify rate_flags table exists in production
-- 2. Run this script on production:
--    mysql -u root -p -S /run/mysqld/mysqld10.sock circulation_dashboard < 12_import_rate_flags_from_development.sql
-- 3. Verify import: SELECT COUNT(*) FROM rate_flags;
-- 4. Check classifications: SELECT paper_code, COUNT(*) as flagged FROM rate_flags GROUP BY paper_code;
-- ================================================================

-- Import 36 rate classifications from development
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TA', 'MAIL', 'THE ADVERTISER 3 MONTH', '3 M', 14.95, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CD01', '2 days mail delivery with full digital access', '12 M', 159.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CD01', 'THE JOURNAL 6 MONTH', '6 M', 84.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CD01', 'THE JOURNAL 1 YEAR', '1 Y', 159.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CD02', 'THE JOURNAL 1 YEAR', '1 Y', 154.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CD04', 'THE JOURNAL 1 YEAR', '1 Y', 154.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CD14', 'THE JOURNAL 1 MONTH AUTO RENEW', '1 M', 15.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CD15', 'THE JOURNAL 1 MONTH', '1 M', 8.25, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CITYGOV', 'THE JOURNAL 12 MONTH', '12 M', 129.99, 1, 1, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CR0099', 'THE JOURNAL 1 MONTH AUTO RENEW', '1 M', 19.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CR0099', 'THE JOURNAL 6 MONTH', '6 M', 99.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'CR0099', 'THE JOURNAL 12 MONTH', '12 M', 189.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'DIGI1199', 'not for public', '1 M', 11.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'DIGI129', '2025 12M Fallback Digital Rate', '12 M', 129.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'DIGI99', 'NOT FOR PUBLIC', '12 M', 99.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'DIGI999', 'not for public', '1 M', 9.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'FB12169', 'NOT FOR PUBLIC', '12 M', 169.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'FB12M149', 'NOT FOR PUBLIC', '12 M', 149.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'FB1M999', '2025 fallback Rate $9.99', '1 M', 9.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'FB6M89', 'NOT FOR PUBLIC', '6 M', 89.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'FB6M99', 'NOT FOR PUBLIC', '6 M', 99.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'FD12M169', 'NOT FOR PUBLIC', '12 M', 169.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'HAL406M', 'NOT FOR ONLINE', '6 M', 40.00, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'KK2DAY', 'Not for Public', '6 M', 69.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'KK2DAY', 'Not for Public', '12 M', 129.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'KK2DAY', 'NOT FOR PUBLIC', '1 M', 11.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'KK5DAY', 'NOT FOR PUBLIC', '1 M', 15.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'KK5DAY', '12M Keowee Key Home Delivery with Digital Accses', '12 M', 169.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'MAIL', '1 Year Special - TJ', '52 W', 99.00, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'MAIL99', 'THE JOURNAL 12 MONTH', '12 M', 249.99, 1, 1, 1, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'PO06', 'THE JOURNAL 1 YEAR', '1 Y', 229.99, 0, 1, 1, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'PRNTONLY', 'NOT FOR PUBLIC - 6M 2 DAY MAIL', '6 M', 69.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'PRNTONLY', 'NOT FOR PUBLIC - 12M 2 DAY MAIL', '12 M', 129.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'PRNTONLY', 'NOT FOR PUBLIC - 1M 2 DAY MAIL', '1 M', 11.99, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'RN0099', 'The Journal 1 Year', '1 Y', 99.00, 1, 0, 0, NULL);
INSERT INTO rate_flags (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special, notes) VALUES ('TJ', 'RN0099', 'THE JOURNAL 12 MONTH', '12 M', 169.99, 1, 0, 0, NULL);

-- Verification
SELECT
    paper_code,
    COUNT(*) as total_flags,
    SUM(is_legacy) as legacy_count,
    SUM(is_special) as special_count,
    SUM(is_ignored) as ignored_count
FROM rate_flags
GROUP BY paper_code
ORDER BY paper_code;

-- Expected output:
-- TA: 1 flag (1 legacy)
-- TJ: 35 flags (mix of legacy/special/ignored)
