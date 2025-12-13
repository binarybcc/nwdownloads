-- ================================================================
-- Production Migration: Revenue Intelligence Feature
-- ================================================================
-- Created: 2025-12-13
-- Purpose: Add tables for revenue opportunity tracking and rate management
--
-- DEPLOYMENT INSTRUCTIONS:
-- 1. Backup production database first!
-- 2. Run this script on production:
--    mysql -u root -p -S /run/mysqld/mysqld10.sock circulation_dashboard < 11_production_migration_revenue_intelligence.sql
-- 3. Verify tables created: SHOW TABLES LIKE 'rate_%';
-- 4. Import rate_flags data if you classified rates in development (see separate export file)
-- 5. Clear cache: rm -rf /tmp/dashboard_cache/*.cache
-- ================================================================

-- ----------------------------------------------------------------
-- Table 1: rate_structure
-- ----------------------------------------------------------------
-- Purpose: Market rate lookup table for legacy rate gap analysis
-- Source: Populated from rates.csv (23 rows)
-- Used by: web/api/revenue_intelligence.php

DROP TABLE IF EXISTS rate_structure;

CREATE TABLE rate_structure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paper_code VARCHAR(10) NOT NULL,
    subscription_length VARCHAR(20) NOT NULL,
    market_rate DECIMAL(10,2) NOT NULL,
    rate_name VARCHAR(255) NULL,
    annualized_rate DECIMAL(10,2) NOT NULL COMMENT 'Rate normalized to annual for comparison',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_paper_length (paper_code, subscription_length),
    INDEX idx_paper_code (paper_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Market rate lookup table for legacy rate gap analysis';

-- Insert market rates (from rates.csv)
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate) VALUES
('LJ', '1 M', 6.50, 'LANDER JOURNAL 1 MONTH AUTO RENEW', 78.00),
('LJ', '1 Y', 65.00, 'LANDER JOURNAL 1 YEAR', 65.00),
('LJ', '52 W', 65.00, 'LJ DIGITAL', 65.00),
('LJ', '6 M', 34.00, 'LANDER JOURNAL 6 MONTH', 68.00),
('TA', '1 D', 5.00, 'THE ADVERTISER 1 DAY AUTO RENEW', 1825.00),
('TA', '1 M', 7.95, 'THE ADVERTISER 1 MONTH', 95.40),
('TA', '12 M', 62.00, '12 Months Mail Delivery--Outside of Tuscola County', 62.00),
('TA', '3 M', 24.00, 'THE ADVERTISER 3 MONTH', 96.00),
('TA', '6 M', 39.00, 'THE ADVERTISER 6 MONTH', 78.00),
('TJ', '1 M', 19.99, 'THE JOURNAL 1 MONTH AUTO RENEW', 239.88),
('TJ', '1 W', 6.29, 'ONE Week PURCHASE', 327.08),
('TJ', '1 Y', 229.99, 'THE JOURNAL 1 YEAR', 229.99),
('TJ', '12 M', 249.99, 'THE JOURNAL 12 MONTH', 249.99),
('TJ', '2 M', 18.20, 'THE JOURNAL 2 MONTH', 109.20),
('TJ', '3 M', 65.99, 'OUT OF COUNTY MAIL, 3 MONTHS FOR $65.99', 263.96),
('TJ', '52 W', 99.00, '1 Year Special - TJ', 99.00),
('TJ', '6 M', 229.99, 'THE JOURNAL 6 MONTH', 459.98),
('TR', '1 M', 6.50, 'THE RANGER 1 MONTH AUTO RENEW', 78.00),
('TR', '1 Y', 65.00, 'THE RANGER 1 YEAR', 65.00),
('TR', '12 M', 48.75, '25% OFF JAN 2025 PROMO', 48.75),
('TR', '52 W', 65.00, 'THE RANGER 52 WEEK', 65.00),
('TR', '6 M', 34.00, 'THE RANGER 6 MONTH', 68.00),
('WRN', '1 Y', 35.00, 'WIND RIVER NEWS 1 YEAR', 35.00);

-- Verification query
SELECT
    paper_code,
    COUNT(*) as rate_count,
    MIN(annualized_rate) as min_rate,
    MAX(annualized_rate) as max_rate
FROM rate_structure
GROUP BY paper_code
ORDER BY paper_code;

-- Expected output:
-- LJ: 4 rates ($65-$78/year)
-- TA: 5 rates ($62-$1825/year - includes daily auto-renew)
-- TJ: 8 rates ($99-$460/year)
-- TR: 5 rates ($48.75-$78/year)
-- WRN: 1 rate ($35/year)

-- ----------------------------------------------------------------
-- Table 2: rate_flags
-- ----------------------------------------------------------------
-- Purpose: User-controlled rate classification for legacy detection
-- Source: Empty on initial creation, populated via web/rates.php page
-- Used by: web/rates.php and web/api/revenue_intelligence.php

DROP TABLE IF EXISTS rate_flags;

CREATE TABLE rate_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paper_code VARCHAR(10) NOT NULL,
    zone VARCHAR(50) NOT NULL,
    rate_name VARCHAR(255) NOT NULL,
    subscription_length VARCHAR(20) NOT NULL,
    rate_amount DECIMAL(10,2) NOT NULL,
    is_legacy TINYINT(1) DEFAULT 0 COMMENT 'User or auto-marked as legacy',
    is_ignored TINYINT(1) DEFAULT 0 COMMENT 'User marked to ignore in calculations',
    is_special TINYINT(1) DEFAULT 0 COMMENT 'User marked as special rate (legitimate, excluded from opportunities)',
    auto_detected_legacy TINYINT(1) DEFAULT 0 COMMENT 'Was auto-detected as below market rate',
    notes TEXT NULL COMMENT 'Optional user notes about why rate is flagged',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_zone_rate (paper_code, zone, subscription_length, rate_amount),
    INDEX idx_paper_zone (paper_code, zone),
    INDEX idx_flags (is_legacy, is_ignored, is_special)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='User-controlled rate classification for legacy detection';

-- Note: This table starts empty
-- Users will populate it via web/rates.php as they classify rates
-- If you have rate classifications from development, import them separately

-- ================================================================
-- DEPLOYMENT CHECKLIST
-- ================================================================
-- [ ] Database backup completed
-- [ ] SQL script executed successfully
-- [ ] rate_structure has 23 rows (verify with count query above)
-- [ ] rate_flags table created (empty initially)
-- [ ] Cache cleared: rm -rf /tmp/dashboard_cache/*.cache
-- [ ] Code deployed to /volume1/web/circulation/
-- [ ] Test revenue opportunities section at https://cdash.upstatetoday.com
-- [ ] Test rates.php page - verify rates load and checkboxes save
-- [ ] Import rate_flags data if available (see export file)
-- ================================================================
