-- Market Rate Structure Table
-- Contains the maximum rate for each paper + subscription length

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

-- Insert market rates
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('LJ', '1 M', 6.5, 'LANDER JOURNAL 1 MONTH AUTO RENEW', 78.0);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('LJ', '1 Y', 65, 'LANDER JOURNAL 1 YEAR', 65);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('LJ', '52 W', 65, 'LJ DIGITAL', 65);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('LJ', '6 M', 34, 'LANDER JOURNAL 6 MONTH', 68);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TA', '1 D', 5, 'THE ADVERTISER 1 DAY AUTO RENEW', 1825);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TA', '1 M', 7.95, 'THE ADVERTISER 1 MONTH', 95.40);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TA', '12 M', 62, '12 Months Mail Delivery--Outside of Tuscola County', 62);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TA', '3 M', 24, 'THE ADVERTISER 3 MONTH', 96);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TA', '6 M', 39, 'THE ADVERTISER 6 MONTH', 78);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '1 M', 19.99, 'THE JOURNAL 1 MONTH AUTO RENEW', 239.88);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '1 W', 6.29, 'ONE Week PURCHASE', 327.08);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '1 Y', 229.99, 'THE JOURNAL 1 YEAR', 229.99);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '12 M', 249.99, 'THE JOURNAL 12 MONTH', 249.99);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '2 M', 18.2, 'THE JOURNAL 2 MONTH', 109.2);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '3 M', 65.99, 'OUT OF COUNTY MAIL, 3 MONTHS FOR $65.99', 263.96);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '52 W', 99, '1 Year Special - TJ', 99);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TJ', '6 M', 229.99, 'THE JOURNAL 6 MONTH', 459.98);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TR', '1 M', 6.5, 'THE RANGER 1 MONTH AUTO RENEW', 78.0);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TR', '1 Y', 65, 'THE RANGER 1 YEAR', 65);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TR', '12 M', 48.75, '25% OFF JAN 2025 PROMO', 48.75);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TR', '52 W', 65, 'THE RANGER 52 WEEK', 65);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('TR', '6 M', 34, 'THE RANGER 6 MONTH', 68);
INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)
VALUES ('WRN', '1 Y', 35, 'WIND RIVER NEWS 1 YEAR', 35);

-- Summary:
-- Total papers: 5
-- LJ: 4 subscription lengths
-- TA: 5 subscription lengths
-- TJ: 8 subscription lengths
-- TR: 5 subscription lengths
-- WRN: 1 subscription lengths
