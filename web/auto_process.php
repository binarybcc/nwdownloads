<?php

/**
 * Automated Weekly File Processor
 *
 * Scans /volume1/homes/newzware/inbox/ for CSV files deposited by Newzware SFTP,
 * processes each one using the appropriate importer, then moves it to
 * completed/ or failed/.
 *
 * File type detection by filename prefix:
 *   AllSubscriberReport*.csv      → AllSubscriberImporter
 *   SubscribersOnVacation*.csv    → VacationImporter
 *   RenewalChurnReport*.csv       → RenewalImporter
 *   NewSubscriptionStarts*.csv    → NewStartsImporter
 *   StopAnalysisReport*.csv       → StopAnalysisImporter
 *
 * Run via Synology Task Scheduler:
 *   Command: /var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/auto_process.php
 *   Schedule: Weekly, Sunday, 08:02 AM
 *   Run as: root
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

require_once __DIR__ . '/lib/AllSubscriberImporter.php';
require_once __DIR__ . '/lib/VacationImporter.php';
require_once __DIR__ . '/lib/RenewalImporter.php';
require_once __DIR__ . '/lib/NewStartsImporter.php';
require_once __DIR__ . '/lib/StopAnalysisImporter.php';
require_once __DIR__ . '/SimpleCache.php';
require_once __DIR__ . '/notifications/INotifier.php';
require_once __DIR__ . '/notifications/EmailNotifier.php';
require_once __DIR__ . '/notifications/DashboardNotifier.php';

use CirculationDashboard\Notifications\EmailNotifier;
use CirculationDashboard\Notifications\DashboardNotifier;
use CirculationDashboard\Processors\ProcessResult;
use CirculationDashboard\AllSubscriberImporter;
use CirculationDashboard\VacationImporter;
use CirculationDashboard\RenewalImporter;
use CirculationDashboard\NewStartsImporter;
use CirculationDashboard\StopAnalysisImporter;
use CirculationDashboard\SimpleCache;

// ── Config ────────────────────────────────────────────────────────────────────
const INBOX_DIR      = '/volume1/homes/newzware/inbox/';
const COMPLETED_DIR  = '/volume1/homes/newzware/completed/';
const FAILED_DIR     = '/volume1/homes/newzware/failed/';
const PROCESSING_DIR = '/volume1/homes/newzware/processing/';
const LOG_FILE       = '/volume1/homes/newzware/auto_process.log';
const LOCK_FILE      = '/tmp/circulation_auto_process.lock';

const DB_SOCKET   = '/run/mysqld/mysqld10.sock';
const DB_NAME     = 'circulation_dashboard';
const DB_USER     = 'root';
const DB_PASSWORD = 'P@ta675N0id';

// ── Lock: prevent overlapping runs ────────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    $lock_age = time() - filemtime(LOCK_FILE);
    if ($lock_age < 3600) {
        log_msg("Already running (lock file is {$lock_age}s old). Exiting.");
        exit(0);
    }
    log_msg("Stale lock file found ({$lock_age}s old). Removing and continuing.");
    unlink(LOCK_FILE);
}
file_put_contents(LOCK_FILE, getmypid());
register_shutdown_function(fn() => @unlink(LOCK_FILE));

// ── Main ──────────────────────────────────────────────────────────────────────
log_msg("=== Auto-process started ===");

$files = glob(INBOX_DIR . '*.csv');

if (empty($files)) {
    log_msg("No CSV files found in inbox. Nothing to do.");
    log_msg("=== Done ===");
    exit(0);
}

log_msg("Found " . count($files) . " file(s) to process.");

$pdo = connect_db();
$processed = 0;
$failed    = 0;

foreach ($files as $filepath) {
    $filename = basename($filepath);
    log_msg("Processing: $filename");

    // Move to processing/ so a second run doesn't pick it up mid-flight
    $processing_path = PROCESSING_DIR . $filename;
    if (!rename($filepath, $processing_path)) {
        log_msg("  ERROR: Could not move to processing/. Skipping.");
        $failed++;
        continue;
    }

    try {
        $result  = run_importer($pdo, $processing_path, $filename);
        $summary = format_result($result);
        log_msg("  OK: $summary");

        // Clear dashboard cache so fresh data shows immediately
        (new SimpleCache())->clear();

        move_file($processing_path, COMPLETED_DIR . $filename);
        $processed++;
    } catch (Throwable $e) {
        // Throwable, not Exception: a PHP Error (TypeError, bad method call after a
        // signature change) would otherwise escape, kill the script mid-loop, leave the
        // file stuck in processing/, and silently skip every remaining file — exactly
        // the kind of silent failure the notifications below exist to prevent.
        log_msg("  FAILED: " . $e->getMessage());
        move_file($processing_path, FAILED_DIR . $filename);
        $failed++;
        notify_failure($pdo, $filename, $e->getMessage());
    }
}

log_msg("=== Done. Processed: $processed, Failed: $failed ===");
exit($failed > 0 ? 1 : 0);

// ── Helpers ───────────────────────────────────────────────────────────────────

function connect_db(): PDO
{
    $dsn = 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    return new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function run_importer(PDO $pdo, string $filepath, string $filename): array
{
    $importers = [
        'allsubscriber' => AllSubscriberImporter::class,
        'vacation'      => VacationImporter::class,
        'renewal'       => RenewalImporter::class,
        'newstarts'     => NewStartsImporter::class,
        'stopanalysis'  => StopAnalysisImporter::class,
    ];

    $type = detect_type($filename);
    if (!isset($importers[$type])) {
        throw new Exception("Unknown file type: $filename");
    }

    return (new $importers[$type]($pdo))->import($filepath, $filename);
}

function format_result(array $result): string
{
    // AllSubscriberImporter returns: new_records, updated_records, total_processed
    if (isset($result['new_records'])) {
        return "new={$result['new_records']} updated={$result['updated_records']} total={$result['total_processed']}";
    }
    // VacationImporter returns: stats.updated_rows, stats.total_rows
    if (isset($result['stats'])) {
        $s = $result['stats'];
        return "updated={$s['updated_rows']} total={$s['total_rows']}";
    }
    // RenewalImporter returns: events_imported, summaries_imported
    if (isset($result['events_imported'])) {
        return "events={$result['events_imported']} summaries={$result['summaries_imported']}";
    }
    // NewStartsImporter returns: truly_new, restarts, total_processed
    if (isset($result['truly_new'])) {
        return "total={$result['total_processed']} truly_new={$result['truly_new']} restarts={$result['restarts']}";
    }
    return json_encode($result);
}

/**
 * Map a filename to its file type
 *
 * Single source of truth for routing: run_importer() picks the importer from this,
 * and failure notifications use it to label the file. Keeping one table means the
 * two cannot drift apart.
 */
function detect_type(string $filename): string
{
    $types = [
        'AllSubscriberReport'    => 'allsubscriber',
        'SubscribersOnVacation'  => 'vacation',
        'RenewalChurnReport'     => 'renewal',
        'NewSubscriptionStarts'  => 'newstarts',
        'NewStart'               => 'newstarts',
        'StopAnalysisReport'     => 'stopanalysis',
        'StopAnalysis'           => 'stopanalysis',
    ];

    foreach ($types as $prefix => $type) {
        if (str_starts_with($filename, $prefix)) {
            return $type;
        }
    }

    return 'unknown';
}

/**
 * Alert on a failed file
 *
 * The notifiers existed but were only wired into process-inbox.php, which never runs
 * in production — so 38 consecutive failures between 2026-07-08 and 2026-08-20 went
 * unnoticed for seven weeks. Notification problems must never stop processing, so
 * everything here is best-effort and reported to the log.
 */
function notify_failure(PDO $pdo, string $filename, string $error): void
{
    try {
        $result = ProcessResult::failure($filename, detect_type($filename), $error);

        foreach ([new DashboardNotifier($pdo), new EmailNotifier($pdo)] as $notifier) {
            try {
                $notifier->sendFailure($result);
            } catch (Throwable $e) {
                log_msg("  WARNING: " . get_class($notifier) . " failed: " . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        log_msg("  WARNING: Could not send failure notification: " . $e->getMessage());
    }
}

function move_file(string $from, string $to): void
{
    if (!rename($from, $to)) {
        log_msg("  WARNING: Could not move $from → $to");
    }
}

function log_msg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}
