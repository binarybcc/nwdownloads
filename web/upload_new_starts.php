<?php

/**
 * New Subscription Starts Data Import
 *
 * Imports Newzware "New Subscription Starts" CSV files.
 * Tracks individual new subscriber events and classifies them
 * as truly new or restarts by cross-referencing renewal_events.
 *
 * CSV Format:
 * - Header at row ~11: SUB NUM, STARTED, Ed, ISS, DEL, Remark, SUBMIT, Type
 * - Date format: M/D/YY
 * - Data ends at summary section ("New,,Starts")
 *
 * @package CirculationDashboard
 * @date    2026-03-02
 */

require_once __DIR__ . '/lib/NewStartsImporter.php';

use CirculationDashboard\NewStartsImporter;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. This endpoint only accepts POST requests.'
    ]);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$start_time = microtime(true);
header('Content-Type: application/json');

// Database configuration
$db_config = [
    'host' => getenv('DB_HOST') ?: 'database',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
];

try {
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        $db_config['username'],
        $db_config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Validate file upload
    if (!isset($_FILES['newstarts_csv']) || $_FILES['newstarts_csv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred. Please select a New Subscription Starts CSV file.');
    }

    $file = $_FILES['newstarts_csv'];

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new Exception('Failed to initialize file info');
    }
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'])) {
        throw new Exception('Invalid file type. Please upload a CSV file.');
    }

    // Validate file size (max 50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 50MB.');
    }

    // Import using NewStartsImporter
    $importer = new NewStartsImporter($pdo);
    $result = $importer->import($file['tmp_name'], $file['name']);

    $processing_time = round(microtime(true) - $start_time, 2) . ' seconds';

    echo json_encode([
        'success' => true,
        'date_range' => $result['date_range'],
        'new_records' => $result['new_records'],
        'updated_records' => $result['updated_records'],
        'total_processed' => $result['total_processed'],
        'truly_new' => $result['truly_new'],
        'restarts' => $result['restarts'],
        'summary_html' => $result['summary_html'],
        'processing_time' => $processing_time
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log("New Starts Import Error: " . $e->getMessage());
}
