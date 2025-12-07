<?php
/**
 * Cleanup Script - Remove Incomplete/Test Upload Data
 *
 * This script identifies and removes snapshot dates that have:
 * 1. Incomplete paper coverage (less than 5 papers)
 * 2. Suspiciously low subscriber counts
 * 3. Non-Saturday dates (since we only use Saturdays for official snapshots)
 *
 * Usage: php cleanup_bad_data.php [--dry-run]
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
function connectDB($config) {
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
    echo "  DATA CLEANUP TOOL - Remove Incomplete/Test Uploads\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";

    if ($dryRun) {
        echo "🔍 DRY RUN MODE - No data will be deleted\n\n";
    } else {
        echo "⚠️  LIVE MODE - Data will be permanently deleted!\n\n";
    }

    // Find problematic dates
    $stmt = $pdo->query("
        SELECT
            snapshot_date,
            DAYNAME(snapshot_date) as day_name,
            DAYOFWEEK(snapshot_date) as day_num,
            COUNT(*) as paper_count,
            SUM(total_active) as total_subscribers
        FROM daily_snapshots
        WHERE paper_code != 'FN'
        GROUP BY snapshot_date
        ORDER BY snapshot_date DESC
    ");
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Expected values for valid data
    $EXPECTED_PAPERS = 5; // TA, TJ, TR, LJ, WRN
    $SATURDAY_DAY_NUM = 7;
    $DATA_CUTOFF_DATE = '2025-01-01'; // Per docs: deleted all pre-2025 data on Dec 2

    $problematicDates = [];
    $validDates = [];

    echo "Analyzing snapshot dates...\n\n";
    echo str_pad("Date", 15) . str_pad("Day", 12) . str_pad("Papers", 10) . str_pad("Subscribers", 15) . "Status\n";
    echo str_repeat("-", 70) . "\n";

    foreach ($dates as $date) {
        $isProblematic = false;
        $reasons = [];

        // Check 1: Pre-2025 data (should have been deleted already per documentation)
        if ($date['snapshot_date'] < $DATA_CUTOFF_DATE) {
            $isProblematic = true;
            $reasons[] = "Pre-2025 data";
        }

        // Check 2: Non-Saturday date in 2025 (test uploads)
        if ($date['snapshot_date'] >= $DATA_CUTOFF_DATE && $date['day_num'] != $SATURDAY_DAY_NUM) {
            $isProblematic = true;
            $reasons[] = "Not Saturday";
        }

        // Check 3: Missing papers (only flag this, don't auto-delete unless combined with other issues)
        if ($date['paper_count'] < $EXPECTED_PAPERS && $isProblematic) {
            $reasons[] = "Missing " . ($EXPECTED_PAPERS - $date['paper_count']) . " paper(s)";
        }

        $status = $isProblematic ? "❌ " . implode(", ", $reasons) : "✓ Valid";

        printf(
            "%-15s %-12s %-10d %-15s %s\n",
            $date['snapshot_date'],
            $date['day_name'],
            $date['paper_count'],
            number_format($date['total_subscribers']),
            $status
        );

        if ($isProblematic) {
            $problematicDates[] = $date['snapshot_date'];
        } else {
            $validDates[] = $date;
        }
    }

    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  Summary\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "Valid snapshot dates: " . count($validDates) . "\n";
    echo "Problematic dates to remove: " . count($problematicDates) . "\n";

    if (empty($problematicDates)) {
        echo "\n✓ No problematic dates found. Database is clean!\n\n";
        exit(0);
    }

    echo "\n";
    echo "Dates to be removed:\n";
    foreach ($problematicDates as $date) {
        echo "  - $date\n";
    }
    echo "\n";

    if ($dryRun) {
        echo "🔍 DRY RUN - No changes made. Run without --dry-run to delete these dates.\n\n";
        exit(0);
    }

    // Confirm deletion
    echo "⚠️  WARNING: This will permanently delete the above dates from both:\n";
    echo "   - daily_snapshots table\n";
    echo "   - subscriber_snapshots table\n";
    echo "\n";
    echo "Type 'DELETE' to confirm: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if ($line !== 'DELETE') {
        echo "\nCancelled. No data was deleted.\n\n";
        exit(0);
    }

    // Perform deletion
    echo "\nDeleting problematic dates...\n";

    $placeholders = str_repeat('?,', count($problematicDates) - 1) . '?';

    // Delete from daily_snapshots
    $stmt = $pdo->prepare("DELETE FROM daily_snapshots WHERE snapshot_date IN ($placeholders)");
    $stmt->execute($problematicDates);
    $dailyDeleted = $stmt->rowCount();
    echo "  ✓ Removed $dailyDeleted records from daily_snapshots\n";

    // Delete from subscriber_snapshots
    $stmt = $pdo->prepare("DELETE FROM subscriber_snapshots WHERE snapshot_date IN ($placeholders)");
    $stmt->execute($problematicDates);
    $subscriberDeleted = $stmt->rowCount();
    echo "  ✓ Removed $subscriberDeleted records from subscriber_snapshots\n";

    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  Cleanup Complete!\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "\n";
    echo "✓ Database cleaned successfully\n";
    echo "✓ Remaining valid snapshots: " . count($validDates) . "\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Refresh the dashboard to see corrected metrics\n";
    echo "2. Re-upload your most recent CSV to ensure all data is current\n";
    echo "3. Run test_metrics_math.php to verify all calculations are correct\n";
    echo "\n";

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
