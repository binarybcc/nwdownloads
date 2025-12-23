<?php

/**
 * All Subscriber Report Upload and Import Handler
 *
 * Processes AllSubscriberReport CSV files with UPSERT logic:
 * - New snapshots: INSERT
 * - Existing snapshots: UPDATE
 * - Only imports data from 2025-01-01 onwards
 *
 * Cache Management:
 * - Clears all dashboard caches after successful import
 * - Forces fresh API responses with updated data
 *
 * Architecture:
 * - This file is a thin wrapper around AllSubscriberImporter.php
 * - All import logic lives in the library (single source of truth)
 * - See: web/lib/AllSubscriberImporter.php
 *
 * Date: 2025-12-23
 * Refactored: Eliminated 606 lines of duplicate code
 */

// Require authentication
require_once 'auth_check.php';
require_once 'SimpleCache.php';
require_once 'lib/AllSubscriberImporter.php';

use CirculationDashboard\SimpleCache;
use CirculationDashboard\AllSubscriberImporter;

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. This endpoint only accepts POST requests.'
    ]);
    exit;
}

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

// Start timing
$start_time = microtime(true);

// Response header
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
    // Connect to database
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Validate file upload
    if (!isset($_FILES['allsubscriber']) || $_FILES['allsubscriber']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['allsubscriber'];

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'])) {
        throw new Exception('Invalid file type. Please upload a CSV file');
    }

    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 10MB');
    }

    // Process CSV using AllSubscriberImporter library
    $importer = new AllSubscriberImporter($pdo);
    $result = $importer->import($file['tmp_name'], $file['name']);

    // Clear all dashboard caches (new data uploaded!)
    $cache = new SimpleCache();
    $cleared_count = $cache->clear();

    // Calculate processing time
    $processing_time = round(microtime(true) - $start_time, 2) . ' seconds';

    // Return success
    echo json_encode([
        'success' => true,
        'date_range' => $result['date_range'],
        'new_records' => $result['new_records'],
        'updated_records' => $result['updated_records'],
        'total_processed' => $result['total_processed'],
        'processing_time' => $processing_time,
        'summary_html' => $result['summary_html'],
        'cache_cleared' => $cleared_count
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => (getenv('DEBUG') === '1') ? $e->getTraceAsString() : null
    ]);
}
