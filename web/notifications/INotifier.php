<?php

/**
 * Notifier Interface
 *
 * Defines the contract for notification implementations.
 * Allows for extensible notification system (Email, Dashboard, Slack, SMS, etc.)
 *
 * All notification implementations must implement this interface.
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard\Notifications;

use CirculationDashboard\Processors\ProcessResult;

interface INotifier
{
    /**
     * Send success notification
     *
     * Called when a file is processed successfully.
     * Implementations can choose to send notifications or not (e.g., email might skip success).
     *
     * @param ProcessResult $result Processing result containing filename, records, duration, etc.
     * @return void
     */
    public function sendSuccess(ProcessResult $result): void;

    /**
     * Send failure notification
     *
     * Called when a file processing fails.
     * Should provide actionable information about the failure.
     *
     * @param ProcessResult $result Processing result containing error message and details
     * @return void
     */
    public function sendFailure(ProcessResult $result): void;
}
