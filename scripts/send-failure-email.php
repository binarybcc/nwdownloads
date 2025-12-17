#!/usr/bin/php
<?php
/**
 * Backup Failure Email Notification Script
 *
 * Sends email alerts when automated backups fail
 * Called by backup-circulation.sh
 *
 * Usage: php send-failure-email.php "Error message here"
 */

// Email configuration
$to = 'it@upstatetoday.com';
$from = 'backups@upstatetoday.com';
$subject = '[ALERT] Circulation Dashboard Backup Failed';

// Get error message from command line argument
$error_message = $argv[1] ?? 'Unknown error';
$timestamp = date('Y-m-d H:i:s');
$hostname = gethostname();

// Build email body
$body = <<<EMAIL
==============================================
Circulation Dashboard Backup Failure Alert
==============================================

Time: {$timestamp}
Server: {$hostname}
Status: FAILED

Error Details:
{$error_message}

==============================================

Action Required:
1. SSH into NAS: ssh it@192.168.1.254
2. Check backup logs: tail -50 /volume1/homes/newzware/backup/logs/backup-*.log
3. Verify disk space: df -h /volume1
4. Test database connection: mysql -uroot -p -S /run/mysqld/mysqld10.sock
5. Run manual backup: /volume1/homes/it/scripts/backup-circulation.sh

Backup System Details:
- Schedule: Sunday 23:30, Wednesday 00:30, Friday 00:30
- Backup location: /volume1/homes/newzware/backup/
- Log directory: /volume1/homes/newzware/backup/logs/
- Web interface: https://cdash.upstatetoday.com/backup.php

If backups continue to fail, investigate immediately.

==============================================
This is an automated alert from the Circulation Dashboard backup system.
EMAIL;

// Email headers
$headers = [
    "From: {$from}",
    "Reply-To: {$from}",
    "X-Mailer: PHP/" . phpversion(),
    "X-Priority: 1 (Highest)",
    "X-MSMail-Priority: High",
    "Importance: High"
];

// Send email
$success = mail($to, $subject, $body, implode("\r\n", $headers));

// Log result
if ($success) {
    echo "Email notification sent successfully to {$to}\n";
    exit(0);
} else {
    echo "Failed to send email notification\n";
    exit(1);
}
