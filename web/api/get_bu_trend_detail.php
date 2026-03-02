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
 */

require_once __DIR__ . '/../auth_check.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

    // Reverse to oldest-first for chart rendering
    $rows = array_reverse($rows);

    // Step 2: For each week, get churn data (starts/stops)
    $churn_sql = "
        SELECT
            COALESCE(SUM(cds.renewed_count), 0) as starts,
            COALESCE(SUM(cds.stopped_count), 0) as stops
        FROM churn_daily_summary cds
        WHERE cds.paper_code IN ($placeholders)
          AND cds.snapshot_date BETWEEN ? AND ?
    ";
    $churn_stmt = $pdo->prepare($churn_sql);

    $result = [];
    $prev_total = null;

    foreach ($rows as $row) {
        $snapshot_date = $row['snapshot_date'];

        // Compute week Sunday and Saturday
        // DAYOFWEEK: 1=Sun, 7=Sat in MySQL; in PHP: date('w') gives 0=Sun, 6=Sat
        $dt = new DateTime($snapshot_date);
        $day_of_week = (int) $dt->format('w'); // 0=Sun, 6=Sat
        $sunday = (clone $dt)->modify("-{$day_of_week} days")->format('Y-m-d');
        $saturday = (clone $dt)->modify('+' . (6 - $day_of_week) . ' days')->format('Y-m-d');

        // Get churn data for this week
        $churn_params = array_merge($papers, [$sunday, $saturday]);
        $churn_stmt->execute($churn_params);
        $churn = $churn_stmt->fetch();

        $starts = (int) $churn['starts'];
        $stops  = (int) $churn['stops'];

        // If no churn data exists for this week (pre-Dec 2025), mark as null
        $has_churn = ($starts > 0 || $stops > 0);

        // Net = week-over-week change in total_active
        $total = (int) $row['total_active'];
        $net = ($prev_total !== null) ? ($total - $prev_total) : null;
        $prev_total = $total;

        // Short label for x-axis: "Wk 9 '26"
        $label = "Wk " . (int) $row['week_num'] . " '" . substr($row['year'], 2);

        $result[] = [
            'snapshot_date' => $snapshot_date,
            'week_num'      => (int) $row['week_num'],
            'year'          => (int) $row['year'],
            'label'         => $label,
            'total_active'  => $total,
            'starts'        => $has_churn ? $starts : null,
            'stops'         => $has_churn ? $stops : null,
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
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}
