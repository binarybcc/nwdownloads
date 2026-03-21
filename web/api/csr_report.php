<?php

/**
 * CSR Call Report API
 *
 * Read-only JSON endpoint providing CSR call statistics from the call_logs table.
 *
 * Modes:
 * - ?summary=true  : Per-CSR totals only (for settings card)
 * - (no param)     : Full data with summary + weekly breakdown (for report page)
 *
 * Rolling 60-day window. CSR codes mapped to human-readable names.
 *
 * @package CirculationDashboard
 */

require_once __DIR__ . '/../auth_check.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration (same as legacy.php)
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD'),
    'socket' => getenv('DB_SOCKET') !== false ? getenv('DB_SOCKET') : '/run/mysqld/mysqld10.sock',
];

/**
 * Connect to database
 *
 * @param  array<string, mixed> $config Database configuration array
 * @return PDO Database connection
 */
function connectDB(array $config): PDO
{
    if (empty($config['socket']) || !file_exists($config['socket'])) {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    } else {
        $dsn = "mysql:unix_socket={$config['socket']};dbname={$config['database']};charset=utf8mb4";
    }
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

// CSR name mapping: source_group code => human-readable name
$csrNames = [
    'BC' => 'Brittany Carroll',
    'CW' => 'Chloe Welch',
];

/**
 * Map a source_group code to a human-readable CSR name
 */
function mapCsrName(string $group, array $csrNames): string
{
    return $csrNames[$group] ?? "Unknown ({$group})";
}

try {
    $pdo = connectDB($db_config);
    $isSummaryMode = isset($_GET['summary']) && $_GET['summary'] === 'true';

    // Summary query: per-CSR totals for last 60 days, split by 4-digit extension vs other
    $summarySQL = "
        SELECT
            COALESCE(source_group, 'UNKNOWN') AS source_group,
            SUM(CASE WHEN call_direction = 'placed' AND LENGTH(remote_number) = 4 THEN 1 ELSE 0 END) AS placed_ext,
            SUM(CASE WHEN call_direction = 'placed' AND LENGTH(remote_number) != 4 THEN 1 ELSE 0 END) AS placed_other,
            SUM(CASE WHEN call_direction = 'placed' THEN 1 ELSE 0 END) AS placed,
            SUM(CASE WHEN call_direction = 'received' AND LENGTH(remote_number) = 4 THEN 1 ELSE 0 END) AS received_ext,
            SUM(CASE WHEN call_direction = 'received' AND LENGTH(remote_number) != 4 THEN 1 ELSE 0 END) AS received_other,
            SUM(CASE WHEN call_direction = 'received' THEN 1 ELSE 0 END) AS received,
            SUM(CASE WHEN call_direction = 'missed' AND LENGTH(remote_number) = 4 THEN 1 ELSE 0 END) AS missed_ext,
            SUM(CASE WHEN call_direction = 'missed' AND LENGTH(remote_number) != 4 THEN 1 ELSE 0 END) AS missed_other,
            SUM(CASE WHEN call_direction = 'missed' THEN 1 ELSE 0 END) AS missed,
            COUNT(*) AS total
        FROM call_logs
        WHERE call_timestamp >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY COALESCE(source_group, 'UNKNOWN')
        ORDER BY source_group
    ";
    $summaryStmt = $pdo->query($summarySQL);
    $summaryRows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    // Map CSR names and cast numeric fields
    $summary = array_map(
        function ($row) use ($csrNames) {
            return [
            'name'           => mapCsrName($row['source_group'], $csrNames),
            'group'          => $row['source_group'],
            'placed'         => (int) $row['placed'],
            'placed_ext'     => (int) $row['placed_ext'],
            'placed_other'   => (int) $row['placed_other'],
            'received'       => (int) $row['received'],
            'received_ext'   => (int) $row['received_ext'],
            'received_other' => (int) $row['received_other'],
            'missed'         => (int) $row['missed'],
            'missed_ext'     => (int) $row['missed_ext'],
            'missed_other'   => (int) $row['missed_other'],
            'total'          => (int) $row['total'],
            ];
        },
        $summaryRows
    );

    // Weekly breakdown (full mode only)
    $weekly = [];
    if (!$isSummaryMode) {
        $weeklySQL = "
            SELECT
                YEARWEEK(call_timestamp, 3) AS yw,
                DATE_FORMAT(
                    DATE_SUB(call_timestamp, INTERVAL WEEKDAY(call_timestamp) DAY),
                    '%Y-%m-%d'
                ) AS week_start,
                COALESCE(source_group, 'UNKNOWN') AS source_group,
                SUM(CASE WHEN call_direction = 'placed' AND LENGTH(remote_number) = 4 THEN 1 ELSE 0 END) AS placed_ext,
                SUM(CASE WHEN call_direction = 'placed' AND LENGTH(remote_number) != 4 THEN 1 ELSE 0 END) AS placed_other,
                SUM(CASE WHEN call_direction = 'placed' THEN 1 ELSE 0 END) AS placed,
                SUM(CASE WHEN call_direction = 'received' AND LENGTH(remote_number) = 4 THEN 1 ELSE 0 END) AS received_ext,
                SUM(CASE WHEN call_direction = 'received' AND LENGTH(remote_number) != 4 THEN 1 ELSE 0 END) AS received_other,
                SUM(CASE WHEN call_direction = 'received' THEN 1 ELSE 0 END) AS received,
                SUM(CASE WHEN call_direction = 'missed' AND LENGTH(remote_number) = 4 THEN 1 ELSE 0 END) AS missed_ext,
                SUM(CASE WHEN call_direction = 'missed' AND LENGTH(remote_number) != 4 THEN 1 ELSE 0 END) AS missed_other,
                SUM(CASE WHEN call_direction = 'missed' THEN 1 ELSE 0 END) AS missed
            FROM call_logs
            WHERE call_timestamp >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            GROUP BY yw, week_start, COALESCE(source_group, 'UNKNOWN')
            ORDER BY yw, source_group
        ";
        $weeklyStmt = $pdo->query($weeklySQL);
        $weeklyRows = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);

        $weekly = array_map(
            function ($row) use ($csrNames) {
                return [
                'yw'             => $row['yw'],
                'week_start'     => $row['week_start'],
                'name'           => mapCsrName($row['source_group'], $csrNames),
                'group'          => $row['source_group'],
                'placed'         => (int) $row['placed'],
                'placed_ext'     => (int) $row['placed_ext'],
                'placed_other'   => (int) $row['placed_other'],
                'received'       => (int) $row['received'],
                'received_ext'   => (int) $row['received_ext'],
                'received_other' => (int) $row['received_other'],
                'missed'         => (int) $row['missed'],
                'missed_ext'     => (int) $row['missed_ext'],
                'missed_other'   => (int) $row['missed_other'],
                ];
            },
            $weeklyRows
        );
    }

    // Subscriber call matching (full mode only): placed external calls to known subscriber phone numbers
    $subscriberCalls = [];
    if (!$isSummaryMode) {
        // Get latest snapshot date for subscriber phone lookup
        $latestSnap = $pdo->query("SELECT MAX(snapshot_date) FROM daily_snapshots")->fetchColumn();

        // Weekly subscriber-call counts per CSR
        $subCallSQL = "
            SELECT
                YEARWEEK(cl.call_timestamp, 3) AS yw,
                DATE_FORMAT(
                    DATE_SUB(cl.call_timestamp, INTERVAL WEEKDAY(cl.call_timestamp) DAY),
                    '%Y-%m-%d'
                ) AS week_start,
                COALESCE(cl.source_group, 'UNKNOWN') AS source_group,
                SUM(CASE WHEN ss.phone_normalized IS NOT NULL THEN 1 ELSE 0 END) AS to_subscriber,
                SUM(CASE WHEN ss.phone_normalized IS NULL THEN 1 ELSE 0 END) AS to_other
            FROM call_logs cl
            LEFT JOIN (
                SELECT DISTINCT phone_normalized
                FROM subscriber_snapshots
                WHERE snapshot_date = :snapshot_date
                AND phone_normalized IS NOT NULL
            ) ss ON ss.phone_normalized COLLATE utf8mb4_general_ci = cl.phone_normalized
            WHERE cl.call_timestamp >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            AND cl.call_direction = 'placed'
            AND LENGTH(cl.remote_number) != 4
            GROUP BY yw, week_start, COALESCE(cl.source_group, 'UNKNOWN')
            ORDER BY yw, source_group
        ";
        $subCallStmt = $pdo->prepare($subCallSQL);
        $subCallStmt->execute([':snapshot_date' => $latestSnap]);
        $subCallRows = $subCallStmt->fetchAll(PDO::FETCH_ASSOC);

        $subscriberCalls = array_map(
            function ($row) use ($csrNames) {
                return [
                'yw'            => $row['yw'],
                'week_start'    => $row['week_start'],
                'name'          => mapCsrName($row['source_group'], $csrNames),
                'group'         => $row['source_group'],
                'to_subscriber' => (int) $row['to_subscriber'],
                'to_other'      => (int) $row['to_other'],
                ];
            },
            $subCallRows
        );
    }

    // Date range and last updated
    $dateRangeStmt = $pdo->query("
        SELECT MIN(call_timestamp) AS earliest, MAX(call_timestamp) AS latest
        FROM call_logs
        WHERE call_timestamp >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    ");
    $dateRange = $dateRangeStmt->fetch(PDO::FETCH_ASSOC);

    $lastUpdatedStmt = $pdo->query("SELECT MAX(imported_at) AS last_updated FROM call_logs");
    $lastUpdatedRow = $lastUpdatedStmt->fetch(PDO::FETCH_ASSOC);
    $lastUpdated = $lastUpdatedRow['last_updated'] ?? null;

    echo json_encode(
        [
        'success'          => true,
        'summary'          => $summary,
        'weekly'           => $weekly,
        'subscriber_calls' => $subscriberCalls,
        'date_range'       => [
            'earliest' => $dateRange['earliest'] ?? null,
            'latest'   => $dateRange['latest'] ?? null,
        ],
        'last_updated'     => $lastUpdated,
        ]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(
        [
        'success' => false,
        'error'   => $e->getMessage(),
        ]
    );
}
