<?php

/**
 * Phase 1A Database Setup Script
 * Creates publication_schedule table and weekly_summary view
 * Run once via: php phase1_setup.php
 */

header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
 * @param array<string, mixed> $config Database configuration array
 * @return PDO Database connection
 */
function connectDB(array $config): PDO
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
        die("❌ Database connection failed: " . $e->getMessage() . "\n");
    }
}

echo "==========================================\n";
echo "Phase 1A: Database Foundation Setup\n";
echo "==========================================\n\n";
try {
    $pdo = connectDB($db_config);
    echo "✅ Connected to database\n\n";
// Step 1: Create publication_schedule table
    echo "Step 1: Creating publication_schedule table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS publication_schedule (
        paper_code VARCHAR(10) NOT NULL,
        day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
        has_print BOOLEAN DEFAULT FALSE COMMENT 'True if print edition publishes this day',
        has_digital BOOLEAN DEFAULT FALSE COMMENT 'True if digital content updates this day',
        PRIMARY KEY (paper_code, day_of_week)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Publication schedule for all papers'";
    $pdo->exec($sql);
    echo "✅ publication_schedule table created\n\n";
// Step 2: Seed publication schedule data
    echo "Step 2: Seeding publication schedule data...\n";
// Clear existing data
    $pdo->exec("DELETE FROM publication_schedule");
// TJ: Print Wed/Sat, Digital Tue-Sat
    $stmt = $pdo->prepare("INSERT INTO publication_schedule (paper_code, day_of_week, has_print, has_digital) VALUES (?, ?, ?, ?)");
    $stmt->execute(['TJ', 2, 0, 1]);
// Tuesday: Digital only
    $stmt->execute(['TJ', 3, 1, 1]);
// Wednesday: Print + Digital
    $stmt->execute(['TJ', 4, 0, 1]);
// Thursday: Digital only
    $stmt->execute(['TJ', 5, 0, 1]);
// Friday: Digital only
    $stmt->execute(['TJ', 6, 1, 1]);
// Saturday: Print + Digital

    // TA: Print Wed only
    $stmt->execute(['TA', 3, 1, 0]);
// Wednesday: Print only

    // TR: Print Wed/Sat, Digital on print days
    $stmt->execute(['TR', 3, 1, 1]);
// Wednesday: Print + Digital
    $stmt->execute(['TR', 6, 1, 1]);
// Saturday: Print + Digital

    // LJ: Print Wed/Sat, Digital on print days
    $stmt->execute(['LJ', 3, 1, 1]);
// Wednesday: Print + Digital
    $stmt->execute(['LJ', 6, 1, 1]);
// Saturday: Print + Digital

    // WRN: Print Thu only, Digital on print days
    $stmt->execute(['WRN', 4, 1, 1]);
// Thursday: Print + Digital

    echo "✅ Publication schedule seeded (13 rows)\n\n";
// Verify data
    echo "Verification - Print days by paper:\n";
    $result = $pdo->query("
        SELECT
            paper_code,
            GROUP_CONCAT(
                CASE day_of_week
                    WHEN 0 THEN 'Sun'
                    WHEN 1 THEN 'Mon'
                    WHEN 2 THEN 'Tue'
                    WHEN 3 THEN 'Wed'
                    WHEN 4 THEN 'Thu'
                    WHEN 5 THEN 'Fri'
                    WHEN 6 THEN 'Sat'
                END
                ORDER BY day_of_week SEPARATOR ', '
            ) AS print_days
        FROM publication_schedule
        WHERE has_print = TRUE
        GROUP BY paper_code
        ORDER BY paper_code
    ");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        printf("  %s: %s\n", $row['paper_code'], $row['print_days']);
    }
    echo "\n";
// Step 3: Create weekly_summary view
    echo "Step 3: Creating weekly_summary view...\n";
// Drop existing view if it exists
    $pdo->exec("DROP VIEW IF EXISTS weekly_summary");
// Create the view
    $sql = "CREATE VIEW weekly_summary AS
SELECT
    DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY) as week_start_date,
    CONCAT(
        DATE_FORMAT(DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY), '%b %d'),
        ' - ',
        DATE_FORMAT(DATE_ADD(DATE_SUB(ds.snapshot_date, INTERVAL WEEKDAY(ds.snapshot_date) DAY), INTERVAL 6 DAY), '%b %d, %Y')
    ) as week_label,
    ds.paper_code,
    ds.paper_name,
    ds.business_unit,
    COUNT(DISTINCT ds.snapshot_date) as print_days_reported,
    ROUND(AVG(ds.total_active), 0) as avg_total_active,
    ROUND(AVG(ds.deliverable), 0) as avg_deliverable,
    MAX(ds.total_active) as max_total_active,
    MIN(ds.total_active) as min_total_active,
    MAX(ds.total_active) - MIN(ds.total_active) as weekly_variation,
    ROUND(AVG(ds.mail_delivery), 0) as avg_mail,
    ROUND(AVG(ds.carrier_delivery), 0) as avg_carrier,
    ROUND(AVG(ds.digital_only), 0) as avg_digital,
    ROUND(AVG(ds.on_vacation), 0) as avg_vacation,
    MAX(ds.snapshot_date) as latest_snapshot_in_week,
    (SELECT COUNT(*) FROM publication_schedule ps2 WHERE ps2.paper_code = ds.paper_code AND ps2.has_print = TRUE) as expected_print_days,
    (COUNT(DISTINCT ds.snapshot_date) >= (SELECT COUNT(*) FROM publication_schedule ps3 WHERE ps3.paper_code = ds.paper_code AND ps3.has_print = TRUE)) as is_week_complete
FROM daily_snapshots ds
INNER JOIN publication_schedule ps
    ON ds.paper_code = ps.paper_code
    AND DAYOFWEEK(ds.snapshot_date) - 1 = ps.day_of_week
    AND ps.has_print = TRUE
WHERE ds.snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
GROUP BY
    week_start_date,
    week_label,
    ds.paper_code,
    ds.paper_name,
    ds.business_unit
ORDER BY
    week_start_date DESC,
    ds.business_unit,
    ds.paper_name";
    $pdo->exec($sql);
    echo "✅ weekly_summary view created\n\n";
// Test the view
    echo "Testing weekly_summary view:\n";
    $result = $pdo->query("
        SELECT
            week_label,
            paper_code,
            print_days_reported,
            expected_print_days,
            is_week_complete,
            avg_total_active
        FROM weekly_summary
        ORDER BY week_start_date DESC, paper_code
        LIMIT 10
    ");
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) > 0) {
        echo "  Found " . count($rows) . " week(s) of data:\n";
        foreach ($rows as $row) {
            printf(
                "  %s | %s | %d/%d days | %s active\n",
                $row['week_label'],
                $row['paper_code'],
                $row['print_days_reported'],
                $row['expected_print_days'],
                $row['avg_total_active']
            );
        }
    } else {
        echo "  ⚠️  No weekly data yet (expected - need print day uploads)\n";
    }
    echo "\n";
    echo "==========================================\n";
    echo "Phase 1A Setup Complete!\n";
    echo "==========================================\n\n";
    echo "Next steps:\n";
    echo "1. Upload CSVs on print days (Wed, Thu, Sat)\n";
    echo "2. After 1 week, weekly_summary will show data\n";
    echo "3. Proceed to Phase 1B (API development)\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
