-- ============================================
-- Churn Tracking Tables
-- Migration: 005
-- Created: 2025-12-15
-- Purpose: Track subscriber renewal/expiration events
-- ============================================

-- Individual renewal/expiration events from Newzware churn reports
CREATE TABLE IF NOT EXISTS renewal_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- Source tracking
    upload_id BIGINT COMMENT 'Link to raw_uploads table (future)',
    source_filename VARCHAR(255) COMMENT 'Original CSV filename',
    
    -- Event identification
    event_date DATE NOT NULL COMMENT 'Issue date when subscription expired/renewed',
    sub_num VARCHAR(50) NOT NULL COMMENT 'Subscriber number',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code (TJ, TA, TR, LJ, WRN)',
    
    -- Renewal status
    status ENUM('RENEW', 'EXPIRE') NOT NULL COMMENT 'Whether subscription renewed or expired',
    
    -- Subscription type at time of renewal
    subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY') NOT NULL COMMENT 'Type of subscription',
    
    -- Import metadata
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Performance indexes
    INDEX idx_event_date (event_date),
    INDEX idx_sub_paper (sub_num, paper_code),
    INDEX idx_paper_date (paper_code, event_date),
    INDEX idx_status (status),
    INDEX idx_type (subscription_type),
    INDEX idx_upload (upload_id),
    INDEX idx_paper_type_date (paper_code, subscription_type, event_date),
    
    -- Prevent duplicate renewals for same subscriber on same date
    -- Note: Same subscriber CAN appear on different dates (monthly billing)
    UNIQUE KEY unique_renewal_event (sub_num, paper_code, event_date)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Individual renewal/expiration events from Newzware churn reports';


-- Daily aggregated churn metrics by publication and subscription type
CREATE TABLE IF NOT EXISTS churn_daily_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Dimensions
    snapshot_date DATE NOT NULL COMMENT 'Date of the churn calculation',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Publication code',
    subscription_type ENUM('REGULAR', 'MONTHLY', 'COMPLIMENTARY', 'ALL') NOT NULL COMMENT 'Type of subscription (or ALL for totals)',
    
    -- Metrics
    expiring_count INT NOT NULL DEFAULT 0 COMMENT 'Number of subscriptions expiring',
    renewed_count INT NOT NULL DEFAULT 0 COMMENT 'Number that renewed',
    stopped_count INT NOT NULL DEFAULT 0 COMMENT 'Number that stopped',
    
    -- Calculated rates (percentages)
    renewal_rate DECIMAL(5,2) COMMENT 'Percentage that renewed (0-100)',
    churn_rate DECIMAL(5,2) COMMENT 'Percentage that stopped (0-100)',
    
    -- Metadata
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Performance indexes
    UNIQUE KEY unique_daily_churn (snapshot_date, paper_code, subscription_type),
    INDEX idx_date (snapshot_date),
    INDEX idx_paper (paper_code),
    INDEX idx_type (subscription_type),
    INDEX idx_paper_date (paper_code, snapshot_date)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Daily aggregated churn metrics by publication and subscription type';


-- Verification
SELECT 'Churn tracking tables created successfully' AS status;

-- Show table structures
SHOW CREATE TABLE renewal_events;
SHOW CREATE TABLE churn_daily_summary;
