<?php

/**
 * Call Log Scraper - CLI Runner
 *
 * Scrapes BroadWorks MyCommPilot call logs for circulation staff
 * and inserts into call_logs table with INSERT IGNORE dedup.
 *
 * Run: /var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/fetch_call_logs.php
 * Schedule: Hourly via macOS launchd (com.circulation.call-scraper)
 *
 * @package CirculationDashboard
 */

// ── CLI-only guard ────────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
date_default_timezone_set('America/New_York');

require_once __DIR__ . '/lib/MyCommPilotScraper.php';

use CirculationDashboard\MyCommPilotScraper;

// ── Config (LOG_FILE must be defined before business-hours guard) ─────────────
const LOG_FILE    = '/volume1/web/circulation/logs/call_scraper.log';
const DB_SOCKET   = '/run/mysqld/mysqld10.sock';
const DB_NAME     = 'circulation_dashboard';
const DB_USER     = 'root';
const DB_PASSWORD  = 'P@ta675N0id';

// ── Business-hours guard (8am-8pm ET) ─────────────────────────────────────────
$hour = (int) date('G');
if ($hour < 8 || $hour >= 20) {
    log_msg("Outside business hours ({$hour}:00 ET). Exiting.");
    exit(0);
}
const LOCK_FILE   = '/tmp/circulation_call_scraper.lock';
const ALERT_TO    = 'jcorbin@upstatetoday.com';
const ALERT_FROM  = 'Circulation Dashboard <noreply@upstatetoday.com>';

// ── Lock: prevent overlapping runs ────────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    $lock_age = time() - filemtime(LOCK_FILE);
    if ($lock_age < 3600) {
        log_msg("Already running (lock file is {$lock_age}s old). Exiting.");
        exit(0);
    }
    log_msg("Stale lock file found ({$lock_age}s old). Removing.");
    unlink(LOCK_FILE);
}
file_put_contents(LOCK_FILE, getmypid());
register_shutdown_function(fn() => @unlink(LOCK_FILE));

// ── Credential loading ────────────────────────────────────────────────────────
$envFile = __DIR__ . '/.env.mycommpilot';
if (!file_exists($envFile)) {
    log_msg("ERROR: .env.mycommpilot not found at {$envFile}");
    send_alert(
        'Call Scraper: Missing Credentials',
        "The .env.mycommpilot file was not found at {$envFile}. Scraper cannot run."
    );
    exit(1);
}

$envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($envLines as $line) {
    if (str_starts_with(trim($line), '#')) {
        continue;
    }
    [$key, $val] = explode('=', $line, 2);
    $env[trim($key)] = trim($val);
}

$username = $env['MYCOMMPILOT_USERNAME'] ?? null;
$password = $env['MYCOMMPILOT_PASSWORD'] ?? null;

if (!$username || !$password) {
    log_msg('ERROR: MYCOMMPILOT_USERNAME or MYCOMMPILOT_PASSWORD missing from .env.mycommpilot');
    send_alert(
        'Call Scraper: Invalid Credentials File',
        "MYCOMMPILOT_USERNAME or MYCOMMPILOT_PASSWORD missing from {$envFile}"
    );
    exit(1);
}

// ── User configuration ────────────────────────────────────────────────────────
$users = [
    ['key' => 'West+Carolina+Telephone::Edwards_Group::8649736678EgP', 'group' => 'BC', 'ext' => '8649736678'],
    ['key' => 'West+Carolina+Telephone::Edwards_Group::8649736689EgP', 'group' => 'CW', 'ext' => '8649736689'],
];
$callTypes = ['placed', 'received', 'missed'];

// ── Main ──────────────────────────────────────────────────────────────────────
log_msg('=== Call log scrape started ===');

// Create scraper and login (with one retry)
$scraper = new MyCommPilotScraper($username, $password);
$loggedIn = $scraper->login();

if (!$loggedIn) {
    log_msg('Login failed. Retrying in 30 seconds...');
    sleep(30);
    $scraper = new MyCommPilotScraper($username, $password);
    $loggedIn = $scraper->login();
}

if (!$loggedIn) {
    log_msg('ERROR: Login to MyCommPilot failed after retry.');
    send_alert(
        'Call Scraper: Login Failed',
        "Failed to authenticate to MyCommPilot after two attempts.\n"
        . "Username: {$username}\n"
        . "Portal: https://ws2.mycommpilot.com\n\n"
        . 'Check if credentials have changed or the portal is down.'
    );
    exit(1);
}

log_msg('Login successful.');

// Connect to database
$pdo = connect_db();
$stmt = $pdo->prepare("
    INSERT IGNORE INTO call_logs
        (call_direction, call_timestamp, remote_number, phone_normalized,
         local_extension, source_group, raw_payload)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$totalScraped = 0;
$totalInserted = 0;

// Scrape each user and call type
foreach ($users as $user) {
    foreach ($callTypes as $type) {
        try {
            $entries = $scraper->getCallLogs($user['key'], $type);
        } catch (\RuntimeException $e) {
            // Broken parsing or cURL error
            log_msg("  ERROR [{$user['group']}/{$type}]: {$e->getMessage()}");
            send_alert(
                "Call Scraper: Parse Error ({$user['group']}/{$type})",
                "Error scraping {$type} calls for {$user['group']}:\n{$e->getMessage()}"
            );
            continue;
        }

        $count = count($entries);
        $inserted = 0;

        foreach ($entries as $entry) {
            $timestamp = $scraper->parseBroadWorksDatetime($entry['datetime']);
            if ($timestamp === null) {
                log_msg("  WARN: Could not parse datetime '{$entry['datetime']}' — skipping entry");
                continue;
            }

            $normalized = $scraper->normalizePhone($entry['phone']);
            $rawPayload = json_encode($entry, JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                $type,                // call_direction
                $timestamp,           // call_timestamp
                $entry['phone'],      // remote_number (raw)
                $normalized,          // phone_normalized (10-digit or null)
                $user['ext'],         // local_extension
                $user['group'],       // source_group (BC or CW)
                $rawPayload,          // raw_payload (JSON)
            ]);

            if ($stmt->rowCount() > 0) {
                $inserted++;
            }
        }

        $totalScraped += $count;
        $totalInserted += $inserted;
        log_msg("  {$user['group']}/{$type}: {$count} entries ({$inserted} new)");
    }
}

// Logout and report
$scraper->logout();
log_msg("=== Done. Scraped: {$totalScraped}, New: {$totalInserted} ===");
exit(0);

// ── Helper Functions ──────────────────────────────────────────────────────────

/**
 * Connect to the circulation dashboard database.
 *
 * @return PDO Database connection
 */
function connect_db(): \PDO
{
    $dsn = 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    return new \PDO($dsn, DB_USER, DB_PASSWORD, [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
}

/**
 * Log a message to stdout and the log file.
 *
 * @param string $msg Message to log
 */
function log_msg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    @file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

/**
 * Send an alert email for scraper failures.
 *
 * Uses direct mail() rather than EmailNotifier (which requires ProcessResult).
 *
 * @param string $subject Email subject
 * @param string $body Email body text
 */
function send_alert(string $subject, string $body): void
{
    $headers = "From: " . ALERT_FROM . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $success = mail(ALERT_TO, $subject, $body, $headers);
    if ($success) {
        log_msg("  Alert email sent: {$subject}");
    } else {
        log_msg("  WARNING: Failed to send alert email: {$subject}");
    }
}
