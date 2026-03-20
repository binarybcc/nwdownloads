<?php

/**
 * PURGE ALL DATA - Complete Database Reset
 *
 * WARNING: This will delete ALL data from:
 * - daily_snapshots table
 * - subscriber_snapshots table
 *
 * Use this to start fresh when you need to re-upload clean data.
 *
 * Usage: php purge_all_data.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv);
// Database configuration
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
    'socket' => getenv('DB_SOCKET') !== false ? getenv('DB_SOCKET') : '/run/mysqld/mysqld10.sock',
];

/**
 * Connect to database
 */
function connectDB($config)
{

    try {
        if (empty($config['socket']) || !file_exists($config['socket'])) {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
        } else {
            $dsn = "mysql:unix_socket={$config['socket']};dbname={$config['database']};charset=utf8mb4";
        }
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage() . "\n");
    }
}

try {
    $pdo = connectDB($db_config);
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  ⚠️  COMPLETE DATABASE PURGE\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
    if ($dryRun) {
        echo "🔍 DRY RUN MODE - No data will be deleted\n\n";
    } else {
        echo "🔴 LIVE MODE - ALL DATA WILL BE PERMANENTLY DELETED!\n\n";
    }

    // Get current data counts
    echo "Current Database Contents:\n";
    echo "─────────────────────────────────────────────────────────────\n\n";
// daily_snapshots count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM daily_snapshots");
    $dailyCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  daily_snapshots:       " . number_format($dailyCount) . " records\n";
// subscriber_snapshots count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subscriber_snapshots");
    $subscriberCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  subscriber_snapshots:  " . number_format($subscriberCount) . " records\n";
    echo "\n";
// Date range
    $stmt = $pdo->query("
        SELECT
            MIN(snapshot_date) as earliest,
            MAX(snapshot_date) as latest
        FROM daily_snapshots
    ");
    $dateRange = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dateRange['earliest']) {
        echo "  Date Range: {$dateRange['earliest']} to {$dateRange['latest']}\n";
    }

    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    if ($dailyCount == 0 && $subscriberCount == 0) {
        echo "✓ Database is already empty. Nothing to purge.\n\n";
        exit(0);
    }

    if ($dryRun) {
        echo "🔍 DRY RUN - The following would be deleted:\n\n";
        echo "  ✗ " . number_format($dailyCount) . " daily snapshot records\n";
        echo "  ✗ " . number_format($subscriberCount) . " subscriber records\n";
        echo "\n";
        echo "Run without --dry-run to actually delete this data.\n\n";
        exit(0);
    }

    // Confirm deletion
    echo "⚠️  WARNING: You are about to PERMANENTLY DELETE:\n\n";
    echo "  • " . number_format($dailyCount) . " daily snapshot records\n";
    echo "  • " . number_format($subscriberCount) . " subscriber records\n";
    echo "  • All historical data from {$dateRange['earliest']} to {$dateRange['latest']}\n";
    echo "\n";
    echo "This action CANNOT be undone!\n";
    echo "\n";
    echo "Type 'PURGE ALL DATA' to confirm (exact text required): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    if ($line !== 'PURGE ALL DATA') {
        echo "\nCancelled. No data was deleted.\n\n";
        exit(0);
    }

    echo "\n";
    echo "Purging all data...\n";
    echo "─────────────────────────────────────────────────────────────\n\n";
// Delete from subscriber_snapshots first (may have foreign key)
    $stmt = $pdo->query("DELETE FROM subscriber_snapshots");
    $subscriberDeleted = $stmt->rowCount();
    echo "  ✓ Deleted " . number_format($subscriberDeleted) . " records from subscriber_snapshots\n";
// Delete from daily_snapshots
    $stmt = $pdo->query("DELETE FROM daily_snapshots");
    $dailyDeleted = $stmt->rowCount();
    echo "  ✓ Deleted " . number_format($dailyDeleted) . " records from daily_snapshots\n";
// Reset auto-increment counters
    $pdo->query("ALTER TABLE subscriber_snapshots AUTO_INCREMENT = 1");
    $pdo->query("ALTER TABLE daily_snapshots AUTO_INCREMENT = 1");
    echo "  ✓ Reset auto-increment counters\n";
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  ✓ PURGE COMPLETE\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "Database is now empty and ready for fresh data upload.\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Go to http://192.168.1.254:8081/upload_page.php\n";
    echo "2. Upload your first CSV (older date)\n";
    echo "3. Upload your second CSV (newer date)\n";
    echo "4. Verify the data looks correct\n";
    echo "\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
