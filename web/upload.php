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
 * Date: 2025-12-02
 */

// Require authentication
require_once 'auth_check.php';
require_once 'SimpleCache.php';

use CirculationDashboard\SimpleCache;

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
ini_set('display_errors', 0);
// Don't display errors to user
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

    // Parse CSV and process
    $result = processAllSubscriberReport($pdo, $file['tmp_name'], $file['name']);

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

/**
 * Extract snapshot_date from filename
 *
 * Filename format: AllSubscriberReportYYYYMMDDHHMMSS.csv
 * Example: AllSubscriberReport20251206164201.csv → 2025-12-06
 *
 * The filename timestamp is the source of truth for the snapshot date.
 *
 * @param string $filename Original uploaded filename
 * @return string snapshot_date in 'Y-m-d' format, or current date if parsing fails
 */
function extractSnapshotDateFromFilename($filename)
{

    // Pattern: AllSubscriberReport + YYYYMMDDHHMMSS + .csv
    // Extract the 14-digit timestamp: YYYYMMDDHHMMSS
    if (preg_match('/AllSubscriberReport(\d{14})\.csv$/i', $filename, $matches)) {
        $timestamp = $matches[1];
// Extract YYYYMMDD (first 8 digits)
        $dateStr = substr($timestamp, 0, 8);
// Format: YYYYMMDD → YYYY-MM-DD
        $year = substr($dateStr, 0, 4);
        $month = substr($dateStr, 4, 2);
        $day = substr($dateStr, 6, 2);
        return "$year-$month-$day";
    }

    // Fallback: If filename doesn't match expected pattern, use current date
    error_log("Warning: Could not extract date from filename '$filename', using current date");
    return date('Y-m-d');
}

/**
 * Process All Subscriber Report CSV with UPSERT logic
 *
 * Handles Newzware CSV format with:
 * - Report header rows (date, company info, settings)
 * - Column headers (containing "SUB NUM")
 * - Decorative separator rows
 * - Actual data rows
 * - Footer section (starting with "Report Criteria")
 */
function processAllSubscriberReport($pdo, $filepath, $filename)
{
    // Step 1: Save raw CSV to raw_uploads table (source of truth)
    $raw_csv_data = file_get_contents($filepath);
    if ($raw_csv_data === false) {
        throw new Exception('Could not read uploaded file');
    }

    $file_size = filesize($filepath);
    $file_hash = hash('sha256', $raw_csv_data);

    // We'll get snapshot_date later, so start with pending status
    $stmt = $pdo->prepare("
        INSERT INTO raw_uploads (
            filename, file_size, file_hash, snapshot_date,
            row_count, subscriber_count, raw_csv_data,
            processing_status, uploaded_by, ip_address, user_agent
        ) VALUES (
            :filename, :file_size, :file_hash, '1970-01-01',
            0, 0, :raw_csv_data,
            'pending', 'web_interface', :ip, :user_agent
        )
    ");

    $stmt->execute([
        'filename' => $filename,
        'file_size' => $file_size,
        'file_hash' => $file_hash,
        'raw_csv_data' => $raw_csv_data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $upload_id = $pdo->lastInsertId();

    // Step 2: Process CSV as normal
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        throw new Exception('Could not open uploaded file');
    }

    // Find the header row (contains "SUB NUM")
    $header = null;
    $header_line = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $header_line++;
    // Look for "SUB NUM" in any column (case-insensitive)
        foreach ($row as $cell) {
            if (stripos($cell, 'SUB NUM') !== false) {
                $header = $row;
                break 2;
            // Break out of both loops
            }
        }

        // Safety: Don't search more than 50 lines
        if ($header_line > 50) {
            break;
        }
    }

    if (!$header) {
        throw new Exception('Could not find header row (looking for "SUB NUM" column). This does not appear to be an All Subscriber Report.');
    }

    // Trim whitespace from column names
    $header = array_map('trim', $header);
// Validate header format - check for required columns
    // Note: Column names may have extra spaces, so we check if they exist anywhere
    $required_columns = ['SUB NUM', 'Ed', 'ISS', 'DEL'];
    $missing_columns = [];
    foreach ($required_columns as $required) {
        $found = false;
        foreach ($header as $col) {
        // Check if the required column exists (case-insensitive, ignore extra spaces)
            if (strtoupper(trim($col)) === strtoupper($required)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missing_columns[] = $required;
        }
    }

    if (!empty($missing_columns)) {
        throw new Exception('CSV does not appear to be an All Subscriber Report (missing required columns: ' . implode(', ', $missing_columns) . ')');
    }

    // Map column indices
    $col_map = array_flip($header);
// Skip decorative separator rows (typically 2-3 rows of dashes/equals after header)
    $rows_skipped = 0;
    while (($row = fgetcsv($handle)) !== false && $rows_skipped < 5) {
    // Check if row is decorative (all dashes, equals, or mostly empty)
        $first_cell = trim($row[0] ?? '');
    // If first cell starts with dash or is empty but we haven't seen data yet, skip
        if (empty($first_cell) || preg_match('/^[-=_]+$/', $first_cell)) {
            $rows_skipped++;
            continue;
        }

        // Found first data row, rewind one line so it gets processed
        // We can't actually rewind fgetcsv, so we'll process this row immediately
        // Store it for processing in main loop
        $first_data_row = $row;
        break;
    }

    // Tracking variables
    $snapshots = [];
    $subscriber_records = [];
// NEW: Individual subscriber records
    $stats = [
        'new_records' => 0,
        'updated_records' => 0,
        'total_processed' => 0,
        'subscriber_records_imported' => 0,  // NEW
        'min_date' => null,
        'max_date' => null,
        'by_business_unit' => []
    ];
// Extract snapshot_date from filename (source of truth)
    // Filename format: AllSubscriberReport20251206164201.csv → 2025-12-06
    // NOTE: Filename date is when report was RUN, but data is for PREVIOUS week
    $original_filename = $_FILES['allsubscriber']['name'] ?? 'unknown';
    $file_date = extractSnapshotDateFromFilename($original_filename);

    // Subtract 7 days to get the week the data actually represents
    // (Report run on Dec 8 = data for week ending Dec 7 = Week 49: Dec 1-7)
    $dt = new DateTime($file_date);
    $dt->modify('-7 days');
    $snapshot_date = $dt->format('Y-m-d');

    // Calculate week number and year from adjusted date using ISO 8601
    $week_num = (int)$dt->format('W');  // ISO week number (1-53)
    $year = (int)$dt->format('o');      // ISO year (not 'Y' - important for year boundaries!)
// Store original upload info for audit trail
    $today = date('Y-m-d');
    $original_upload_date = $today;
// Process each row
    $row_num = $header_line;
// Start counting from header line

    // Helper function to check if row is footer/metadata
    $isFooterRow = function ($row) {

        $first_cell = trim($row[0] ?? '');
        // Check for footer markers
        if (
            stripos($first_cell, 'Report Criteria') !== false ||
            stripos($first_cell, 'Report Start:') !== false ||
            stripos($first_cell, 'Copies:Issues') !== false ||
            stripos($first_cell, 'Edition Code') !== false
        ) {
            return true;
        }
        return false;
    };
// Process first data row if we found one
    $rows_to_process = [];
    if (isset($first_data_row)) {
        $rows_to_process[] = $first_data_row;
    }

    // Read remaining rows
    while (($row = fgetcsv($handle)) !== false) {
        $row_num++;
// Stop at footer section
        if ($isFooterRow($row)) {
            break;
        }

        // Skip empty rows
        if (count(array_filter($row)) === 0) {
            continue;
        }

        $rows_to_process[] = $row;
    }

    // Now process all collected rows
    foreach ($rows_to_process as $row) {
        try {
        // Extract all subscriber fields
            // Core subscriber identification
            $sub_num = trim($row[$col_map['SUB NUM']] ?? '');
            $paper_code = trim($row[$col_map['Ed']] ?? '');
            $name = isset($col_map['Name']) ? trim($row[$col_map['Name']] ?? '') : '';
        // Subscription details
            $route = isset($col_map['Route']) ? trim($row[$col_map['Route']] ?? '') : '';
            $delivery_type = trim($row[$col_map['DEL']] ?? '');
            $zone = isset($col_map['Zone']) ? trim($row[$col_map['Zone']] ?? '') : '';
            $subscription_length = isset($col_map['LEN']) ? trim($row[$col_map['LEN']] ?? '') : '';
            $payment_status = isset($col_map['PAY']) ? trim($row[$col_map['PAY']] ?? '') : '';
        // Dates
            $begin_date = isset($col_map['BEGIN']) ? trim($row[$col_map['BEGIN']] ?? '') : '';
            $paid_thru = isset($col_map['Paid Thru']) ? trim($row[$col_map['Paid Thru']] ?? '') : '';
        // Financial
            $daily_rate = isset($col_map['DAILY RATE']) ? trim($row[$col_map['DAILY RATE']] ?? '') : '';
            $last_payment = isset($col_map['LAST PAY']) ? trim($row[$col_map['LAST PAY']] ?? '') : '';
        // Contact information (NEW)
            $address = isset($col_map['Address']) ? trim($row[$col_map['Address']] ?? '') : '';
            $city_state_postal = isset($col_map['CITY  STATE  POSTAL']) ? trim($row[$col_map['CITY  STATE  POSTAL']] ?? '') : '';
            $phone = isset($col_map['Phone']) ? trim($row[$col_map['Phone']] ?? '') : '';
            $email = isset($col_map['Email']) ? trim($row[$col_map['Email']] ?? '') : '';
        // Additional fields (NEW)
            $abc = isset($col_map['ABC']) ? trim($row[$col_map['ABC']] ?? '') : '';
            $issue_code = isset($col_map['ISS']) ? trim($row[$col_map['ISS']] ?? '') : '';
            $login_id = isset($col_map['Login ID']) ? trim($row[$col_map['Login ID']] ?? '') : '';
            $last_login = isset($col_map['Last Login']) ? trim($row[$col_map['Last Login']] ?? '') : '';
        // Skip invalid rows
            if (empty($paper_code) || empty($sub_num)) {
                continue;
            }

            // Use snapshot_date extracted from filename
            // Already set: $snapshot_date = extractSnapshotDateFromFilename($original_filename);

            // Filter: Only 2025-01-01 onwards
            if ($snapshot_date < '2025-01-01') {
                continue;
            }

            // Determine business unit and paper name
            $paper_info = getPaperInfo($paper_code);
            $business_unit = $paper_info['business_unit'];
            $paper_name = $paper_info['name'];
        // Initialize snapshot for this week/paper if not exists
            $key = $week_num . '|' . $year . '|' . $paper_code;
            if (!isset($snapshots[$key])) {
                $snapshots[$key] = [
                    'snapshot_date' => $snapshot_date,
                    'week_num' => $week_num,
                    'year' => $year,
                    'paper_code' => $paper_code,
                    'paper_name' => $paper_name,
                    'business_unit' => $business_unit,
                    'total_active' => 0,
                    'mail_delivery' => 0,
                    'carrier_delivery' => 0,
                    'digital_only' => 0,
                    'on_vacation' => 0
                ];
            }

            // Count subscribers
            $snapshots[$key]['total_active']++;
        // Count by delivery type
            switch (strtoupper($delivery_type)) {
                case 'MAIL':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              $snapshots[$key]['mail_delivery']++;

                    break;
                case 'CARR':
                case 'CARRIER':
                    $snapshots[$key]['carrier_delivery']++;

                    break;
                case 'INTE':
                case 'INTERNET':
                case 'DIGITAL':
                case 'EMAI':
                case 'EMAIL':
                    $snapshots[$key]['digital_only']++;

                    break;
            }

            // Count vacations
            $on_vacation = (stripos($zone, 'VAC') !== false || stripos($zone, 'VACATION') !== false);
            if ($on_vacation) {
                $snapshots[$key]['on_vacation']++;
            }

            // NEW: Store individual subscriber record
            $subscriber_records[] = [
                'snapshot_date' => $snapshot_date,
                'week_num' => $week_num,
                'year' => $year,
                'sub_num' => $sub_num,
                'paper_code' => $paper_code,
                'paper_name' => $paper_name,
                'business_unit' => $business_unit,
                'name' => $name,
                'route' => $route,
                'rate_name' => $zone,  // Zone column = rate name
                'subscription_length' => $subscription_length,
                'delivery_type' => $delivery_type,
                'payment_status' => $payment_status,
                'begin_date' => parseDate($begin_date),
                'paid_thru' => parseDate($paid_thru),
                'daily_rate' => !empty($daily_rate) ? floatval($daily_rate) : null,
                'on_vacation' => $on_vacation ? 1 : 0,  // Convert boolean to integer
                // NEW: Additional fields
                'address' => $address,
                'city_state_postal' => $city_state_postal,
                'abc' => $abc,
                'issue_code' => $issue_code,
                'last_payment_amount' => !empty($last_payment) ? floatval($last_payment) : null,
                'phone' => $phone,
                'email' => $email,
                'login_id' => $login_id,
                'last_login' => parseDate($last_login)
            ];
        } catch (Exception $e) {
        // Log error but continue processing
            error_log("Row $row_num error: " . $e->getMessage());
        }
    }

    fclose($handle);
    if (empty($snapshots)) {
        throw new Exception('No valid data found in CSV file (or all data is before 2025-01-01)');
    }

    // SOFT BACKFILL ALGORITHM
    // Rule: "Latest filename date wins, backfills backward until hitting existing data"
    //
    // Logic:
    // 1. Upload date from filename (source_date) is the authority
    // 2. Replace any data for this week from older uploads
    // 3. Backfill backward week-by-week until:
    //    - Hit Oct 1, 2025 (minimum date), OR
    //    - Hit a week with data from a NEWER upload (source_date > this upload)
    // 4. Track: source_filename, source_date, is_backfilled, backfill_weeks

    $pdo->beginTransaction();
    try {
        // Determine backfill range (start of Week 48 - first real data week)
        $min_backfill_date = '2025-11-24';
        $min_backfill_week_year = getWeekAndYear($min_backfill_date);
        $min_backfill_week = $min_backfill_week_year['week'];
        $min_backfill_year = $min_backfill_week_year['year'];

        // Find which weeks need to be filled/replaced
        $weeks_to_process = [];

        // Start from upload week, work BACKWARD ONLY
        $current_week = $week_num;
        $current_year = $year;
        $weeks_back = 0;

        while (true) {
            // Check if we've reached the minimum date
            if (
                $current_year < $min_backfill_year ||
                ($current_year == $min_backfill_year && $current_week < $min_backfill_week)
            ) {
                error_log("🛑 Backfill stopped at minimum date (Oct 1, 2025)");
                break; // Hit Oct 1, 2025 limit
            }

            // Check if this week has data
            $check_stmt = $pdo->prepare("
                SELECT source_date, is_backfilled
                FROM daily_snapshots
                WHERE week_num = ? AND year = ?
                LIMIT 1
            ");
            $check_stmt->execute([$current_week, $current_year]);
            $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && $existing['source_date']) {
                // Week has data
                // Only replace if this is the UPLOAD WEEK (weeks_back = 0) and existing is older
                // For backfilled weeks, stop when hitting any existing data
                if ($weeks_back == 0 && $existing['source_date'] < $file_date) {
                    // This is the upload week and existing data is older - replace it
                    error_log("♻️ Replacing upload week $current_week, $current_year (old data from {$existing['source_date']}, new from $file_date)");
                } else {
                    // Either this is a backfill week hitting existing data, OR upload week has newer data
                    error_log("🛑 Backfill stopped at Week $current_week, $current_year (has data from {$existing['source_date']})");
                    break;
                }
            }

            // Add this week to processing list
            $weeks_to_process[] = [
                'week' => $current_week,
                'year' => $current_year,
                'weeks_offset' => $weeks_back,
                'is_backfilled' => ($weeks_back > 0)
            ];

            // Move to previous week
            $weeks_back++;
            $current_week--;
            if ($current_week < 1) {
                // Wrapped to previous year
                $current_year--;
                $current_week = 52; // Approximate - ISO weeks are complex at year boundaries
            }
        }

        // Reverse so we process oldest to newest (cleaner for logging)
        $weeks_to_process = array_reverse($weeks_to_process);

        if (empty($weeks_to_process)) {
            throw new Exception('No weeks to process - all data is from newer uploads');
        }

        $total_weeks_processed = count($weeks_to_process);
        $total_backfilled = 0;
        $total_real = 0;

        // Step 2: Process each week (delete old data, insert new)
        foreach ($weeks_to_process as $week_info) {
            $target_week = $week_info['week'];
            $target_year = $week_info['year'];
            $is_backfilled = $week_info['is_backfilled'];
            $weeks_offset = $week_info['weeks_offset'];

            // Calculate snapshot_date for this specific week (first day of week)
            // Use ISO 8601: Week 1 is the week with first Thursday of year
            $target_snapshot_date = (new DateTime())
                ->setISODate($target_year, $target_week, 1) // Monday of target week
                ->format('Y-m-d');

            // Delete existing data for this week (if any)
            $delete_daily = $pdo->prepare("DELETE FROM daily_snapshots WHERE week_num = ? AND year = ?");
            $delete_daily->execute([$target_week, $target_year]);

            $delete_subscriber = $pdo->prepare("DELETE FROM subscriber_snapshots WHERE week_num = ? AND year = ?");
            $delete_subscriber->execute([$target_week, $target_year]);

            // Prepare INSERT statement for daily_snapshots with source tracking
            $stmt = $pdo->prepare("
                INSERT INTO daily_snapshots (
                    snapshot_date, week_num, year, paper_code, paper_name, business_unit,
                    total_active, deliverable, mail_delivery, carrier_delivery, digital_only, on_vacation,
                    source_filename, source_date, is_backfilled, backfill_weeks
                ) VALUES (
                    :snapshot_date, :week_num, :year, :paper_code, :paper_name, :business_unit,
                    :total_active, :deliverable, :mail_delivery, :carrier_delivery, :digital_only, :on_vacation,
                    :source_filename, :source_date, :is_backfilled, :backfill_weeks
                )
            ");

            // Insert snapshots for this week
            foreach ($snapshots as $snapshot) {
                // Use calculated snapshot_date for this week (not filename date)
                $snapshot['snapshot_date'] = $target_snapshot_date;

                // Add week/year to snapshot
                $snapshot['week_num'] = $target_week;
                $snapshot['year'] = $target_year;

                // Calculate deliverable (total_active - on_vacation)
                $snapshot['deliverable'] = $snapshot['total_active'] - $snapshot['on_vacation'];

                // Add source tracking (source_date is the file date, NOT adjusted snapshot_date)
                $snapshot['source_filename'] = $original_filename;
                $snapshot['source_date'] = $file_date;  // Original filename date (when report was run)
                $snapshot['is_backfilled'] = $is_backfilled ? 1 : 0;
                $snapshot['backfill_weeks'] = $weeks_offset;

                // Execute insert
                $stmt->execute($snapshot);

                // Track stats
                if ($is_backfilled) {
                    $stats['updated_records']++;
                    $total_backfilled++;
                } else {
                    $stats['new_records']++;
                    $total_real++;
                }
                $stats['total_processed']++;

                // Track date range
                if ($stats['min_date'] === null || $snapshot['snapshot_date'] < $stats['min_date']) {
                    $stats['min_date'] = $snapshot['snapshot_date'];
                }
                if ($stats['max_date'] === null || $snapshot['snapshot_date'] > $stats['max_date']) {
                    $stats['max_date'] = $snapshot['snapshot_date'];
                }

                // Track by business unit
                $bu = $snapshot['business_unit'];
                if (!isset($stats['by_business_unit'][$bu])) {
                    $stats['by_business_unit'][$bu] = ['count' => 0, 'papers' => [], 'total_subs' => 0];
                }
                $stats['by_business_unit'][$bu]['count']++;
                $stats['by_business_unit'][$bu]['total_subs'] += $snapshot['total_active'];
                if (!in_array($snapshot['paper_code'], $stats['by_business_unit'][$bu]['papers'])) {
                    $stats['by_business_unit'][$bu]['papers'][] = $snapshot['paper_code'];
                }
            }

            // Step 3: Insert subscriber-level records for this week
            if (!empty($subscriber_records)) {
                $sub_stmt = $pdo->prepare("
                    INSERT INTO subscriber_snapshots (
                        upload_id, snapshot_date, week_num, year, sub_num, paper_code, paper_name, business_unit,
                        name, route, rate_name, subscription_length, delivery_type,
                        payment_status, begin_date, paid_thru, daily_rate, on_vacation,
                        address, city_state_postal, abc, issue_code, last_payment_amount,
                        phone, email, login_id, last_login,
                        source_filename, source_date, is_backfilled, backfill_weeks
                    ) VALUES (
                        :upload_id, :snapshot_date, :week_num, :year, :sub_num, :paper_code, :paper_name, :business_unit,
                        :name, :route, :rate_name, :subscription_length, :delivery_type,
                        :payment_status, :begin_date, :paid_thru, :daily_rate, :on_vacation,
                        :address, :city_state_postal, :abc, :issue_code, :last_payment_amount,
                        :phone, :email, :login_id, :last_login,
                        :source_filename, :source_date, :is_backfilled, :backfill_weeks
                    )
                ");

                foreach ($subscriber_records as $sub) {
                    // Link to raw upload
                    $sub['upload_id'] = $upload_id;

                    // Use calculated snapshot_date for this week
                    $sub['snapshot_date'] = $target_snapshot_date;

                    // Add week/year
                    $sub['week_num'] = $target_week;
                    $sub['year'] = $target_year;

                    // Add source tracking (source_date is the file date, NOT adjusted snapshot_date)
                    $sub['source_filename'] = $original_filename;
                    $sub['source_date'] = $file_date;  // Original filename date (when report was run)
                    $sub['is_backfilled'] = $is_backfilled ? 1 : 0;
                    $sub['backfill_weeks'] = $weeks_offset;

                    $sub_stmt->execute($sub);
                    $stats['subscriber_records_imported']++;
                }
            }
        }

        // Log backfill summary
        error_log("✅ SoftBackfill complete: $total_weeks_processed weeks processed ($total_real real, $total_backfilled backfilled)");

            $pdo->commit();

        // Update raw_uploads with final metadata
        $update_stmt = $pdo->prepare("
            UPDATE raw_uploads SET
                snapshot_date = :snapshot_date,
                row_count = :row_count,
                subscriber_count = :subscriber_count,
                processed_at = NOW(),
                processing_status = 'completed'
            WHERE upload_id = :upload_id
        ");

        $update_stmt->execute([
            'snapshot_date' => $stats['max_date'],
            'row_count' => $stats['total_processed'],
            'subscriber_count' => $stats['subscriber_records_imported'],
            'upload_id' => $upload_id
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Mark raw upload as failed
        try {
            $fail_stmt = $pdo->prepare("
                UPDATE raw_uploads SET
                    processing_status = 'failed',
                    processing_errors = :error
                WHERE upload_id = :upload_id
            ");
            $fail_stmt->execute([
                'error' => $e->getMessage(),
                'upload_id' => $upload_id
            ]);
        } catch (Exception $ignored) {
            // Ignore errors updating the error status
        }

        throw $e;
    }

    // Generate summary HTML
    $summary_html = '';
    foreach ($stats['by_business_unit'] as $bu => $data) {
        $papers = implode(', ', $data['papers']);
        $summary_html .= "<div class='border-l-4 border-blue-500 pl-3 mb-2'>";
        $summary_html .= "<strong>$bu</strong>: {$data['total_subs']} subscribers<br>";
        $summary_html .= "<span class='text-gray-600 text-xs'>Papers: $papers ({$data['count']} snapshots)</span>";
        $summary_html .= "</div>";
    }

    return [
        'date_range' => $stats['min_date'] . ' to ' . $stats['max_date'],
        'new_records' => $stats['new_records'],
        'updated_records' => $stats['updated_records'],
        'total_processed' => $stats['total_processed'],
        'summary_html' => $summary_html
    ];
}

/**
 * Get paper information
 */
function getPaperInfo($paper_code)
{

    $papers = [
        'TJ' => ['name' => 'The Journal', 'business_unit' => 'South Carolina'],
        'TA' => ['name' => 'The Advertiser', 'business_unit' => 'Michigan'],
        'TR' => ['name' => 'The Ranger', 'business_unit' => 'Wyoming'],
        'LJ' => ['name' => 'The Lander Journal', 'business_unit' => 'Wyoming'],
        'WRN' => ['name' => 'Wind River News', 'business_unit' => 'Wyoming'],
        'FN' => ['name' => 'Former News', 'business_unit' => 'Sold']
    ];
    return $papers[$paper_code] ?? ['name' => $paper_code, 'business_unit' => 'Unknown'];
}

/**
 * Parse various date formats from Newzware CSV
 * Handles formats like: 6/5/25, 12/4/25, etc.
 */
function parseDate($date_string)
{

    if (empty($date_string)) {
        return null;
    }

    // Try parsing M/D/Y format (6/5/25)
    $date_string = trim($date_string);
// Handle M/D/Y or M/D/YY format
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $date_string, $matches)) {
        $month = $matches[1];
        $day = $matches[2];
        $year = $matches[3];
// Convert 2-digit year to 4-digit (assume 2000s)
        if (strlen($year) == 2) {
            $year = '20' . $year;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    // Try other common formats
    $timestamp = strtotime($date_string);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}

/**
 * Get ISO week number and year from date
 * Used for softBackfill system
 *
 * @param string $date Date in Y-m-d format
 * @return array ['week' => int, 'year' => int]
 */
function getWeekAndYear($date)
{
    $dt = new DateTime($date);
    return [
        'week' => (int)$dt->format('W'),  // ISO week number
        'year' => (int)$dt->format('Y')   // Year
    ];
}
