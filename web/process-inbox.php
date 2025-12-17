<?php

/**
 * Automated File Processing Orchestrator
 *
 * Main cron script that orchestrates automated processing of CSV files
 * from SFTP inbox directory.
 *
 * Workflow:
 * 1. Scan inbox/ for CSV files
 * 2. Match files to processors via database patterns
 * 3. Move to processing/ directory
 * 4. Validate and process files
 * 5. Move to completed/ or failed/ based on result
 * 6. Log to database
 * 7. Send notifications (email failures, dashboard successes)
 *
 * Cron Schedule: Monday 00:03 AM
 * Execution: php /volume1/web/circulation/process-inbox.php
 *
 * Date: 2025-12-16
 */

// ============================================================================
// Configuration & Initialization
// ============================================================================

// Error reporting for cron execution
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display to stdout (cron email)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Start execution timer
$startTime = microtime(true);

// Detect environment (development vs production)
$isProduction = (php_uname('n') === 'upstatetoday');  // Synology NAS hostname

// Database configuration
if ($isProduction) {
    // Production: Native Synology MariaDB via Unix socket
    $dsn = 'mysql:unix_socket=/run/mysqld/mysqld10.sock;dbname=circulation_dashboard;charset=utf8mb4';
    $username = 'root';
    $password = 'P@ta675N0id';
} else {
    // Development: Docker container
    $dsn = 'mysql:host=database;port=3306;dbname=circulation_dashboard;charset=utf8mb4';
    $username = getenv('DB_USER') ?: 'circ_dash';
    $password = getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!';
}

// File paths configuration
if ($isProduction) {
    $inboxPath = '/volume1/homes/newzware/inbox';
    $processingPath = '/volume1/homes/newzware/processing';
    $completedPath = '/volume1/homes/newzware/completed';
    $failedPath = '/volume1/homes/newzware/failed';
} else {
    // Development: Use local test directories
    $inboxPath = __DIR__ . '/../test-inbox/inbox';
    $processingPath = __DIR__ . '/../test-inbox/processing';
    $completedPath = __DIR__ . '/../test-inbox/completed';
    $failedPath = __DIR__ . '/../test-inbox/failed';
}

// Ensure directories exist (create if missing in development)
if (!$isProduction) {
    foreach ([$inboxPath, $processingPath, $completedPath, $failedPath] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

// ============================================================================
// Database Connection
// ============================================================================

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("FATAL: Database connection failed: " . $e->getMessage());
    exit(1);
}

// ============================================================================
// Load Processor Classes
// ============================================================================

require_once __DIR__ . '/processors/IFileProcessor.php';
require_once __DIR__ . '/processors/AllSubscriberProcessor.php';
require_once __DIR__ . '/processors/VacationProcessor.php';
require_once __DIR__ . '/processors/RenewalProcessor.php';
require_once __DIR__ . '/processors/RatesProcessor.php';
require_once __DIR__ . '/notifications/INotifier.php';
require_once __DIR__ . '/notifications/EmailNotifier.php';
require_once __DIR__ . '/notifications/DashboardNotifier.php';

use CirculationDashboard\Processors\IFileProcessor;
use CirculationDashboard\Processors\AllSubscriberProcessor;
use CirculationDashboard\Processors\VacationProcessor;
use CirculationDashboard\Processors\RenewalProcessor;
use CirculationDashboard\Processors\RatesProcessor;
use CirculationDashboard\Processors\ProcessResult;
use CirculationDashboard\Notifications\EmailNotifier;
use CirculationDashboard\Notifications\DashboardNotifier;

// Initialize notifiers
$emailNotifier = new EmailNotifier($pdo);
$dashboardNotifier = new DashboardNotifier($pdo);

// ============================================================================
// Main Processing Logic
// ============================================================================

echo "==> Circulation Dashboard File Processing\n";
echo "==> Started: " . date('Y-m-d H:i:s') . "\n";
echo "==> Environment: " . ($isProduction ? 'Production' : 'Development') . "\n";
echo "==> Inbox: $inboxPath\n\n";

// Step 1: Scan inbox for CSV files
$files = glob("$inboxPath/*.csv");

if (empty($files)) {
    echo "✓ No files to process\n";
    echo "==> Finished: " . date('Y-m-d H:i:s') . "\n";
    exit(0);
}

echo "==> Found " . count($files) . " file(s) to process\n\n";

// Step 2: Load filename patterns from database
$patterns = loadProcessingPatterns($pdo);

if (empty($patterns)) {
    echo "⚠ No processing patterns configured in database\n";
    echo "   Run migration 006_create_file_processing_tables.sql first\n";
    exit(1);
}

// Step 3: Process each file
$processedCount = 0;
$failedCount = 0;

foreach ($files as $filepath) {
    $filename = basename($filepath);
    echo "Processing: $filename\n";

    // Match file to processor
    $processorClass = matchFileToProcessor($filename, $patterns);

    if (!$processorClass) {
        echo "  ⚠ No matching processor pattern, skipping\n\n";
        continue;
    }

    echo "  → Processor: $processorClass\n";

    // Move to processing directory
    $processingFilepath = "$processingPath/$filename";
    if (!rename($filepath, $processingFilepath)) {
        echo "  ❌ Failed to move to processing/ directory\n\n";
        continue;
    }

    // Create log entry
    $logId = createProcessingLog($pdo, $filename, $processorClass);

    try {
        // Instantiate processor
        $processor = createProcessor($processorClass, $pdo);

        // Validate file
        echo "  → Validating...\n";
        $processor->validate($processingFilepath);

        // Process file
        echo "  → Processing...\n";
        $result = $processor->process($processingFilepath);

        if ($result->success) {
            // Move to completed directory
            $finalPath = "$completedPath/$filename";
            rename($processingFilepath, $finalPath);

            // Update log
            updateProcessingLog($pdo, $logId, 'completed', $result);

            // Store log_id in result metadata for notifications
            $result->metadata['log_id'] = $logId;

            // Send notifications
            $emailNotifier->sendSuccess($result);
            $dashboardNotifier->sendSuccess($result);

            echo "  ✅ Success: {$result->recordsProcessed} records\n";
            echo "     Range: {$result->dateRange}\n";
            echo "     Duration: " . number_format($result->processingDuration, 2) . "s\n\n";

            $processedCount++;
        } else {
            throw new Exception($result->errorMessage);
        }
    } catch (Exception $e) {
        // Move to failed directory
        $finalPath = "$failedPath/$filename";
        if (file_exists($processingFilepath)) {
            rename($processingFilepath, $finalPath);
        }

        // Update log
        updateProcessingLog($pdo, $logId, 'failed', null, $e->getMessage());

        // Create ProcessResult for failure notification
        $failureResult = ProcessResult::failure(
            $filename,
            'unknown',  // fileType not available here
            $e->getMessage(),
            ['log_id' => $logId]
        );

        // Send notifications
        $emailNotifier->sendFailure($failureResult);
        $dashboardNotifier->sendFailure($failureResult);

        echo "  ❌ Failed: " . $e->getMessage() . "\n\n";
        $failedCount++;
    }
}

// ============================================================================
// Summary & Cleanup
// ============================================================================

$duration = microtime(true) - $startTime;

echo "==> Processing Complete\n";
echo "==> Processed: $processedCount file(s)\n";
echo "==> Failed: $failedCount file(s)\n";
echo "==> Duration: " . number_format($duration, 2) . "s\n";
echo "==> Finished: " . date('Y-m-d H:i:s') . "\n";

exit($failedCount > 0 ? 1 : 0);

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Load enabled processing patterns from database
 *
 * @param PDO $pdo Database connection
 * @return array<array{pattern: string, processor_class: string, priority: int}>
 */
function loadProcessingPatterns(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT pattern, processor_class, priority
        FROM file_processing_patterns
        WHERE enabled = TRUE
        ORDER BY priority ASC, id ASC
    ");

    return $stmt->fetchAll();
}

/**
 * Match filename to processor using glob-style patterns
 *
 * @param string $filename File to match
 * @param array<array{pattern: string, processor_class: string}> $patterns Patterns from database
 * @return string|null Processor class name or null if no match
 */
function matchFileToProcessor(string $filename, array $patterns): ?string
{
    foreach ($patterns as $pattern) {
        // Convert glob pattern to regex
        $regex = '#^' . str_replace(['*', '?'], ['.*', '.'], $pattern['pattern']) . '$#i';

        if (preg_match($regex, $filename)) {
            return $pattern['processor_class'];
        }
    }

    return null;
}

/**
 * Create processor instance
 *
 * @param string $processorClass Processor class name
 * @param PDO $pdo Database connection
 * @return IFileProcessor
 * @throws Exception if processor class not found
 */
function createProcessor(string $processorClass, PDO $pdo): IFileProcessor
{
    // Map class names to actual classes
    $processors = [
        'AllSubscriberProcessor' => AllSubscriberProcessor::class,
        'VacationProcessor' => VacationProcessor::class,
        'RenewalProcessor' => RenewalProcessor::class,
        'RatesProcessor' => RatesProcessor::class,
    ];

    if (!isset($processors[$processorClass])) {
        throw new Exception("Unknown processor: $processorClass");
    }

    $class = $processors[$processorClass];
    return new $class($pdo);
}

/**
 * Create processing log entry
 *
 * @param PDO $pdo Database connection
 * @param string $filename File being processed
 * @param string $processorClass Processor handling the file
 * @return int Log ID
 */
function createProcessingLog(PDO $pdo, string $filename, string $processorClass): int
{
    $stmt = $pdo->prepare("
        INSERT INTO file_processing_log
            (filename, file_type, processor_class, status, started_at, file_size_bytes)
        VALUES
            (:filename, :file_type, :processor_class, 'processing', NOW(), :file_size)
    ");

    // Extract file type from processor class name
    $fileType = strtolower(str_replace('Processor', '', $processorClass));

    $stmt->execute([
        'filename' => $filename,
        'file_type' => $fileType,
        'processor_class' => $processorClass,
        'file_size' => file_exists("processing/$filename") ? filesize("processing/$filename") : null
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * Update processing log with results
 *
 * @param PDO $pdo Database connection
 * @param int $logId Log entry ID
 * @param string $status Final status (completed, failed, skipped)
 * @param ProcessResult|null $result Processing result (if successful)
 * @param string|null $errorMessage Error message (if failed)
 */
function updateProcessingLog(
    PDO $pdo,
    int $logId,
    string $status,
    ?ProcessResult $result = null,
    ?string $errorMessage = null
): void {
    $stmt = $pdo->prepare("
        UPDATE file_processing_log
        SET
            status = :status,
            records_processed = :records,
            error_message = :error,
            completed_at = NOW(),
            processing_duration_seconds = :duration,
            file_moved_to = :moved_to,
            is_backfill = :is_backfill
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $logId,
        'status' => $status,
        'records' => $result ? $result->recordsProcessed : 0,
        'error' => $errorMessage,
        'duration' => $result ? $result->processingDuration : 0,
        'moved_to' => $status === 'completed' ? 'completed/' : 'failed/',
        'is_backfill' => $result && isset($result->metadata['is_backfill']) ? $result->metadata['is_backfill'] : 0
    ]);
}
