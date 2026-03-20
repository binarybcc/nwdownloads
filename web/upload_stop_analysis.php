<?php

/**
 * Stop Analysis Report Data Import
 *
 * Imports Newzware "Stop Analysis Report" CSV files.
 * Captures per-subscriber stop data including contact info,
 * stop reasons, and remarks for drill-down analysis.
 *
 * CSV Format:
 * - Header at row ~7: Sub Number, First Name, Last Name, ..., Stop Date, ..., Edition
 * - Date format: M/D/YY
 * - Data ends at count row or "Report Criteria" section
 *
 * @package CirculationDashboard
 * @date    2026-03-03
 */

require_once __DIR__ . '/lib/StopAnalysisImporter.php';

use CirculationDashboard\StopAnalysisImporter;

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
    if (!isset($_FILES['stop_analysis_csv']) || $_FILES['stop_analysis_csv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred. Please select a Stop Analysis Report CSV file.');
    }

    $file = $_FILES['stop_analysis_csv'];

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

    // Import using StopAnalysisImporter
    $importer = new StopAnalysisImporter($pdo);
    $result = $importer->import($file['tmp_name'], $file['name']);

    $processing_time = round(microtime(true) - $start_time, 2) . ' seconds';

    echo json_encode([
        'success' => true,
        'date_range' => $result['date_range'],
        'new_records' => $result['new_records'],
        'updated_records' => $result['updated_records'],
        'total_processed' => $result['total_processed'],
        'summary_html' => $result['summary_html'],
        'processing_time' => $processing_time
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log("Stop Analysis Import Error: " . $e->getMessage());
}
