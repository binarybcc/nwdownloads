-- Migration: 008_create_notification_settings.sql
-- Description: Creates notification_settings table for email alerts and dashboard preferences
-- Date: 2025-12-17
--
-- Table:
--   - notification_settings: System-wide notification configuration
--
-- Usage:
--   cat database/migrations/008_create_notification_settings.sql | \
--     docker exec -i circulation_db mariadb -uroot -prootpass circulation_dashboard

-- ============================================================================
-- Drop existing table (for clean migration)
-- ============================================================================

DROP TABLE IF EXISTS notification_settings;

-- ============================================================================
-- notification_settings - Notification Configuration
-- ============================================================================

CREATE TABLE notification_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE COMMENT 'Setting identifier (email_addresses, enable_failure_emails, enable_success_dashboard, etc.)',
    setting_value TEXT NULL COMMENT 'Setting value (JSON array for email lists, boolean for flags)',
    description VARCHAR(255) NULL COMMENT 'Human-readable description of this setting',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification time',

    -- Index for fast lookups
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Notification system configuration and preferences';

-- ============================================================================
-- Default Settings
-- ============================================================================

INSERT INTO notification_settings (setting_key, setting_value, description) VALUES
('email_addresses', '["john@upstatetoday.com"]', 'Comma-separated list of email addresses for failure notifications (JSON array)'),
('enable_failure_emails', 'true', 'Send email notifications when file processing fails'),
('enable_success_dashboard', 'true', 'Show success banners on dashboard after successful processing'),
('from_email', 'noreply@upstatetoday.com', 'From address for outgoing notification emails'),
('from_name', 'Circulation Dashboard', 'From name for outgoing notification emails');

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Show table structure
SHOW CREATE TABLE notification_settings\G

-- Show default settings
SELECT setting_key, setting_value, description FROM notification_settings ORDER BY setting_key;
