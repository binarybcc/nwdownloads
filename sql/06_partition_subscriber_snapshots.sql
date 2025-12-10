-- Restructure subscriber_snapshots with partitioning
-- This creates a partitioned version optimized for temporal queries

DROP TABLE IF EXISTS subscriber_snapshots;

CREATE TABLE subscriber_snapshots (
    id BIGINT AUTO_INCREMENT,
    upload_id BIGINT NULL COMMENT 'Reference to raw_uploads table',
    snapshot_date DATE NOT NULL,
    week_num INT NULL COMMENT 'ISO week number (1-53)',
    year INT NULL COMMENT 'Year for the week',
    import_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Subscriber identification
    sub_num VARCHAR(50) NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    paper_name VARCHAR(100) NOT NULL,
    business_unit VARCHAR(50) NOT NULL,

    -- Subscriber details
    name VARCHAR(200) NULL,
    route VARCHAR(100) NULL,
    address VARCHAR(255) NULL,
    city_state_postal VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,

    -- Subscription details
    rate_name VARCHAR(50) NULL,
    subscription_length VARCHAR(20) NULL,
    delivery_type VARCHAR(20) NULL,
    payment_status VARCHAR(10) NULL,
    begin_date DATE NULL,
    paid_thru DATE NULL,
    daily_rate DECIMAL(10,5) NULL,
    last_payment_amount DECIMAL(10,2) NULL,

    -- Vacation tracking
    on_vacation TINYINT(1) DEFAULT 0,
    vacation_start DATE NULL COMMENT 'Vacation start date from Newzware',
    vacation_end DATE NULL COMMENT 'Vacation end/return date from Newzware',
    vacation_weeks DECIMAL(5,1) NULL COMMENT 'Calculated weeks on vacation (calendar weeks)',

    -- Digital engagement
    abc VARCHAR(10) NULL,
    issue_code VARCHAR(10) NULL,
    login_id VARCHAR(50) NULL,
    last_login DATE NULL,

    -- Import metadata
    source_filename VARCHAR(255) NULL COMMENT 'Original CSV filename',
    source_date DATE NULL COMMENT 'Date from filename',
    is_backfilled TINYINT(1) DEFAULT 0 COMMENT '1 if backfilled',
    backfill_weeks INT NULL COMMENT 'Weeks backfilled',

    -- PRIMARY KEY includes snapshot_date for partitioning
    PRIMARY KEY (id, snapshot_date),

    -- Unique constraint prevents duplicate subscribers per snapshot
    UNIQUE KEY unique_snapshot_subscriber (snapshot_date, sub_num, paper_code),

    -- Performance indexes
    INDEX idx_upload_id (upload_id),
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_snapshot_sub (snapshot_date, sub_num),
    INDEX idx_sub_num (sub_num),
    INDEX idx_paper_code (paper_code),
    INDEX idx_business_unit (business_unit),
    INDEX idx_rate_name (rate_name),
    INDEX idx_paid_thru (paid_thru),
    INDEX idx_last_payment (last_payment_amount),
    INDEX idx_delivery_type (delivery_type),
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_week (week_num, year),
    INDEX idx_vacation_dates (vacation_start, vacation_end),
    INDEX idx_vacation_weeks (vacation_weeks),
    INDEX idx_revenue_query (snapshot_date, paid_thru, last_payment_amount, delivery_type)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Partitioned subscriber snapshots - optimized for temporal queries'

-- Partition by snapshot_date using RANGE
-- Creates monthly partitions for efficient queries on recent data
PARTITION BY RANGE (TO_DAYS(snapshot_date)) (
    -- 2025 partitions
    PARTITION p2025_11 VALUES LESS THAN (TO_DAYS('2025-12-01')) COMMENT 'November 2025',
    PARTITION p2025_12 VALUES LESS THAN (TO_DAYS('2026-01-01')) COMMENT 'December 2025',

    -- 2026 partitions (full year)
    PARTITION p2026_01 VALUES LESS THAN (TO_DAYS('2026-02-01')) COMMENT 'January 2026',
    PARTITION p2026_02 VALUES LESS THAN (TO_DAYS('2026-03-01')) COMMENT 'February 2026',
    PARTITION p2026_03 VALUES LESS THAN (TO_DAYS('2026-04-01')) COMMENT 'March 2026',
    PARTITION p2026_04 VALUES LESS THAN (TO_DAYS('2026-05-01')) COMMENT 'April 2026',
    PARTITION p2026_05 VALUES LESS THAN (TO_DAYS('2026-06-01')) COMMENT 'May 2026',
    PARTITION p2026_06 VALUES LESS THAN (TO_DAYS('2026-07-01')) COMMENT 'June 2026',
    PARTITION p2026_07 VALUES LESS THAN (TO_DAYS('2026-08-01')) COMMENT 'July 2026',
    PARTITION p2026_08 VALUES LESS THAN (TO_DAYS('2026-09-01')) COMMENT 'August 2026',
    PARTITION p2026_09 VALUES LESS THAN (TO_DAYS('2026-10-01')) COMMENT 'September 2026',
    PARTITION p2026_10 VALUES LESS THAN (TO_DAYS('2026-11-01')) COMMENT 'October 2026',
    PARTITION p2026_11 VALUES LESS THAN (TO_DAYS('2026-12-01')) COMMENT 'November 2026',
    PARTITION p2026_12 VALUES LESS THAN (TO_DAYS('2027-01-01')) COMMENT 'December 2026',

    -- Future catch-all partition
    PARTITION p_future VALUES LESS THAN MAXVALUE COMMENT 'Future data'
);
