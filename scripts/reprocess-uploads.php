#!/usr/bin/env php
<?php
/**
 * Reprocess Archived Uploads
 *
 * Processes CSV files from raw_uploads archive into daily_snapshots.
 * Used to restore data after December 15th data loss incident.
 */

// Detect if running from production or development
if (file_exists('/volume1/web/circulation/lib/AllSubscriberImporter.php')) {
    // Production
    require_once '/volume1/web/circulation/lib/AllSubscriberImporter.php';
} else {
    // Development
    require_once __DIR__ . '/../web/lib/AllSubscriberImporter.php';
}

use CirculationDashboard\AllSubscriberImporter;

// Auto-detect environment
$isProduction = file_exists('/run/mysqld/mysqld10.sock');

try {
    if ($isProduction) {
        // Production: Load from environment variables
        // Run: source .env.credentials && php scripts/reprocess-uploads.php
        $dbSocket = getenv('PROD_DB_SOCKET') ?: '/run/mysqld/mysqld10.sock';
        $dbUser = getenv('PROD_DB_USERNAME') ?: 'root';
        $dbPass = getenv('PROD_DB_PASSWORD');
        $dbName = getenv('PROD_DB_DATABASE') ?: 'circulation_dashboard';

        if (!$dbPass) {
            echo "❌ Error: PROD_DB_PASSWORD environment variable not set\n";
            echo "   Run: source .env.credentials && php scripts/reprocess-uploads.php\n";
            exit(1);
        }

        $pdo = new PDO(
            "mysql:unix_socket=$dbSocket;dbname=$dbName;charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        echo "✓ Connected to PRODUCTION database\n";
    } else {
        // Non-production fallback: connect via TCP
        $pdo = new PDO(
            'mysql:host=localhost;port=3306;dbname=circulation_dashboard;charset=utf8mb4',
            getenv('DB_USER') ?: 'circ_dash',
            getenv('DB_PASSWORD') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        echo "✓ Connected to local database\n";
    }
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🔄 Processing archived uploads...\n\n";

// Get all uploads that need processing
$stmt = $pdo->query("
    SELECT
        upload_id,
        filename,
        snapshot_date,
        subscriber_count,
        raw_csv_data
    FROM raw_uploads
    WHERE filename LIKE 'AllSubscriberReport%'
    ORDER BY snapshot_date ASC
");

$uploads = $stmt->fetchAll();
echo "Found " . count($uploads) . " uploads to process\n\n";

$importer = new AllSubscriberImporter($pdo);
$processed = 0;
$errors = 0;

foreach ($uploads as $upload) {
    echo "Processing: {$upload['filename']}\n";
    echo "  Date: {$upload['snapshot_date']}\n";
    echo "  Subscribers: {$upload['subscriber_count']}\n";

    try {
        // Create temp file from raw CSV data
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
        file_put_contents($tempFile, $upload['raw_csv_data']);

        // Process the CSV
        $result = $importer->importFromFile(
            $tempFile,
            $upload['filename'],
            'reprocess_script',
            '127.0.0.1',
            'CLI Script'
        );

        // Clean up
        unlink($tempFile);

        echo "  ✓ Success: {$result['snapshots_created']} snapshots created\n";
        $processed++;

    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $errors++;
    }

    echo "\n";
}

// Summary
echo "═══════════════════════════════════════\n";
echo "Reprocessing Complete!\n";
echo "═══════════════════════════════════════\n";
echo "Processed: $processed uploads\n";
echo "Errors: $errors uploads\n";
echo "\n";

// Verify data range
$stmt = $pdo->query("
    SELECT
        MIN(snapshot_date) as earliest,
        MAX(snapshot_date) as latest,
        COUNT(DISTINCT snapshot_date) as unique_dates,
        COUNT(*) as total_records
    FROM daily_snapshots
");
$range = $stmt->fetch();

echo "Database Status:\n";
echo "  Date range: {$range['earliest']} to {$range['latest']}\n";
echo "  Unique dates: {$range['unique_dates']}\n";
echo "  Total records: {$range['total_records']}\n";
echo "\n";

echo "✅ Dashboard data restored successfully!\n";
