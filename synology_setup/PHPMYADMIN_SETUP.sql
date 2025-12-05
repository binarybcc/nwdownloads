-- ============================================================================
-- Circulation Dashboard - Complete Database Setup
-- Run this SQL in PhpMyAdmin after installation
-- ============================================================================

-- Step 1: Create Database
-- ============================================================================
CREATE DATABASE IF NOT EXISTS circulation_dashboard
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

-- Step 2: Create User
-- ============================================================================
CREATE USER IF NOT EXISTS 'circ_dash'@'localhost'
IDENTIFIED BY 'Barnaby358Jones';

-- Step 3: Grant Privileges
-- ============================================================================
GRANT ALL PRIVILEGES ON circulation_dashboard.*
TO 'circ_dash'@'localhost';

FLUSH PRIVILEGES;

-- Step 4: Switch to the New Database
-- ============================================================================
USE circulation_dashboard;

-- Step 5: Create Tables
-- ============================================================================

-- Table 1: Daily Snapshots by Paper
CREATE TABLE IF NOT EXISTS daily_snapshots (
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
    UNIQUE KEY unique_daily_snapshot (snapshot_date, paper_code),
    INDEX idx_date (snapshot_date),
    INDEX idx_paper (paper_code),
    INDEX idx_business_unit (business_unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 2: Vacation Statistics
CREATE TABLE IF NOT EXISTS vacation_snapshots (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 3: Rate Package Distribution
CREATE TABLE IF NOT EXISTS rate_distribution (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 4: Dashboard Users
CREATE TABLE IF NOT EXISTS dashboard_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'viewer',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 5: Import Log
CREATE TABLE IF NOT EXISTS import_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_type VARCHAR(50),
    file_name VARCHAR(255),
    records_processed INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'success',
    error_message TEXT,
    INDEX idx_date (import_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Show all databases (should see circulation_dashboard)
SHOW DATABASES LIKE 'circulation%';

-- Show all tables (should see 5 tables)
SHOW TABLES;

-- Show user created (should see circ_dash@localhost)
SELECT User, Host FROM mysql.user WHERE User = 'circ_dash';

-- Show grants for user
SHOW GRANTS FOR 'circ_dash'@'localhost';

-- ============================================================================
-- SUCCESS!
-- ============================================================================
-- Database: circulation_dashboard
-- User: circ_dash
-- Password: Barnaby358Jones
-- Tables: 5 (daily_snapshots, vacation_snapshots, rate_distribution,
--            dashboard_users, import_log)
-- ============================================================================
