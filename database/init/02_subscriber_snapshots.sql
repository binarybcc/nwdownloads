-- Create subscriber_snapshots table for weekly subscriber-level data
-- This runs automatically when database container is first initialized

CREATE TABLE IF NOT EXISTS subscriber_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL COMMENT 'Date of weekly snapshot',
    import_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was imported',
    sub_num VARCHAR(50) NOT NULL COMMENT 'Subscriber number from Newzware',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Paper code (TJ, TA, TR, LJ, WRN, FN)',
    paper_name VARCHAR(100) NOT NULL COMMENT 'Full paper name',
    business_unit VARCHAR(50) NOT NULL COMMENT 'Business unit',
    name VARCHAR(200) COMMENT 'Subscriber name',
    route VARCHAR(100) COMMENT 'Delivery route',
    rate_name VARCHAR(50) COMMENT 'Zone column - Rate plan name',
    subscription_length VARCHAR(20) COMMENT 'LEN column - Term length',
    delivery_type VARCHAR(20) COMMENT 'DEL column - MAIL, CARR, INTE',
    payment_status VARCHAR(10) COMMENT 'PAY column - COMP or PAY',
    begin_date DATE COMMENT 'Subscription start date',
    paid_thru DATE COMMENT 'Subscription expiration date',
    daily_rate DECIMAL(10,5) COMMENT 'Daily rate charged',
    on_vacation BOOLEAN DEFAULT FALSE COMMENT 'Currently on vacation hold',
    address VARCHAR(255) COMMENT 'Street address',
    city_state_postal VARCHAR(150) COMMENT 'City, State, Postal code',
    phone VARCHAR(50) COMMENT 'Phone number',
    email VARCHAR(255) COMMENT 'Email address',
    abc VARCHAR(50) COMMENT 'ABC column from Newzware',
    issue_code VARCHAR(50) COMMENT 'ISS column from Newzware',
    last_payment_amount DECIMAL(10,2) COMMENT 'Last payment amount',
    login_id VARCHAR(100) COMMENT 'Digital login ID',
    last_login DATETIME COMMENT 'Last digital login date/time',
    
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
    INDEX idx_snapshot_paper_rate (snapshot_date, paper_code, rate_name),
    INDEX idx_snapshot_paper_length (snapshot_date, paper_code, subscription_length),
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    
    -- CRITICAL: Unique constraint for historical snapshots and UPSERT
    UNIQUE KEY unique_snapshot_subscriber (snapshot_date, sub_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
COMMENT='Weekly subscriber-level snapshots for trend analysis and detailed breakdowns';
