<?php

/**
 * Email Notifier
 *
 * Sends email notifications for file processing failures.
 * Success notifications are NOT sent via email (dashboard is sufficient).
 *
 * Configuration:
 * - Email addresses stored in notification_settings table
 * - Enable/disable controlled by enable_failure_emails setting
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard\Notifications;

require_once __DIR__ . '/INotifier.php';
require_once __DIR__ . '/../processors/IFileProcessor.php';

use CirculationDashboard\Processors\ProcessResult;
use PDO;
use Exception;

class EmailNotifier implements INotifier
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
     * Get email addresses from settings
     *
     * @return array<string> List of email addresses
     */
    private function getEmailAddresses(): array
    {
        $emailsJson = $this->getSetting('email_addresses', '[]');
        $emails = json_decode($emailsJson, true);

        if (!is_array($emails) || empty($emails)) {
            error_log("⚠️ No email addresses configured for notifications");
            return [];
        }

        return $emails;
    }

    /**
     * Check if failure emails are enabled
     */
    private function isFailureEmailsEnabled(): bool
    {
        $enabled = $this->getSetting('enable_failure_emails', 'true');
        return strtolower($enabled) === 'true';
    }

    /**
     * Send success notification
     *
     * Success emails are intentionally NOT sent (dashboard is sufficient).
     * This method is a no-op.
     *
     * @param ProcessResult $result Processing result
     */
    public function sendSuccess(ProcessResult $result): void
    {
        // Intentionally empty - success notifications go to dashboard only
    }

    /**
     * Send failure notification
     *
     * Sends detailed email with error information and action steps.
     *
     * @param ProcessResult $result Processing result
     */
    public function sendFailure(ProcessResult $result): void
    {
        // Check if failure emails are enabled
        if (!$this->isFailureEmailsEnabled()) {
            return;
        }

        // Get recipient addresses
        $to = $this->getEmailAddresses();
        if (empty($to)) {
            error_log("⚠️ Cannot send failure email: No recipients configured");
            return;
        }

        // Build email
        $subject = $this->buildSubject($result);
        $body = $this->buildFailureEmail($result);
        $headers = $this->buildHeaders();

        // Send to each recipient
        foreach ($to as $recipient) {
            $success = mail($recipient, $subject, $body, $headers);
            if ($success) {
                error_log("✅ Failure notification sent to: {$recipient}");
            } else {
                error_log("❌ Failed to send notification email to: {$recipient}");
            }
        }
    }

    /**
     * Build email subject line
     */
    private function buildSubject(ProcessResult $result): string
    {
        return "⚠️ File Processing Failed: {$result->filename}";
    }

    /**
     * Build email headers
     */
    private function buildHeaders(): string
    {
        $fromEmail = $this->getSetting('from_email', 'noreply@upstatetoday.com');
        $fromName = $this->getSetting('from_name', 'Circulation Dashboard');

        return implode("\r\n", [
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "Content-Type: text/plain; charset=UTF-8",
            "X-Mailer: PHP/" . phpversion()
        ]);
    }

    /**
     * Build failure email body
     */
    private function buildFailureEmail(ProcessResult $result): string
    {
        $timestamp = date('F j, Y \a\t g:i A');
        $dashboardUrl = 'https://cdash.upstatetoday.com';
        $uploadUrl = $dashboardUrl . '/upload_unified.php';
        $settingsUrl = $dashboardUrl . '/settings.php#processing-history';

        $body = <<<EMAIL
Automated file processing failed on {$timestamp}.

FILE DETAILS:
- Filename: {$result->filename}
- Type: {$result->fileType}

ERROR:
{$result->errorMessage}

ACTION REQUIRED:
1. Check Newzware SFTP export settings
2. Verify complete file at source
3. Re-export and SFTP to inbox/ folder
   OR
4. Upload manually via dashboard: {$uploadUrl}

PROCESSING LOG:
View details: {$settingsUrl}

---
This is an automated message from the Circulation Dashboard.
To update notification settings: {$dashboardUrl}/settings.php
EMAIL;

        return $body;
    }
}
