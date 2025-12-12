-- Simplified rate_flags schema
-- Uses zone as primary identifier since that's what subscriber_snapshots has

DROP TABLE IF EXISTS rate_flags;

CREATE TABLE rate_flags (
    zone VARCHAR(50) PRIMARY KEY,
    paper_code VARCHAR(10) NOT NULL,
    rate_name VARCHAR(255) NOT NULL,
    subscription_length VARCHAR(20) NOT NULL,
    rate_amount DECIMAL(10,2) NOT NULL,
    is_legacy BOOLEAN DEFAULT FALSE COMMENT 'User or auto-marked as legacy',
    is_ignored BOOLEAN DEFAULT FALSE COMMENT 'User marked to ignore in calculations',
    auto_detected_legacy BOOLEAN DEFAULT FALSE COMMENT 'Was auto-detected as below market rate',
    notes TEXT NULL COMMENT 'Optional user notes about why rate is flagged',
    subscriber_count INT DEFAULT 0 COMMENT 'Last known subscriber count',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_paper (paper_code),
    INDEX idx_flags (is_legacy, is_ignored)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='User-controlled rate classification for legacy detection - indexed by zone';
