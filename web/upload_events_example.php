<?php

/**
 * Example: Event Data Import (Pattern 2)
 *
 * This is a TEMPLATE for implementing event-based CSV imports.
 * Use this pattern for transaction logs, payment history, stop reasons, etc.
 *
 * Key Characteristics:
 * - Append-only (never update existing records)
 * - No backfill logic
 * - Preserves exact dates/timestamps
 * - Deduplication by unique transaction ID
 *
 * Example Use Cases:
 * - Payment transactions
 * - Stop reasons (when/why subscribers canceled)
 * - Complaint logs
 * - Vacation hold requests
 *
 * @package CirculationDashboard
 * @version 1.0.0
 * @date December 8, 2025
 */

// Require authentication
require_once 'auth_check.php';
require_once 'includes/import-helpers.php';

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

// Error reporting
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
    // Connect to database
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Validate file upload
    if (!isset($_FILES['event_csv']) || $_FILES['event_csv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['event_csv'];

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

    // Process CSV
    $result = processEventCsv($pdo, $file['tmp_name'], $file['name']);

    // Calculate processing time
    $processing_time = round(microtime(true) - $start_time, 2) . ' seconds';

    // Return success
    echo json_encode([
        'success' => true,
        'events_imported' => $result['events_imported'],
        'duplicates_skipped' => $result['duplicates_skipped'],
        'date_range' => $result['date_range'],
        'processing_time' => $processing_time
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log("Event CSV Import Error: " . $e->getMessage());
}

/**
 * Process event CSV with append-only logic
 *
 * Example CSV Format (Payment History):
 * Transaction ID, Date, Subscriber ID, Amount, Payment Type
 * TXN12345, 2025-12-01, 10001, -169.99, CREDIT_CARD
 * TXN12346, 2025-12-01, 10002, -129.99, CHECK
 *
 * Example CSV Format (Stop Reasons):
 * Stop ID, Stop Date, Subscriber ID, Reason Code, Notes
 * STP5001, 2025-11-15, 10003, PRICE, Too expensive
 * STP5002, 2025-11-16, 10004, MOVED, Relocated out of area
 *
 * @param PDO $pdo Database connection
 * @param string $filepath Path to CSV file
 * @param string $filename Original filename
 * @return array Import statistics
 */
function processEventCsv($pdo, $filepath, $filename)
{
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        throw new Exception('Could not open uploaded file');
    }

    // Find header row (looking for 'Transaction ID' or 'Stop ID' or similar)
    // Adjust this based on your specific CSV format
    $header = findCsvHeader($handle, 'Transaction ID'); // Or 'Stop ID', 'Event ID', etc.

    if (!$header) {
        throw new Exception('Could not find header row. This does not appear to be a valid event CSV.');
    }

    // Validate required columns
    // Adjust this based on your specific CSV format
    $required_columns = ['Transaction ID', 'Date', 'Subscriber ID']; // Example
    $missing = validateCsvHeader($header, $required_columns);

    if (!empty($missing)) {
        throw new Exception('CSV missing required columns: ' . implode(', ', $missing));
    }

    // Map column indices
    $col_map = array_flip($header);

    // Skip decorator rows if present
    $first_row = skipDecoratorRows($handle);

    // Statistics
    $stats = [
        'events_imported' => 0,
        'duplicates_skipped' => 0,
        'min_date' => null,
        'max_date' => null
    ];

    // Prepare INSERT statement with duplicate key handling
    // Adjust table name and columns based on your schema
    $insert_stmt = $pdo->prepare("
        INSERT INTO payment_events (
            transaction_id,
            event_date,
            subscriber_id,
            amount,
            payment_type,
            source_filename,
            imported_at
        )
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE transaction_id = transaction_id  -- No-op, just skip duplicates
    ");

    // Process first row
    if ($first_row) {
        processEventRow($first_row, $col_map, $insert_stmt, $filename, $stats);
    }

    // Process remaining rows
    while (($row = fgetcsv($handle)) !== false) {
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        // Stop at footer section (if present)
        if (
            stripos($row[0] ?? '', 'Report Criteria') !== false ||
            stripos($row[0] ?? '', 'Total') !== false
        ) {
            break;
        }

        processEventRow($row, $col_map, $insert_stmt, $filename, $stats);
    }

    fclose($handle);

    // Format date range
    $date_range = 'unknown';
    if ($stats['min_date'] && $stats['max_date']) {
        $date_range = ($stats['min_date'] === $stats['max_date'])
            ? $stats['min_date']
            : "{$stats['min_date']} to {$stats['max_date']}";
    }

    return [
        'events_imported' => $stats['events_imported'],
        'duplicates_skipped' => $stats['duplicates_skipped'],
        'date_range' => $date_range
    ];
}

/**
 * Process a single event row
 *
 * @param array $row CSV row data
 * @param array $col_map Column name to index mapping
 * @param PDOStatement $stmt Prepared INSERT statement
 * @param string $filename Source filename
 * @param array &$stats Statistics array (passed by reference)
 */
function processEventRow($row, $col_map, $stmt, $filename, &$stats)
{
    try {
        // Extract fields (adjust based on your CSV structure)
        $transaction_id = trim($row[$col_map['Transaction ID']] ?? '');
        $event_date = trim($row[$col_map['Date']] ?? '');
        $subscriber_id = trim($row[$col_map['Subscriber ID']] ?? '');
        $amount = trim($row[$col_map['Amount']] ?? '0');
        $payment_type = trim($row[$col_map['Payment Type']] ?? 'UNKNOWN');

        // Validate required fields
        if (empty($transaction_id) || empty($event_date) || empty($subscriber_id)) {
            error_log("Skipping row with missing required fields");
            return;
        }

        // Parse and validate date
        $date_obj = DateTime::createFromFormat('Y-m-d', $event_date);
        if (!$date_obj) {
            // Try alternative format
            $date_obj = DateTime::createFromFormat('m/d/Y', $event_date);
        }

        if (!$date_obj) {
            error_log("Skipping row with invalid date: $event_date");
            return;
        }

        $normalized_date = $date_obj->format('Y-m-d');

        // Track date range
        if (!$stats['min_date'] || $normalized_date < $stats['min_date']) {
            $stats['min_date'] = $normalized_date;
        }
        if (!$stats['max_date'] || $normalized_date > $stats['max_date']) {
            $stats['max_date'] = $normalized_date;
        }

        // Execute insert
        $result = $stmt->execute([
            $transaction_id,
            $normalized_date,
            $subscriber_id,
            $amount,
            $payment_type,
            $filename
        ]);

        if ($result) {
            // Check if this was a new insert or duplicate
            if ($stmt->rowCount() > 0) {
                $stats['events_imported']++;
            } else {
                $stats['duplicates_skipped']++;
            }
        }
    } catch (Exception $e) {
        error_log("Error processing event row: " . $e->getMessage());
        // Continue processing other rows
    }
}
