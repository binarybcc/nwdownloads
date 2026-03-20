<?php

/**
 * Stop Events API
 * Returns individual stop events for a business unit + week for drill-down display.
 *
 * Params:
 *   business_unit (string, required) — e.g. "South Carolina"
 *   week_num (int, required) — ISO week number (0-based Sunday weeks)
 *   year (int, required) — 4-digit year
 *
 * Data source:
 *   - stop_events: per-subscriber stop data from Stop Analysis Report
 */

// Suppress HTML error output — this is a JSON API endpoint
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../auth_check.php';

header('Content-Type: application/json');

// --- Database connection (same pattern as get_bu_trend_detail.php) ---
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

$week_num = isset($_GET['week_num']) ? (int) $_GET['week_num'] : null;
if ($week_num === null) {
    sendError('week_num parameter is required');
}

$year = isset($_GET['year']) ? (int) $_GET['year'] : null;
if ($year === null) {
    sendError('year parameter is required');
}

// --- BU → paper codes mapping (same as get_bu_trend_detail.php) ---
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

    $placeholders = implode(',', array_fill(0, count($papers), '?'));

    // Query stop_events for this BU + week
    // WEEK(stop_date, 0) uses Sunday as first day of week (matches daily_snapshots.week_num)
    $sql = "
        SELECT
            sub_num,
            CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) AS subscriber_name,
            first_name,
            last_name,
            CONCAT_WS(', ',
                NULLIF(TRIM(CONCAT_WS(' ', COALESCE(street_address, ''), COALESCE(address2, ''))), ''),
                NULLIF(city, ''),
                NULLIF(CONCAT(COALESCE(state, ''), ' ', COALESCE(zip, '')), ' ')
            ) AS mailing_address,
            phone,
            email,
            paper_code,
            rate,
            start_date,
            stop_date,
            paid_date,
            stop_reason,
            remark
        FROM stop_events
        WHERE paper_code IN ($placeholders)
          AND WEEK(stop_date, 0) = ?
          AND YEAR(stop_date) = ?
        ORDER BY stop_date DESC, last_name ASC
    ";

    $params = array_merge($papers, [$week_num, $year]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stops = $stmt->fetchAll();

    sendJSON([
        'success'       => true,
        'business_unit' => $business_unit,
        'week_num'      => $week_num,
        'year'          => $year,
        'count'         => count($stops),
        'stops'         => $stops,
    ]);
} catch (PDOException $e) {
    error_log('Stop Events API DB error: ' . $e->getMessage());
    sendError('Database error. Please try again later.', 500);
} catch (Exception $e) {
    error_log('Stop Events API error: ' . $e->getMessage());
    sendError('Server error. Please try again later.', 500);
}
