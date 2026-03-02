<?php

/**
 * BU Trend Detail API
 * Returns weekly trend data for a business unit: total_active, starts, stops, net
 *
 * Params:
 *   business_unit (string, required) — e.g. "South Carolina"
 *   weeks (int, optional, default 26, min 4, max 52)
 *
 * Data sources:
 *   - daily_snapshots: total_active per paper per week (Jan 2025+)
 *   - churn_daily_summary: renewed_count (starts) and stopped_count (stops) per paper per day (Dec 2025+)
 *   - new_starts_daily_summary: new subscription starts per paper per day (Dec 2025+)
 */

// Suppress HTML error output — this is a JSON API endpoint
// Must be set before auth_check (which loads config.php)
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../auth_check.php';

header('Content-Type: application/json');
// No CORS header — this is an authenticated same-origin endpoint

// --- Database connection (same pattern as legacy.php) ---
$db_config = [
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'port'     => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD'),
    'socket'   => getenv('DB_SOCKET') !== false ? getenv('DB_SOCKET') : '/run/mysqld/mysqld10.sock',
];

function connectDB(array $config): PDO
{
    if (empty($config['socket']) || !file_exists($config['socket'])) {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    } else {
        $dsn = "mysql:unix_socket={$config['socket']};dbname={$config['database']};charset=utf8mb4";
    }
    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function sendJSON($data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function sendError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// --- Validate params ---
$business_unit = trim($_GET['business_unit'] ?? '');
if ($business_unit === '') {
    sendError('business_unit parameter is required');
}

$weeks = (int) ($_GET['weeks'] ?? 26);
$weeks = max(4, min(52, $weeks));

// --- BU → paper codes mapping (exclude FN) ---
$bu_papers = [
    'South Carolina' => ['TJ'],
    'Wyoming'        => ['TR', 'LJ', 'WRN'],
    'Michigan'       => ['TA'],
];

if (!isset($bu_papers[$business_unit])) {
    sendError("Unknown business unit: $business_unit");
}

$papers = $bu_papers[$business_unit];

try {
    $pdo = connectDB($db_config);

    // Build paper placeholders for prepared statements
    $placeholders = implode(',', array_fill(0, count($papers), '?'));

    // Step 1: Get the last N weekly snapshots for this BU
    // Group by week_num + year, take latest snapshot_date per week, SUM total_active across papers
    $sql = "
        SELECT
            MAX(ds.snapshot_date) as snapshot_date,
            ds.week_num,
            ds.year,
            SUM(ds.total_active) as total_active
        FROM daily_snapshots ds
        WHERE ds.paper_code IN ($placeholders)
            AND ds.snapshot_date = (
                SELECT MAX(sd.snapshot_date)
                FROM daily_snapshots sd
                WHERE sd.paper_code = ds.paper_code
                  AND sd.week_num = ds.week_num
                  AND sd.year = ds.year
            )
        GROUP BY ds.week_num, ds.year
        ORDER BY ds.year DESC, ds.week_num DESC
        LIMIT " . (int) $weeks . "
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($papers);
    $rows = $stmt->fetchAll();

    // Step 2: Batch-fetch all churn data in a single query
    // Group by week-Sunday so we can look up per snapshot in O(1)
    $churn_by_sunday = [];
    if (!empty($rows)) {
        // $rows is still DESC order — first = newest, last = oldest
        $newest_date  = $rows[0]['snapshot_date'];
        $oldest_date  = $rows[count($rows) - 1]['snapshot_date'];

        $dt_oldest = new DateTime($oldest_date);
        $dt_newest = new DateTime($newest_date);
        $range_sunday   = (clone $dt_oldest)->modify('-' . $dt_oldest->format('w') . ' days')->format('Y-m-d');
        $range_saturday = (clone $dt_newest)->modify('+' . (6 - (int) $dt_newest->format('w')) . ' days')->format('Y-m-d');

        // Single query: group by the Sunday of each churn row's week
        $churn_sql = "
            SELECT
                DATE_SUB(cds.snapshot_date, INTERVAL (DAYOFWEEK(cds.snapshot_date) - 1) DAY) as week_sunday,
                SUM(cds.renewed_count) as starts,
                SUM(cds.stopped_count) as stops
            FROM churn_daily_summary cds
            WHERE cds.paper_code IN ($placeholders)
              AND cds.snapshot_date BETWEEN ? AND ?
            GROUP BY week_sunday
        ";
        $churn_params = array_merge($papers, [$range_sunday, $range_saturday]);
        $churn_stmt = $pdo->prepare($churn_sql);
        $churn_stmt->execute($churn_params);

        // Key by Sunday date string for O(1) lookup
        foreach ($churn_stmt->fetchAll() as $cr) {
            $churn_by_sunday[$cr['week_sunday']] = $cr;
        }
    }

    // Step 2b: Batch-fetch all new starts data in a single query
    $newstarts_by_sunday = [];
    if (!empty($rows)) {
        $ns_sql = "
            SELECT
                DATE_SUB(ns.snapshot_date, INTERVAL (DAYOFWEEK(ns.snapshot_date) - 1) DAY) as week_sunday,
                SUM(ns.total_new_starts) as total_new_starts,
                SUM(ns.truly_new_count) as truly_new,
                SUM(ns.restart_count) as restarts
            FROM new_starts_daily_summary ns
            WHERE ns.paper_code IN ($placeholders)
              AND ns.snapshot_date BETWEEN ? AND ?
            GROUP BY week_sunday
        ";
        $ns_params = array_merge($papers, [$range_sunday, $range_saturday]);
        $ns_stmt = $pdo->prepare($ns_sql);
        $ns_stmt->execute($ns_params);

        foreach ($ns_stmt->fetchAll() as $nr) {
            $newstarts_by_sunday[$nr['week_sunday']] = $nr;
        }
    }

    // Step 3: Build result array (reverse to oldest-first for chart rendering)
    $rows = array_reverse($rows);
    $result = [];
    $prev_total = null;

    foreach ($rows as $row) {
        $snapshot_date = $row['snapshot_date'];

        // Compute this snapshot's week-Sunday for churn lookup
        $dt = new DateTime($snapshot_date);
        $sunday = (clone $dt)->modify('-' . $dt->format('w') . ' days')->format('Y-m-d');

        // Look up churn data — key existence = data available (even if values are 0)
        $churn = $churn_by_sunday[$sunday] ?? null;
        $starts = ($churn !== null) ? (int) $churn['starts'] : null;
        $stops  = ($churn !== null) ? (int) $churn['stops']  : null;

        // Look up new starts data
        $ns = $newstarts_by_sunday[$sunday] ?? null;
        $new_starts   = ($ns !== null) ? (int) $ns['truly_new'] : null;
        $new_restarts = ($ns !== null) ? (int) $ns['restarts']  : null;

        // Net = week-over-week change in total_active
        $total = (int) $row['total_active'];
        $net = ($prev_total !== null) ? ($total - $prev_total) : null;
        $prev_total = $total;

        // Short label for x-axis: "Wk 9 '26"
        $label = "Wk " . (int) $row['week_num'] . " '" . substr((string) $row['year'], 2);

        $result[] = [
            'snapshot_date' => $snapshot_date,
            'week_num'      => (int) $row['week_num'],
            'year'          => (int) $row['year'],
            'label'         => $label,
            'total_active'  => $total,
            'starts'        => $starts,
            'stops'         => $stops,
            'new_starts'    => $new_starts,
            'new_restarts'  => $new_restarts,
            'net'           => $net,
        ];
    }

    sendJSON([
        'success'         => true,
        'business_unit'   => $business_unit,
        'weeks_requested' => $weeks,
        'data'            => $result,
    ]);
} catch (PDOException $e) {
    error_log('BU Trend Detail DB error: ' . $e->getMessage());
    sendError('Database error. Please try again later.', 500);
} catch (Exception $e) {
    error_log('BU Trend Detail error: ' . $e->getMessage());
    sendError('Server error. Please try again later.', 500);
}
