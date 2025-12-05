-- Phase 1A: Create publication_schedule table
-- Purpose: Define which papers publish on which days
-- Date: 2025-12-01

CREATE TABLE IF NOT EXISTS publication_schedule (
    paper_code VARCHAR(10) NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
    has_print BOOLEAN DEFAULT FALSE COMMENT 'True if print edition publishes this day',
    has_digital BOOLEAN DEFAULT FALSE COMMENT 'True if digital content updates this day',
    PRIMARY KEY (paper_code, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Publication schedule for all papers';

-- Verify table creation
SELECT 'publication_schedule table created successfully' AS status;
