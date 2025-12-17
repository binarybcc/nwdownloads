<?php

/**
 * Dashboard Notifier
 *
 * Stores notifications in database for display as dashboard banners.
 * Success notifications are displayed, failure notifications are NOT (email is used instead).
 *
 * Configuration:
 * - Enable/disable controlled by enable_success_dashboard setting
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard\Notifications;

require_once __DIR__ . '/INotifier.php';
require_once __DIR__ . '/../processors/IFileProcessor.php';

use CirculationDashboard\Processors\ProcessResult;
use PDO;
use Exception;

class DashboardNotifier implements INotifier
{
    private PDO $pdo;
    private array $settings = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * Load notification settings from database
     */
    private function loadSettings(): void
    {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM notification_settings");
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    /**
     * Get setting value with fallback
     */
    private function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Check if success dashboard notifications are enabled
     */
    private function isSuccessDashboardEnabled(): bool
    {
        $enabled = $this->getSetting('enable_success_dashboard', 'true');
        return strtolower($enabled) === 'true';
    }

    /**
     * Send success notification
     *
     * Creates a dashboard banner notification for successful file processing.
     *
     * @param ProcessResult $result Processing result
     */
    public function sendSuccess(ProcessResult $result): void
    {
        // Check if dashboard notifications are enabled
        if (!$this->isSuccessDashboardEnabled()) {
            return;
        }

        // Build success message
        $message = $this->buildSuccessMessage($result);

        // Insert notification into database
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO dashboard_notifications (notification_type, message, processing_log_id, is_dismissed)
                VALUES (:type, :message, :log_id, 0)
            ");

            $stmt->execute([
                'type' => 'success',
                'message' => $message,
                'log_id' => $result->metadata['log_id'] ?? null
            ]);

            error_log("✅ Dashboard notification created for: {$result->filename}");
        } catch (Exception $e) {
            error_log("❌ Failed to create dashboard notification: " . $e->getMessage());
        }
    }

    /**
     * Send failure notification
     *
     * Failures are NOT shown on dashboard (email notifications are used instead).
     * This method is a no-op.
     *
     * @param ProcessResult $result Processing result
     */
    public function sendFailure(ProcessResult $result): void
    {
        // Intentionally empty - failure notifications go to email only
    }

    /**
     * Build success message for dashboard banner
     */
    private function buildSuccessMessage(ProcessResult $result): string
    {
        $records = number_format($result->recordsProcessed);
        $duration = number_format($result->processingDuration, 2);

        $message = "✅ File processed successfully: {$result->filename}";
        $message .= "\n{$records} records • {$duration}s";

        if (!empty($result->dateRange)) {
            $message .= " • {$result->dateRange}";
        }

        return $message;
    }

    /**
     * Get unread notifications for display
     */
    public static function getUnreadNotifications(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT id, notification_type, message, created_at
            FROM dashboard_notifications
            WHERE is_dismissed = 0
            ORDER BY created_at DESC
            LIMIT 10
        ");

        return $stmt->fetchAll();
    }

    /**
     * Dismiss a notification
     */
    public static function dismissNotification(PDO $pdo, int $notificationId): void
    {
        $stmt = $pdo->prepare("
            UPDATE dashboard_notifications
            SET is_dismissed = 1
            WHERE id = :id
        ");

        $stmt->execute(['id' => $notificationId]);
    }

    /**
     * Dismiss all notifications
     */
    public static function dismissAllNotifications(PDO $pdo): void
    {
        $pdo->exec("UPDATE dashboard_notifications SET is_dismissed = 1 WHERE is_dismissed = 0");
    }
}
