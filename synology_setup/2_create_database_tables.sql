-- Day 1: Create Database Tables for Circulation Dashboard
-- Run this in phpMyAdmin SQL tab

-- Select the database
USE circulation_dashboard;

-- Table 1: Daily snapshots by paper
CREATE TABLE daily_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    paper_name VARCHAR(100),
    business_unit VARCHAR(50),
    total_active INT NOT NULL DEFAULT 0,
    on_vacation INT NOT NULL DEFAULT 0,
    deliverable INT NOT NULL DEFAULT 0,
    mail_delivery INT NOT NULL DEFAULT 0,
    carrier_delivery INT NOT NULL DEFAULT 0,
    digital_only INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Ensure one record per paper per day
    UNIQUE KEY unique_daily_snapshot (snapshot_date, paper_code),

    -- Indexes for fast queries
    INDEX idx_date (snapshot_date),
    INDEX idx_paper (paper_code),
    INDEX idx_business_unit (business_unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily circulation snapshots by paper';

-- Table 2: Vacation statistics
CREATE TABLE vacation_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    active_vacations INT NOT NULL DEFAULT 0,
    scheduled_next_7days INT NOT NULL DEFAULT 0,
    scheduled_next_30days INT NOT NULL DEFAULT 0,
    returning_this_week INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_vac_snapshot (snapshot_date, paper_code),
    INDEX idx_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily vacation statistics';

-- Table 3: Rate package distribution (top 10 per paper)
CREATE TABLE rate_distribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    rate_id INT NOT NULL,
    rate_description TEXT,
    subscriber_count INT NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2),
    rank_position INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_date_paper (snapshot_date, paper_code),
    INDEX idx_rate (rate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Top subscription packages by paper';

-- Table 4: Dashboard users (for authentication)
CREATE TABLE dashboard_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'viewer',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Dashboard user accounts';

-- Insert default admin user (password: admin123 - CHANGE THIS!)
-- Password hash for 'admin123' using PHP password_hash()
INSERT INTO dashboard_users (username, password_hash, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Table 5: Data import log (track daily imports)
CREATE TABLE import_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_type VARCHAR(50),
    file_name VARCHAR(255),
    records_processed INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'success',
    error_message TEXT,

    INDEX idx_date (import_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Data import audit trail';

-- Verify tables were created
SHOW TABLES;

-- Check table structure
DESCRIBE daily_snapshots;
DESCRIBE vacation_snapshots;
DESCRIBE rate_distribution;
DESCRIBE dashboard_users;
DESCRIBE import_log;

-- Test insert (will be deleted)
INSERT INTO daily_snapshots
(snapshot_date, paper_code, paper_name, business_unit, total_active, deliverable)
VALUES
(CURDATE(), 'TEST', 'Test Paper', 'Test Unit', 100, 100);

-- Verify insert worked
SELECT * FROM daily_snapshots WHERE paper_code = 'TEST';

-- Clean up test record
DELETE FROM daily_snapshots WHERE paper_code = 'TEST';

-- All done!
SELECT 'Database tables created successfully!' AS status;
