-- Create daily_snapshots table with upsert-ready primary key
-- Purpose: Store daily circulation metrics with ability to update historical data
-- Date: 2025-12-02

CREATE TABLE IF NOT EXISTS daily_snapshots (
    snapshot_date DATE NOT NULL COMMENT 'Date of this snapshot',
    paper_code VARCHAR(10) NOT NULL COMMENT 'Paper code (TJ, TA, TR, LJ, WRN, FN)',
    paper_name VARCHAR(100) NOT NULL COMMENT 'Full paper name',
    business_unit VARCHAR(50) NOT NULL COMMENT 'Business unit (Michigan, Wyoming, South Carolina, Sold)',

    -- Subscription counts
    total_active INT NOT NULL DEFAULT 0 COMMENT 'Total active subscriptions',
    deliverable INT NOT NULL DEFAULT 0 COMMENT 'Subscriptions ready for delivery (active - vacation)',

    -- Delivery method breakdown
    mail_delivery INT NOT NULL DEFAULT 0 COMMENT 'Mail delivery subscriptions',
    carrier_delivery INT NOT NULL DEFAULT 0 COMMENT 'Carrier delivery subscriptions',
    digital_only INT NOT NULL DEFAULT 0 COMMENT 'Digital-only subscriptions',

    -- Status counts
    on_vacation INT NOT NULL DEFAULT 0 COMMENT 'Subscriptions on vacation hold',

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When record was first created',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When record was last updated',

    -- Primary key for upsert functionality
    PRIMARY KEY (snapshot_date, paper_code),

    -- Indexes for common queries
    INDEX idx_business_unit (business_unit),
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_paper_code (paper_code)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily circulation snapshots with upsert support';

-- Verify table creation
SELECT 'daily_snapshots table created successfully with upsert support' AS status;
