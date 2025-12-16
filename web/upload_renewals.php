<?php

/**
 * Renewal Churn Data Import
 *
 * Imports Newzware "Renewal Churn Report by Issue" CSV files.
 * Tracks individual subscriber renewal/expiration events.
 *
 * Key Characteristics:
 * - Append-only (never update existing records)
 * - Deduplication by subscriber + paper + date
 * - Preserves exact renewal dates
 * - Tracks subscription type (Regular/Monthly/Comp)
 *
 * CSV Format:
 * Sub ID,Stat,Ed.,Issue #,Issue Date,
 * Reg Sub Expiring,Reg Sub Renewed,Reg Sub Stopped,Reg Sub %Renewed,
 * Mthy Sub Expiring,Mthy Sub Renewed,Mthy Sub Stopped,Mthy Sub %Renewed,
 * Comp Sub Expiring,Comp Sub Renewed,Comp Sub Stopped,Comp Sub %Renewed,
 * Total Expiring,Total Renewed,Total Stopped,Total %Renewed,Total% Churn
 *
 * @package CirculationDashboard
 * @version 1.0.0
 * @date    December 15, 2025
 */

// Require authentication (comment out for testing if needed)
// require_once 'auth_check.php';
require_once __DIR__ . '/includes/import-helpers.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(
        [
        'success' => false,
        'error' => 'Method not allowed. This endpoint only accepts POST requests.'
        ]
    );
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
    if (!isset($_FILES['renewal_csv']) || $_FILES['renewal_csv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred. Please select a Renewal Churn CSV file.');
    }

    $file = $_FILES['renewal_csv'];

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

    // Validate file size (max 50MB for large churn reports)
    if ($file['size'] > 50 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 50MB.');
    }

    // Process CSV
    $result = processRenewalCsv($pdo, $file['tmp_name'], $file['name']);

    // Calculate processing time
    $processing_time = round(microtime(true) - $start_time, 2) . ' seconds';

    // Return success
    echo json_encode(
        [
        'success' => true,
        'events_imported' => $result['events_imported'],
        'duplicates_skipped' => $result['duplicates_skipped'],
        'summaries_imported' => $result['summaries_imported'],
        'date_range' => $result['date_range'],
        'by_publication' => $result['by_publication'],
        'by_type' => $result['by_type'],
        'processing_time' => $processing_time
        ]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(
        [
        'success' => false,
        'error' => $e->getMessage()
        ]
    );
    error_log("Renewal Churn Import Error: " . $e->getMessage());
}

/**
 * Process renewal churn CSV with append-only logic
 *
 * @param  PDO    $pdo      Database connection
 * @param  string $filepath Path to CSV file
 * @param  string $filename Original filename
 * @return array<string, mixed> Import statistics
 */
function processRenewalCsv(PDO $pdo, string $filepath, string $filename): array
{
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        throw new Exception('Could not open uploaded file');
    }

    // Find header row (looking for 'Sub ID')
    $header = findCsvHeader($handle, 'Sub ID');

    if (!$header) {
        throw new Exception('Could not find header row. This does not appear to be a valid Renewal Churn Report (looking for "Sub ID" column).');
    }

    // Validate required columns
    $required_columns = ['Sub ID', 'Stat', 'Ed.', 'Issue Date'];
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
        'summaries_imported' => 0,
        'min_date' => null,
        'max_date' => null,
        'by_publication' => [],
        'by_type' => [
            'REGULAR' => 0,
            'MONTHLY' => 0,
            'COMPLIMENTARY' => 0
        ]
    ];

    // Prepare INSERT statement with duplicate key handling
    $insert_stmt = $pdo->prepare(
        "
        INSERT INTO renewal_events (
            source_filename,
            event_date,
            sub_num,
            paper_code,
            status,
            subscription_type,
            imported_at
        )
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE id = id  -- No-op, just skip duplicates
    "
    );

    // Prepare INSERT for daily summaries
    $summary_stmt = $pdo->prepare(
        "
        INSERT INTO churn_daily_summary (
            snapshot_date,
            paper_code,
            subscription_type,
            expiring_count,
            renewed_count,
            stopped_count,
            renewal_rate,
            churn_rate,
            calculated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            expiring_count = VALUES(expiring_count),
            renewed_count = VALUES(renewed_count),
            stopped_count = VALUES(stopped_count),
            renewal_rate = VALUES(renewal_rate),
            churn_rate = VALUES(churn_rate),
            calculated_at = NOW()
    "
    );

    // Process first row
    if ($first_row) {
        processRenewalRow($first_row, $col_map, $insert_stmt, $filename, $stats);
    }

    // Process remaining rows
    while (($row = fgetcsv($handle)) !== false) {
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        // Stop at footer/total rows (but not ISSUE summary rows)
        $first_cell = trim($row[0] ?? '');
        $second_cell = strtoupper(trim($row[1] ?? ''));

        // Process ISSUE summary rows (empty Sub ID + "ISSUE" in Stat column)
        if (empty($first_cell) && $second_cell === 'ISSUE') {
            processIssueSummaryRow($row, $col_map, $summary_stmt, $stats);
            continue;
        }

        // Stop at final totals or report footer
        if (
            stripos($first_cell, 'Total') !== false
            || stripos($first_cell, 'Report') !== false
        ) {
            break;
        }

        processRenewalRow($row, $col_map, $insert_stmt, $filename, $stats);
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
        'summaries_imported' => $stats['summaries_imported'],
        'date_range' => $date_range,
        'by_publication' => $stats['by_publication'],
        'by_type' => $stats['by_type']
    ];
}

/**
 * Process a single renewal row
 *
 * @param array<int, string|null> $row      CSV row data
 * @param array<string, int>      $col_map  Column name to index mapping
 * @param PDOStatement            $stmt     Prepared INSERT statement
 * @param string                  $filename Source filename
 * @param array<string, mixed>    &$stats   Statistics array (passed by reference)
 */
function processRenewalRow(array $row, array $col_map, PDOStatement $stmt, string $filename, array &$stats): void
{
    try {
        // Extract core fields
        $sub_id = trim($row[$col_map['Sub ID']] ?? '');
        $status = strtoupper(trim($row[$col_map['Stat']] ?? ''));
        $paper_code = trim($row[$col_map['Ed.'] ?? $col_map['Ed'] ?? ''] ?? '');
        $issue_date = trim($row[$col_map['Issue Date']] ?? '');

        // Validate required fields
        if (empty($sub_id) || empty($status) || empty($paper_code) || empty($issue_date)) {
            return; // Skip invalid rows
        }

        // Normalize status
        if (!in_array($status, ['RENEW', 'EXPIRE'])) {
            return; // Skip rows with invalid status
        }

        // Parse date (handle M/D/YY format from Newzware)
        $event_date = parseDate($issue_date);
        if (!$event_date) {
            error_log("Invalid date format: $issue_date");
            return;
        }

        // Determine subscription type based on which columns have data
        // Column layout: Sub ID (0), Stat (1), Ed. (2), Issue # (3), Issue Date (4),
        //   Reg Sub columns (5-8), Mthy Sub columns (9-12), Comp Sub columns (13-16)
        $reg_expiring = (int)($row[5] ?? 0);   // Reg Sub Expiring
        $mthy_expiring = (int)($row[9] ?? 0);  // Mthy Sub Expiring
        $comp_expiring = (int)($row[13] ?? 0); // Comp Sub Expiring

        if ($reg_expiring > 0) {
            $subscription_type = 'REGULAR';
        } elseif ($mthy_expiring > 0) {
            $subscription_type = 'MONTHLY';
        } elseif ($comp_expiring > 0) {
            $subscription_type = 'COMPLIMENTARY';
        } else {
            return; // Skip if we can't determine type
        }

        // Execute insert
        $result = $stmt->execute(
            [
            $filename,
            $event_date,
            $sub_id,
            $paper_code,
            $status,
            $subscription_type
            ]
        );

        // Track statistics
        if ($stmt->rowCount() > 0) {
            $stats['events_imported']++;
            $stats['by_type'][$subscription_type]++;

            // Track by publication
            if (!isset($stats['by_publication'][$paper_code])) {
                $stats['by_publication'][$paper_code] = 0;
            }
            $stats['by_publication'][$paper_code]++;

            // Track date range
            if ($stats['min_date'] === null || $event_date < $stats['min_date']) {
                $stats['min_date'] = $event_date;
            }
            if ($stats['max_date'] === null || $event_date > $stats['max_date']) {
                $stats['max_date'] = $event_date;
            }
        } else {
            $stats['duplicates_skipped']++;
        }
    } catch (Exception $e) {
        error_log("Error processing renewal row: " . $e->getMessage());
        // Continue processing other rows
    }
}

/**
 * Parse date in M/D/YY or M/D/YYYY format
 *
 * @param  string $date_str Date string
 * @return string|null Formatted date (Y-m-d) or null if invalid
 */
function parseDate(string $date_str): ?string
{
    if (empty($date_str)) {
        return null;
    }

    // Try M/D/YY or M/D/YYYY format
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date_str, $matches)) {
        $month = (int)$matches[1];
        $day = (int)$matches[2];
        $year = (int)$matches[3];

        // Convert 2-digit year to 4-digit
        if ($year < 100) {
            $year += ($year < 50) ? 2000 : 1900;
        }

        // Validate date
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    return null;
}

/**
 * Process ISSUE summary row and insert into churn_daily_summary
 *
 * ISSUE rows contain pre-aggregated daily metrics by subscription type:
 * "","ISSUE","TJ","","09/03/2024","12","11","1","91.67%","45","45","0","100.00%",...
 *
 * @param array<int, string|null> $row CSV row data
 * @param array<string, int> $col_map Column name to index mapping (unused but kept for consistency)
 * @param PDOStatement $stmt Prepared INSERT statement for summaries
 * @param array<string, mixed> &$stats Statistics array (passed by reference)
 * @return void
 */
function processIssueSummaryRow(array $row, array $col_map, PDOStatement $stmt, array &$stats): void
{
    try {
        // Extract core fields
        $paper_code = trim($row[2] ?? ''); // Ed. column
        $issue_date = trim($row[4] ?? ''); // Issue Date column

        if (empty($paper_code) || empty($issue_date)) {
            return; // Skip invalid rows
        }

        $event_date = parseDate($issue_date);
        if (!$event_date) {
            return;
        }

        // Column layout: Sub ID (0), Stat (1), Ed. (2), Issue # (3), Issue Date (4),
        //   Reg Sub columns (5-8), Mthy Sub columns (9-12), Comp Sub columns (13-16), Totals (17-21)

        // Insert summary for each subscription type that has data
        $types = [
            'REGULAR' => [
                'expiring' => (int)($row[5] ?? 0),
                'renewed' => (int)($row[6] ?? 0),
                'stopped' => (int)($row[7] ?? 0),
                'renewal_pct' => trim($row[8] ?? '')
            ],
            'MONTHLY' => [
                'expiring' => (int)($row[9] ?? 0),
                'renewed' => (int)($row[10] ?? 0),
                'stopped' => (int)($row[11] ?? 0),
                'renewal_pct' => trim($row[12] ?? '')
            ],
            'COMPLIMENTARY' => [
                'expiring' => (int)($row[13] ?? 0),
                'renewed' => (int)($row[14] ?? 0),
                'stopped' => (int)($row[15] ?? 0),
                'renewal_pct' => trim($row[16] ?? '')
            ]
        ];

        foreach ($types as $type => $data) {
            // Skip if no expirations for this type
            if ($data['expiring'] === 0) {
                continue;
            }

            // Parse renewal rate (strip % and convert to decimal)
            $renewal_rate = null;
            if (!empty($data['renewal_pct'])) {
                $renewal_rate = (float)str_replace(['%', ' '], '', $data['renewal_pct']);
            }

            // Calculate churn rate
            $churn_rate = null;
            if ($renewal_rate !== null) {
                $churn_rate = 100.0 - $renewal_rate;
            }

            // Insert summary
            $stmt->execute([
                $event_date,
                $paper_code,
                $type,
                $data['expiring'],
                $data['renewed'],
                $data['stopped'],
                $renewal_rate,
                $churn_rate
            ]);

            $stats['summaries_imported']++;
        }
    } catch (Exception $e) {
        error_log("Error processing ISSUE summary row: " . $e->getMessage());
        // Continue processing other rows
    }
}
