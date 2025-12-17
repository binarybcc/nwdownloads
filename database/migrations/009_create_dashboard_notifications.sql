-- Migration: 009_create_dashboard_notifications.sql
-- Description: Creates dashboard_notifications table for success/failure banners
-- Date: 2025-12-17
--
-- Table:
--   - dashboard_notifications: Notifications to display on dashboard
--
-- Usage:
--   cat database/migrations/009_create_dashboard_notifications.sql | \
--     docker exec -i circulation_db mariadb -uroot -prootpass circulation_dashboard

-- ============================================================================
-- Drop existing table (for clean migration)
-- ============================================================================

DROP TABLE IF EXISTS dashboard_notifications;

-- ============================================================================
-- dashboard_notifications - Dashboard Banner Notifications
-- ============================================================================

CREATE TABLE dashboard_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_type ENUM('success', 'failure', 'info', 'warning') NOT NULL COMMENT 'Type of notification',
    message TEXT NOT NULL COMMENT 'Notification message to display',
    processing_log_id INT NULL COMMENT 'FK to file_processing_log if related to file processing',
    is_dismissed TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Has user dismissed this notification?',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',

    -- Indexes
    INDEX idx_is_dismissed (is_dismissed),
    INDEX idx_created_at (created_at),
    INDEX idx_processing_log_id (processing_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dashboard notifications and banners for user feedback';

-- ============================================================================
-- Verification Queries
-- ============================================================================

-- Show table structure
SHOW CREATE TABLE dashboard_notifications\G

-- Show statistics
SELECT COUNT(*) as total_notifications FROM dashboard_notifications;
