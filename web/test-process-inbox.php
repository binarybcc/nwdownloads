<?php

/**
 * Test Script for Automated File Processing
 *
 * Simple CLI tool to test the process-inbox.php orchestrator
 * without waiting for cron schedule.
 *
 * Usage:
 *   php web/test-process-inbox.php
 *
 * What it does:
 * - Creates test directories if missing
 * - Executes process-inbox.php
 * - Shows results
 * - Provides cleanup commands
 *
 * Date: 2025-12-16
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║         Automated File Processing - Test Script              ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// Environment Setup
// ============================================================================

$testInboxRoot = __DIR__ . '/../test-inbox';
$directories = [
    'inbox' => "$testInboxRoot/inbox",
    'processing' => "$testInboxRoot/processing",
    'completed' => "$testInboxRoot/completed",
    'failed' => "$testInboxRoot/failed"
];

echo "📁 Test Directory Setup\n";
echo "   Root: $testInboxRoot\n\n";

// Create test directories
foreach ($directories as $name => $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        echo "   ✓ Created: $name/\n";
    } else {
        echo "   ✓ Exists: $name/\n";
    }
}

echo "\n";

// ============================================================================
// Pre-Processing Check
// ============================================================================

echo "📊 Pre-Processing Status\n";

$inboxFiles = glob($directories['inbox'] . '/*.csv');
$processingFiles = glob($directories['processing'] . '/*.csv');

echo "   Inbox files: " . count($inboxFiles) . "\n";
if (!empty($inboxFiles)) {
    foreach ($inboxFiles as $file) {
        echo "     - " . basename($file) . "\n";
    }
}

echo "   Processing files: " . count($processingFiles) . "\n";
if (!empty($processingFiles)) {
    echo "   ⚠ Warning: Files stuck in processing/ folder:\n";
    foreach ($processingFiles as $file) {
        echo "     - " . basename($file) . "\n";
    }
    echo "\n";
    echo "   Stuck files may indicate a previous failed run.\n";
    echo "   Move them back to inbox/ or to failed/ before testing.\n";
}

echo "\n";

if (empty($inboxFiles)) {
    echo "⚠ No files to process in inbox/\n";
    echo "\n";
    echo "To test processing:\n";
    echo "1. Copy a test file to: {$directories['inbox']}/\n";
    echo "   Example: cp /path/to/AllSubscriberReport20251216120000.csv {$directories['inbox']}/\n";
    echo "\n";
    echo "2. Run this test script again\n";
    echo "\n";
    exit(0);
}

// ============================================================================
// Run Orchestrator
// ============================================================================

echo "🚀 Running process-inbox.php...\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$orchestratorPath = __DIR__ . '/process-inbox.php';

if (!file_exists($orchestratorPath)) {
    echo "❌ Error: process-inbox.php not found at: $orchestratorPath\n";
    exit(1);
}

// Execute orchestrator and capture output
ob_start();
$exitCode = 0;

try {
    include $orchestratorPath;
} catch (Exception $e) {
    echo "❌ Fatal error during processing:\n";
    echo "   " . $e->getMessage() . "\n";
    $exitCode = 1;
}

$output = ob_get_clean();
echo $output;

echo "\n════════════════════════════════════════════════════════════════\n\n";

// ============================================================================
// Post-Processing Summary
// ============================================================================

echo "📊 Post-Processing Status\n";

$completedFiles = glob($directories['completed'] . '/*.csv');
$failedFiles = glob($directories['failed'] . '/*.csv');
$remainingFiles = glob($directories['inbox'] . '/*.csv');

echo "   Completed: " . count($completedFiles) . " file(s)\n";
if (!empty($completedFiles)) {
    foreach ($completedFiles as $file) {
        echo "     ✅ " . basename($file) . "\n";
    }
}

echo "   Failed: " . count($failedFiles) . " file(s)\n";
if (!empty($failedFiles)) {
    foreach ($failedFiles as $file) {
        echo "     ❌ " . basename($file) . "\n";
    }
}

echo "   Remaining in inbox: " . count($remainingFiles) . " file(s)\n";

echo "\n";

// ============================================================================
// Database Verification
// ============================================================================

echo "🗄️  Database Verification\n";

try {
    // Connect to database (development config)
    $dsn = 'mysql:host=db;port=3306;dbname=circulation_dashboard;charset=utf8mb4';
    $username = getenv('DB_USER') ?: 'circ_dash';
    $password = getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!';

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Check for processing log entries
    $stmt = $pdo->query("
        SELECT filename, status, records_processed, error_message, started_at
        FROM file_processing_log
        ORDER BY started_at DESC
        LIMIT 5
    ");

    $logs = $stmt->fetchAll();

    if (empty($logs)) {
        echo "   ⚠ No processing log entries found\n";
        echo "      (May need to run migration 006 first)\n";
    } else {
        echo "   Recent processing log entries:\n";
        foreach ($logs as $log) {
            $statusIcon = $log['status'] === 'completed' ? '✅' : '❌';
            echo "     $statusIcon {$log['filename']} - {$log['status']}\n";
            if ($log['status'] === 'completed') {
                echo "        Records: {$log['records_processed']}\n";
            } else if ($log['error_message']) {
                echo "        Error: {$log['error_message']}\n";
            }
        }
    }
} catch (PDOException $e) {
    echo "   ⚠ Could not connect to database: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================================================
// Cleanup Commands
// ============================================================================

echo "🧹 Cleanup Commands\n";
echo "   Clear completed files:\n";
echo "     rm {$directories['completed']}/*.csv\n";
echo "\n";
echo "   Clear failed files:\n";
echo "     rm {$directories['failed']}/*.csv\n";
echo "\n";
echo "   Reset all test directories:\n";
echo "     rm -rf $testInboxRoot\n";
echo "\n";

// ============================================================================
// Next Steps
// ============================================================================

if ($exitCode === 0 && count($completedFiles) > 0) {
    echo "✅ Test Successful!\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Check the database for imported data\n";
    echo "2. Verify dashboard shows updated metrics\n";
    echo "3. Review processing log for details\n";
    echo "\n";
} else if (count($failedFiles) > 0) {
    echo "❌ Processing Failures Detected\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Review error messages above\n";
    echo "2. Check failed files in: {$directories['failed']}/\n";
    echo "3. Fix issues and move files back to inbox/ for retry\n";
    echo "\n";
} else {
    echo "ℹ️  No files were processed\n";
    echo "\n";
}

exit($exitCode);
