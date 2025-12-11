-- Rate Flags Table
-- Stores user-controlled Legacy and Ignore flags for rates
-- Allows human override of algorithmic legacy detection

DROP TABLE IF EXISTS rate_flags;

CREATE TABLE rate_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paper_code VARCHAR(10) NOT NULL,
    zone VARCHAR(50) NOT NULL,
    rate_name VARCHAR(255) NOT NULL,
    subscription_length VARCHAR(20) NOT NULL,
    rate_amount DECIMAL(10,2) NOT NULL,
    is_legacy BOOLEAN DEFAULT FALSE COMMENT 'User or auto-marked as legacy',
    is_ignored BOOLEAN DEFAULT FALSE COMMENT 'User marked to ignore in calculations',
    auto_detected_legacy BOOLEAN DEFAULT FALSE COMMENT 'Was auto-detected as below market rate',
    notes TEXT NULL COMMENT 'Optional user notes about why rate is flagged',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_zone_rate (paper_code, zone, subscription_length, rate_amount),
    INDEX idx_paper_zone (paper_code, zone),
    INDEX idx_flags (is_legacy, is_ignored)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='User-controlled rate classification for legacy detection';
