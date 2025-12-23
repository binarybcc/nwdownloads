#!/usr/bin/env php
<?php
/**
 * CLI Upload Script
 * Process CSV files using AllSubscriberImporter from command line
 */

if ($argc < 2) {
    echo "Usage: php cli-upload.php [csv_file_path]\n";
    exit(1);
}

$csvFile = $argv[1];

if (!file_exists($csvFile)) {
    echo "Error: File not found: $csvFile\n";
    exit(1);
}

// Load the importer class
require_once '/volume1/web/circulation/lib/AllSubscriberImporter.php';

use CirculationDashboard\AllSubscriberImporter;

// Load credentials from environment
// In production: source .env.credentials before running this script
// Example: source .env.credentials && php scripts/cli-upload.php file.csv
$dbSocket = getenv('PROD_DB_SOCKET') ?: '/run/mysqld/mysqld10.sock';
$dbUser = getenv('PROD_DB_USERNAME') ?: 'root';
$dbPass = getenv('PROD_DB_PASSWORD');
$dbName = getenv('PROD_DB_DATABASE') ?: 'circulation_dashboard';

if (!$dbPass) {
    echo "❌ Error: PROD_DB_PASSWORD environment variable not set\n";
    echo "   Run: source .env.credentials && php scripts/cli-upload.php [file]\n";
    exit(1);
}

// Connect to database (production)
try {
    $pdo = new PDO(
        "mysql:unix_socket=$dbSocket;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✓ Connected to database\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Create importer
$importer = new AllSubscriberImporter($pdo);

// Get filename
$filename = basename($csvFile);
echo "📂 Processing: $filename\n";
echo "   Path: $csvFile\n";
echo "   Size: " . filesize($csvFile) . " bytes\n\n";

try {
    // Import the file
    $result = $importer->importFromFile(
        $csvFile,
        $filename,
        'cli_script',
        '127.0.0.1',
        'CLI Upload Script'
    );

    // Display results
    echo "✅ Import Successful!\n\n";
    echo "Summary:\n";
    echo "   Snapshots Created: {$result['snapshots_created']}\n";
    echo "   Snapshot Date: {$result['snapshot_date']}\n";

    if (isset($result['papers'])) {
        echo "\n📊 Papers Processed:\n";
        foreach ($result['papers'] as $paper) {
            echo "   {$paper['code']}: {$paper['total_active']} subscribers\n";
        }
    }

    echo "\n";

    // Verify database state
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
    echo "   Date range: {$range['earliest']} to {$range['latest']}\n";
    echo "   Unique dates: {$range['unique_dates']}\n";
    echo "   Total records: {$range['total_records']}\n";

} catch (Exception $e) {
    echo "❌ Import failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
