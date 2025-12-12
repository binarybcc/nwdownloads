<?php

/**
 * Example: Dimension Data Import (Pattern 3)
 *
 * This is a TEMPLATE for implementing dimension/reference table imports.
 * Use this pattern for small master lists that change infrequently.
 *
 * Key Characteristics:
 * - Full table replacement (TRUNCATE + INSERT)
 * - No versioning or history
 * - Small datasets only (<1,000 rows typically)
 * - Atomic transaction (all or nothing)
 *
 * Example Use Cases:
 * - Rate master (current subscription pricing)
 * - Publication list (active papers)
 * - Carrier route assignments
 * - Geographic zones
 * - Payment type codes
 * - Stop reason codes
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
    if (!isset($_FILES['dimension_csv']) || $_FILES['dimension_csv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['dimension_csv'];

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'])) {
        throw new Exception('Invalid file type. Please upload a CSV file');
    }

    // Validate file size (max 5MB for dimension tables)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 5MB for dimension tables');
    }

    // Process CSV
    $result = processDimensionCsv($pdo, $file['tmp_name']);

    // Calculate processing time
    $processing_time = round(microtime(true) - $start_time, 2) . ' seconds';

    // Return success
    echo json_encode([
        'success' => true,
        'records_loaded' => $result['records_loaded'],
        'table' => $result['table'],
        'processing_time' => $processing_time
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log("Dimension CSV Import Error: " . $e->getMessage());
}

/**
 * Process dimension CSV with full replacement logic
 *
 * Example CSV Format (Rate Master):
 * Rate Code, Rate Name, Amount, Type, Active
 * DIGONLY, Digital Only Annual, 169.99, DIGITAL, 1
 * MAILDG, Mail + Digital Bundle, 169.99, COMBO, 1
 * PRNTONLY, Print Only Annual, 129.99, PRINT, 1
 *
 * Example CSV Format (Publication List):
 * Paper Code, Paper Name, Business Unit, Active
 * TJ, The Journal, Wyoming, 1
 * TA, The Advertiser, Michigan, 1
 * TR, The Ranger, Wyoming, 1
 *
 * @param PDO $pdo Database connection
 * @param string $filepath Path to CSV file
 * @return array Import statistics
 */
function processDimensionCsv($pdo, $filepath)
{
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        throw new Exception('Could not open uploaded file');
    }

    // Find header row
    // Adjust key column based on your CSV format
    $header = findCsvHeader($handle, 'Rate Code'); // Or 'Paper Code', 'Zone Code', etc.

    if (!$header) {
        throw new Exception('Could not find header row. This does not appear to be a valid dimension CSV.');
    }

    // Validate required columns
    // Adjust based on your specific CSV format
    $required_columns = ['Rate Code', 'Rate Name', 'Amount']; // Example
    $missing = validateCsvHeader($header, $required_columns);

    if (!empty($missing)) {
        throw new Exception('CSV missing required columns: ' . implode(', ', $missing));
    }

    // Map column indices
    $col_map = array_flip($header);

    // Skip decorator rows if present
    $first_row = skipDecoratorRows($handle);

    // Collect all rows first (before truncating)
    $rows = [];

    if ($first_row) {
        $rows[] = $first_row;
    }

    while (($row = fgetcsv($handle)) !== false) {
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        // Stop at footer
        if (
            stripos($row[0] ?? '', 'Report Criteria') !== false ||
            stripos($row[0] ?? '', 'Total') !== false
        ) {
            break;
        }

        $rows[] = $row;
    }

    fclose($handle);

    // Validate we have data
    if (empty($rows)) {
        throw new Exception('No data found in CSV file');
    }

    // Start transaction (atomic replacement)
    $pdo->beginTransaction();

    try {
        // TRUNCATE table (remove all existing data)
        // Adjust table name based on your schema
        $table_name = 'rate_master'; // Example: 'rate_master', 'publications', 'zones', etc.
        $pdo->exec("TRUNCATE TABLE $table_name");

        // Prepare INSERT statement
        // Adjust columns based on your schema
        $insert_stmt = $pdo->prepare("
            INSERT INTO $table_name (
                rate_code,
                rate_name,
                amount,
                rate_type,
                active
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        // Insert all rows
        $records_loaded = 0;
        foreach ($rows as $row) {
            try {
                // Extract fields (adjust based on your CSV structure)
                $code = trim($row[$col_map['Rate Code']] ?? '');
                $name = trim($row[$col_map['Rate Name']] ?? '');
                $amount = trim($row[$col_map['Amount']] ?? '0');
                $type = trim($row[$col_map['Type']] ?? 'UNKNOWN');
                $active = trim($row[$col_map['Active']] ?? '1');

                // Validate required fields
                if (empty($code) || empty($name)) {
                    error_log("Skipping row with missing required fields");
                    continue;
                }

                // Execute insert
                $insert_stmt->execute([
                    $code,
                    $name,
                    $amount,
                    $type,
                    $active
                ]);

                $records_loaded++;
            } catch (Exception $e) {
                // Roll back and re-throw on any error
                $pdo->rollBack();
                throw new Exception("Error inserting row: " . $e->getMessage());
            }
        }

        // Commit transaction
        $pdo->commit();

        return [
            'records_loaded' => $records_loaded,
            'table' => $table_name
        ];
    } catch (Exception $e) {
        // Roll back on any error
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Example: Create dimension table schema
 *
 * Run this SQL to create a dimension table:
 *
 * CREATE TABLE rate_master (
 *     rate_code VARCHAR(50) PRIMARY KEY,
 *     rate_name VARCHAR(255) NOT NULL,
 *     amount DECIMAL(10,2) NOT NULL,
 *     rate_type VARCHAR(50),
 *     active TINYINT(1) DEFAULT 1,
 *     last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *     INDEX idx_active (active),
 *     INDEX idx_type (rate_type)
 * );
 *
 * CREATE TABLE publications (
 *     paper_code VARCHAR(10) PRIMARY KEY,
 *     paper_name VARCHAR(100) NOT NULL,
 *     business_unit VARCHAR(50),
 *     active TINYINT(1) DEFAULT 1,
 *     display_order INT,
 *     last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 * );
 *
 * CREATE TABLE carrier_routes (
 *     route_code VARCHAR(50) PRIMARY KEY,
 *     route_name VARCHAR(255),
 *     carrier_name VARCHAR(255),
 *     zone_code VARCHAR(50),
 *     active TINYINT(1) DEFAULT 1,
 *     last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 * );
 */
