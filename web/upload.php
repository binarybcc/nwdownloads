<?php
/**
 * All Subscriber Report Upload and Import Handler
 *
 * Processes AllSubscriberReport CSV files with UPSERT logic:
 * - New snapshots: INSERT
 * - Existing snapshots: UPDATE
 * - Only imports data from 2025-01-01 onwards
 *
 * Date: 2025-12-02
 */

// Require authentication
require_once 'auth_check.php';

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors to user
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
    $result = processAllSubscriberReport($pdo, $file['tmp_name']);

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
        'summary_html' => $result['summary_html']
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
 * Calculate snapshot_date based on Sunday boundary logic
 *
 * Complete week: Monday 8am - Saturday 11:59pm
 * Safe export window: Saturday 11:59pm - Monday 7:59am (includes Sunday)
 *
 * @param string $uploadDateTime Format: 'Y-m-d H:i:s'
 * @return string snapshot_date in 'Y-m-d' format (always a Sunday)
 */
function calculateSnapshotDate($uploadDateTime) {
    $timestamp = strtotime($uploadDateTime);
    $dayOfWeek = (int)date('N', $timestamp); // 1=Monday, 7=Sunday
    $hour = (int)date('G', $timestamp); // 0-23

    // Sunday → use today (already the week ending)
    if ($dayOfWeek == 7) {
        return date('Y-m-d', $timestamp);
    }

    // Saturday → use tomorrow (this week's Sunday)
    if ($dayOfWeek == 6) {
        return date('Y-m-d', strtotime('+1 day', $timestamp));
    }

    // Monday before 8am → use yesterday (still in safe window, last week's Sunday)
    if ($dayOfWeek == 1 && $hour < 8) {
        return date('Y-m-d', strtotime('-1 day', $timestamp));
    }

    // Monday 8am+ or Tue-Fri → current week incomplete, go back to PREVIOUS week's Sunday
    // Logic: Current week started Mon 8am, so any upload after that needs previous week
    // Days back = dayOfWeek (to get to this week's Sunday) + 7 (to go back one more week)
    $daysBack = $dayOfWeek + 7;
    return date('Y-m-d', strtotime("-$daysBack days", $timestamp));
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
function processAllSubscriberReport($pdo, $filepath) {
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
                break 2;  // Break out of both loops
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
    $subscriber_records = [];  // NEW: Individual subscriber records
    $stats = [
        'new_records' => 0,
        'updated_records' => 0,
        'total_processed' => 0,
        'subscriber_records_imported' => 0,  // NEW
        'min_date' => null,
        'max_date' => null,
        'by_business_unit' => []
    ];

    // Calculate snapshot_date based on Sunday boundary logic
    // Complete week: Mon 8am - Sat 11:59pm
    // Safe export window: Sat 11:59pm - Mon 7:59am
    // Rule: Export in safe window → this Sunday, otherwise → previous Sunday
    $today = date('Y-m-d');
    $upload_datetime = date('Y-m-d H:i:s'); // Current date/time
    $snapshot_date = calculateSnapshotDate($upload_datetime);

    // Store original upload info for audit trail
    $original_upload_date = $today;
    $original_filename = $_FILES['allsubscriber']['name'] ?? 'unknown';

    // Process each row
    $row_num = $header_line;  // Start counting from header line

    // Helper function to check if row is footer/metadata
    $isFooterRow = function($row) {
        $first_cell = trim($row[0] ?? '');
        // Check for footer markers
        if (stripos($first_cell, 'Report Criteria') !== false ||
            stripos($first_cell, 'Report Start:') !== false ||
            stripos($first_cell, 'Copies:Issues') !== false ||
            stripos($first_cell, 'Edition Code') !== false) {
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

            // Use calculated snapshot_date (Sunday boundary logic applied above)
            // Already set: $snapshot_date = calculateSnapshotDate($upload_datetime);

            // Filter: Only 2025-01-01 onwards
            if ($snapshot_date < '2025-01-01') {
                continue;
            }

            // Determine business unit and paper name
            $paper_info = getPaperInfo($paper_code);
            $business_unit = $paper_info['business_unit'];
            $paper_name = $paper_info['name'];

            // Initialize snapshot for this date/paper if not exists
            $key = $snapshot_date . '|' . $paper_code;
            if (!isset($snapshots[$key])) {
                $snapshots[$key] = [
                    'snapshot_date' => $snapshot_date,
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

    // Prepare UPSERT statement
    $stmt = $pdo->prepare("
        INSERT INTO daily_snapshots (
            snapshot_date, paper_code, paper_name, business_unit,
            total_active, deliverable, mail_delivery, carrier_delivery, digital_only, on_vacation
        ) VALUES (
            :snapshot_date, :paper_code, :paper_name, :business_unit,
            :total_active, :deliverable, :mail_delivery, :carrier_delivery, :digital_only, :on_vacation
        )
        ON DUPLICATE KEY UPDATE
            paper_name = VALUES(paper_name),
            business_unit = VALUES(business_unit),
            total_active = VALUES(total_active),
            deliverable = VALUES(deliverable),
            mail_delivery = VALUES(mail_delivery),
            carrier_delivery = VALUES(carrier_delivery),
            digital_only = VALUES(digital_only),
            on_vacation = VALUES(on_vacation)
    ");

    // Insert/Update snapshots
    $pdo->beginTransaction();

    try {
        foreach ($snapshots as $snapshot) {
            // Calculate deliverable (total_active - on_vacation)
            $snapshot['deliverable'] = $snapshot['total_active'] - $snapshot['on_vacation'];

            // Check if record exists
            $check_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM daily_snapshots
                WHERE snapshot_date = ? AND paper_code = ?
            ");
            $check_stmt->execute([$snapshot['snapshot_date'], $snapshot['paper_code']]);
            $exists = $check_stmt->fetchColumn() > 0;

            // Execute upsert
            $stmt->execute($snapshot);

            // Track stats
            if ($exists) {
                $stats['updated_records']++;
            } else {
                $stats['new_records']++;
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

        // NEW: Insert subscriber-level records in bulk with UPSERT logic
        if (!empty($subscriber_records)) {
            $sub_stmt = $pdo->prepare("
                INSERT INTO subscriber_snapshots (
                    snapshot_date, sub_num, paper_code, paper_name, business_unit,
                    name, route, rate_name, subscription_length, delivery_type,
                    payment_status, begin_date, paid_thru, daily_rate, on_vacation,
                    address, city_state_postal, abc, issue_code, last_payment_amount,
                    phone, email, login_id, last_login
                ) VALUES (
                    :snapshot_date, :sub_num, :paper_code, :paper_name, :business_unit,
                    :name, :route, :rate_name, :subscription_length, :delivery_type,
                    :payment_status, :begin_date, :paid_thru, :daily_rate, :on_vacation,
                    :address, :city_state_postal, :abc, :issue_code, :last_payment_amount,
                    :phone, :email, :login_id, :last_login
                )
                ON DUPLICATE KEY UPDATE
                    paper_name = VALUES(paper_name),
                    business_unit = VALUES(business_unit),
                    name = VALUES(name),
                    route = VALUES(route),
                    rate_name = VALUES(rate_name),
                    subscription_length = VALUES(subscription_length),
                    delivery_type = VALUES(delivery_type),
                    payment_status = VALUES(payment_status),
                    begin_date = VALUES(begin_date),
                    paid_thru = VALUES(paid_thru),
                    daily_rate = VALUES(daily_rate),
                    on_vacation = VALUES(on_vacation),
                    address = VALUES(address),
                    city_state_postal = VALUES(city_state_postal),
                    abc = VALUES(abc),
                    issue_code = VALUES(issue_code),
                    last_payment_amount = VALUES(last_payment_amount),
                    phone = VALUES(phone),
                    email = VALUES(email),
                    login_id = VALUES(login_id),
                    last_login = VALUES(last_login),
                    import_timestamp = CURRENT_TIMESTAMP
            ");

            foreach ($subscriber_records as $sub) {
                $sub_stmt->execute($sub);
                $stats['subscriber_records_imported']++;
            }
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
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
function getPaperInfo($paper_code) {
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
function parseDate($date_string) {
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
