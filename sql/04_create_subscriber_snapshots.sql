-- Create subscriber_snapshots table for weekly subscriber-level data
-- Purpose: Track individual subscribers over time for trend analysis
-- Date: 2025-12-02

CREATE TABLE IF NOT EXISTS subscriber_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    -- Snapshot metadata
    snapshot_date DATE NOT NULL COMMENT 'Date of weekly snapshot',
    import_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was imported',

    -- Subscriber identification
    sub_num VARCHAR(50) NOT NULL COMMENT 'Subscriber number from Newzware',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Paper code (TJ, TA, TR, LJ, WRN, FN)',
    paper_name VARCHAR(100) NOT NULL COMMENT 'Full paper name',
    business_unit VARCHAR(50) NOT NULL COMMENT 'Business unit',

    -- Subscriber details
    name VARCHAR(200) COMMENT 'Subscriber name',
    route VARCHAR(100) COMMENT 'Delivery route',

    -- Subscription characteristics (what we want to track over time)
    rate_name VARCHAR(50) COMMENT 'Zone column - Rate plan name (DIGOnly, Comp, Senior, Mail, etc.)',
    subscription_length VARCHAR(20) COMMENT 'LEN column - Term length (1 M, 3 M, 6 M, 12 M, etc.)',
    delivery_type VARCHAR(20) COMMENT 'DEL column - MAIL, CARR, INTE',
    payment_status VARCHAR(10) COMMENT 'PAY column - COMP or PAY',

    -- Important dates
    begin_date DATE COMMENT 'Subscription start date',
    paid_thru DATE COMMENT 'Subscription expiration date',

    -- Financial
    daily_rate DECIMAL(10,5) COMMENT 'Daily rate charged',

    -- Status flags
    on_vacation BOOLEAN DEFAULT FALSE COMMENT 'Currently on vacation hold',

    -- Performance indexes
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_snapshot_sub (snapshot_date, sub_num),
    INDEX idx_sub_num (sub_num),
    INDEX idx_paper_code (paper_code),
    INDEX idx_business_unit (business_unit),
    INDEX idx_rate_name (rate_name),
    INDEX idx_subscription_length (subscription_length),
    INDEX idx_paid_thru (paid_thru),
    INDEX idx_payment_status (payment_status),

    -- Composite indexes for common queries
    INDEX idx_snapshot_paper_rate (snapshot_date, paper_code, rate_name),
    INDEX idx_snapshot_paper_length (snapshot_date, paper_code, subscription_length)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Weekly subscriber-level snapshots for trend analysis and detailed breakdowns';

-- Verify table creation
SELECT 'subscriber_snapshots table created successfully' AS status;
