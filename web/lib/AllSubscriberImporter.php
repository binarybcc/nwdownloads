<?php

/**
 * All Subscriber Report Importer
 *
 * Shared library for processing Newzware All Subscriber Report CSV files.
 * Used by both manual upload (upload.php) and automated processing (AllSubscriberProcessor).
 *
 * Core Processing Logic:
 * - Parse Newzware CSV format (header detection, decorative row skipping)
 * - Extract snapshot date from filename (YYYYMMDDHHMMSS format)
 * - Soft backfill algorithm (fill missing weeks backward until hitting existing data)
 * - Insert/update daily_snapshots and subscriber_snapshots tables
 * - Transaction safety with rollback on errors
 *
 * Date: 2025-12-16
 * Extracted from: web/upload.php (original implementation)
 */

namespace CirculationDashboard;

use PDO;
use Exception;
use DateTime;

class AllSubscriberImporter
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection with error mode set to EXCEPTION
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Import All Subscriber Report CSV
     *
     * Main entry point for processing. Handles complete import workflow including:
     * - Raw upload tracking
     * - CSV parsing
     * - Data validation
     * - Soft backfill logic
     * - Database insertion with transactions
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{date_range: string, new_records: int, updated_records: int, total_processed: int, summary_html: string}
     * @throws Exception on validation or processing errors
     */
    public function import(string $filepath, string $filename): array
    {
        // Step 1: Save raw CSV to raw_uploads table (source of truth)
        $raw_csv_data = file_get_contents($filepath);
        if ($raw_csv_data === false) {
            throw new Exception('Could not read uploaded file');
        }

        $file_size = filesize($filepath);
        $file_hash = hash('sha256', $raw_csv_data);

        // We'll get snapshot_date later, so start with pending status
        $stmt = $this->pdo->prepare("
            INSERT INTO raw_uploads (
                filename, file_size, file_hash, snapshot_date,
                row_count, subscriber_count, raw_csv_data,
                processing_status, uploaded_by, ip_address, user_agent
            ) VALUES (
                :filename, :file_size, :file_hash, '1970-01-01',
                0, 0, :raw_csv_data,
                'pending', :uploaded_by, :ip, :user_agent
            )
        ");

        $stmt->execute([
            'filename' => $filename,
            'file_size' => $file_size,
            'file_hash' => $file_hash,
            'raw_csv_data' => $raw_csv_data,
            'uploaded_by' => $_SERVER['REMOTE_USER'] ?? 'automated',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Automated Processing'
        ]);

        $upload_id = $this->pdo->lastInsertId();

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
                    break 2; // Break out of both loops
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
        $required_columns = ['SUB NUM', 'Ed', 'ISS', 'DEL'];
        $missing_columns = [];
        foreach ($required_columns as $required) {
            $found = false;
            foreach ($header as $col) {
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
            $first_cell = trim($row[0] ?? '');
            if (empty($first_cell) || preg_match('/^[-=_]+$/', $first_cell)) {
                $rows_skipped++;
                continue;
            }

            // Found first data row
            $first_data_row = $row;
            break;
        }

        // Tracking variables
        $snapshots = [];
        $subscriber_records = [];
        $stats = [
            'new_records' => 0,
            'updated_records' => 0,
            'total_processed' => 0,
            'subscriber_records_imported' => 0,
            'min_date' => null,
            'max_date' => null,
            'by_business_unit' => []
        ];

        // Extract snapshot_date from filename (source of truth)
        $file_date = $this->extractSnapshotDateFromFilename($filename);

        // Subtract 7 days to get the week the data actually represents
        $dt = new DateTime($file_date);
        $dt->modify('-7 days');
        $snapshot_date = $dt->format('Y-m-d');

        // Calculate week number and year from adjusted date using ISO 8601
        $week_num = (int)$dt->format('W');  // ISO week number (1-53)
        $year = (int)$dt->format('o');      // ISO year

        $row_num = $header_line;

        // Helper function to check if row is footer/metadata
        $isFooterRow = function ($row) {
            $first_cell = trim($row[0] ?? '');
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
            if ($isFooterRow($row)) {
                break;
            }

            if (count(array_filter($row)) === 0) {
                continue;
            }

            $rows_to_process[] = $row;
        }

        // Now process all collected rows
        foreach ($rows_to_process as $row) {
            try {
                // Extract all subscriber fields
                $sub_num = trim($row[$col_map['SUB NUM']] ?? '');
                $paper_code = trim($row[$col_map['Ed']] ?? '');
                $name = isset($col_map['Name']) ? trim($row[$col_map['Name']] ?? '') : '';
                $route = isset($col_map['Route']) ? trim($row[$col_map['Route']] ?? '') : '';
                $delivery_type = trim($row[$col_map['DEL']] ?? '');
                $zone = isset($col_map['Zone']) ? trim($row[$col_map['Zone']] ?? '') : '';
                $subscription_length = isset($col_map['LEN']) ? trim($row[$col_map['LEN']] ?? '') : '';
                $payment_status = isset($col_map['PAY']) ? trim($row[$col_map['PAY']] ?? '') : '';
                $begin_date = isset($col_map['BEGIN']) ? trim($row[$col_map['BEGIN']] ?? '') : '';
                $paid_thru = isset($col_map['Paid Thru']) ? trim($row[$col_map['Paid Thru']] ?? '') : '';
                $daily_rate = isset($col_map['DAILY RATE']) ? trim($row[$col_map['DAILY RATE']] ?? '') : '';
                $last_payment = isset($col_map['LAST PAY']) ? trim($row[$col_map['LAST PAY']] ?? '') : '';
                $address = isset($col_map['Address']) ? trim($row[$col_map['Address']] ?? '') : '';
                $city_state_postal = isset($col_map['CITY  STATE  POSTAL']) ? trim($row[$col_map['CITY  STATE  POSTAL']] ?? '') : '';
                $phone = isset($col_map['Phone']) ? trim($row[$col_map['Phone']] ?? '') : '';
                $email = isset($col_map['Email']) ? trim($row[$col_map['Email']] ?? '') : '';
                $abc = isset($col_map['ABC']) ? trim($row[$col_map['ABC']] ?? '') : '';
                $issue_code = isset($col_map['ISS']) ? trim($row[$col_map['ISS']] ?? '') : '';
                $login_id = isset($col_map['Login ID']) ? trim($row[$col_map['Login ID']] ?? '') : '';
                $last_login = isset($col_map['Last Login']) ? trim($row[$col_map['Last Login']] ?? '') : '';

                // Skip invalid rows
                if (empty($paper_code) || empty($sub_num)) {
                    continue;
                }

                // Filter: Only 2025-01-01 onwards
                if ($snapshot_date < '2025-01-01') {
                    continue;
                }

                // Determine business unit and paper name
                $paper_info = $this->getPaperInfo($paper_code);
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

                // Store individual subscriber record
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
                    'rate_name' => $zone,
                    'subscription_length' => $subscription_length,
                    'delivery_type' => $delivery_type,
                    'payment_status' => $payment_status,
                    'begin_date' => $this->parseDate($begin_date),
                    'paid_thru' => $this->parseDate($paid_thru),
                    'daily_rate' => !empty($daily_rate) ? floatval($daily_rate) : null,
                    'on_vacation' => $on_vacation ? 1 : 0,
                    'address' => $address,
                    'city_state_postal' => $city_state_postal,
                    'abc' => $abc,
                    'issue_code' => $issue_code,
                    'last_payment_amount' => !empty($last_payment) ? floatval($last_payment) : null,
                    'phone' => $phone,
                    'email' => $email,
                    'login_id' => $login_id,
                    'last_login' => $this->parseDate($last_login)
                ];
            } catch (Exception $e) {
                error_log("Row $row_num error: " . $e->getMessage());
            }
        }

        fclose($handle);

        if (empty($snapshots)) {
            throw new Exception('No valid data found in CSV file (or all data is before 2025-01-01)');
        }

        // SOFT BACKFILL ALGORITHM
        $this->pdo->beginTransaction();
        try {
            // Determine backfill range (start of Week 47)
            // Note: Files subtract 7 days for "data represents previous week"
            // Nov 24 file → -7 days → Nov 17 (Week 47)
            // So minimum must be Nov 17 to allow Nov 24 uploads
            $min_backfill_date = '2025-11-17';
            $min_backfill_week_year = $this->getWeekAndYear($min_backfill_date);
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
                    error_log("🛑 Backfill stopped at minimum date (Nov 17, 2025 - Week 47)");
                    break;
                }

                // Check if this week has data
                $check_stmt = $this->pdo->prepare("
                    SELECT source_date, is_backfilled
                    FROM daily_snapshots
                    WHERE week_num = ? AND year = ?
                    LIMIT 1
                ");
                $check_stmt->execute([$current_week, $current_year]);
                $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing && $existing['source_date']) {
                    // Check if existing data is REAL (not backfilled)
                    $is_real_data = ($existing['is_backfilled'] == 0);

                    if ($is_real_data) {
                        // Existing data is REAL - respect it and stop backfilling
                        if ($weeks_back == 0) {
                            // Upload week: only replace if new file is newer
                            if ($existing['source_date'] < $file_date) {
                                error_log("♻️ Replacing upload week $current_week, $current_year with newer data (old: {$existing['source_date']}, new: $file_date)");
                            } else {
                                throw new Exception("Cannot replace real data from {$existing['source_date']} with older file from $file_date");
                            }
                        } else {
                            // Backfill week: stop when hitting real data
                            error_log("🛑 Backfill stopped at Week $current_week, $current_year (has REAL data from {$existing['source_date']})");
                            break;
                        }
                    } else {
                        // Existing data is BACKFILLED - can be replaced with real data
                        error_log("♻️ Replacing BACKFILLED data at Week $current_week, $current_year (old: {$existing['source_date']}, new: $file_date)");
                        // Continue processing - don't break
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
                    $current_year--;
                    $current_week = 52;
                }
            }

            // Reverse so we process oldest to newest
            $weeks_to_process = array_reverse($weeks_to_process);

            if (empty($weeks_to_process)) {
                throw new Exception('No weeks to process - all data is from newer uploads');
            }

            $total_weeks_processed = count($weeks_to_process);
            $total_backfilled = 0;
            $total_real = 0;

            // Process each week
            foreach ($weeks_to_process as $week_info) {
                $target_week = $week_info['week'];
                $target_year = $week_info['year'];
                $is_backfilled = $week_info['is_backfilled'];
                $weeks_offset = $week_info['weeks_offset'];

                $target_snapshot_date = (new DateTime())
                    ->setISODate($target_year, $target_week, 1)
                    ->format('Y-m-d');

                // Delete existing data for this week
                $delete_daily = $this->pdo->prepare("DELETE FROM daily_snapshots WHERE week_num = ? AND year = ?");
                $delete_daily->execute([$target_week, $target_year]);

                $delete_subscriber = $this->pdo->prepare("DELETE FROM subscriber_snapshots WHERE week_num = ? AND year = ?");
                $delete_subscriber->execute([$target_week, $target_year]);

                // Insert daily snapshots
                $stmt = $this->pdo->prepare("
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

                foreach ($snapshots as $snapshot) {
                    $snapshot['snapshot_date'] = $target_snapshot_date;
                    $snapshot['week_num'] = $target_week;
                    $snapshot['year'] = $target_year;
                    $snapshot['deliverable'] = $snapshot['total_active'] - $snapshot['on_vacation'];
                    $snapshot['source_filename'] = $filename;
                    $snapshot['source_date'] = $file_date;
                    $snapshot['is_backfilled'] = $is_backfilled ? 1 : 0;
                    $snapshot['backfill_weeks'] = $weeks_offset;

                    $stmt->execute($snapshot);

                    if ($is_backfilled) {
                        $stats['updated_records']++;
                        $total_backfilled++;
                    } else {
                        $stats['new_records']++;
                        $total_real++;
                    }
                    $stats['total_processed']++;

                    if ($stats['min_date'] === null || $snapshot['snapshot_date'] < $stats['min_date']) {
                        $stats['min_date'] = $snapshot['snapshot_date'];
                    }
                    if ($stats['max_date'] === null || $snapshot['snapshot_date'] > $stats['max_date']) {
                        $stats['max_date'] = $snapshot['snapshot_date'];
                    }

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

                // Insert subscriber snapshots
                if (!empty($subscriber_records)) {
                    $sub_stmt = $this->pdo->prepare("
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
                        $sub['upload_id'] = $upload_id;
                        $sub['snapshot_date'] = $target_snapshot_date;
                        $sub['week_num'] = $target_week;
                        $sub['year'] = $target_year;
                        $sub['source_filename'] = $filename;
                        $sub['source_date'] = $file_date;
                        $sub['is_backfilled'] = $is_backfilled ? 1 : 0;
                        $sub['backfill_weeks'] = $weeks_offset;

                        $sub_stmt->execute($sub);
                        $stats['subscriber_records_imported']++;
                    }
                }
            }

            error_log("✅ SoftBackfill complete: $total_weeks_processed weeks processed ($total_real real, $total_backfilled backfilled)");

            $this->pdo->commit();

            // Update raw_uploads with final metadata
            $update_stmt = $this->pdo->prepare("
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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Mark raw upload as failed
            try {
                $fail_stmt = $this->pdo->prepare("
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
     * Extract snapshot_date from filename
     *
     * Filename format: AllSubscriberReportYYYYMMDDHHMMSS.csv
     * Example: AllSubscriberReport20251206164201.csv → 2025-12-06
     *
     * The filename timestamp represents when the report was RUN.
     * Actual data represents the PREVIOUS week (run date - 7 days).
     *
     * @param string $filename Original uploaded filename
     * @return string snapshot_date in 'Y-m-d' format
     */
    private function extractSnapshotDateFromFilename(string $filename): string
    {
        // Pattern: AllSubscriberReport + YYYYMMDDHHMMSS + .csv
        if (preg_match('/AllSubscriberReport(\d{14})\.csv$/i', $filename, $matches)) {
            $timestamp = $matches[1];
            $dateStr = substr($timestamp, 0, 8); // Extract YYYYMMDD

            $year = substr($dateStr, 0, 4);
            $month = substr($dateStr, 4, 2);
            $day = substr($dateStr, 6, 2);

            return "$year-$month-$day";
        }

        // Fallback: Use current date if pattern doesn't match
        error_log("Warning: Could not extract date from filename '$filename', using current date");
        return date('Y-m-d');
    }

    /**
     * Get paper information by code
     *
     * Maps paper codes to human-readable names and business units.
     *
     * @param string $paperCode Paper code (TJ, TA, TR, LJ, WRN, FN)
     * @return array{name: string, business_unit: string}
     */
    private function getPaperInfo(string $paperCode): array
    {
        $papers = [
            'TJ' => ['name' => 'The Journal', 'business_unit' => 'South Carolina'],
            'TA' => ['name' => 'The Advertiser', 'business_unit' => 'Michigan'],
            'TR' => ['name' => 'The Ranger', 'business_unit' => 'Wyoming'],
            'LJ' => ['name' => 'The Lander Journal', 'business_unit' => 'Wyoming'],
            'WRN' => ['name' => 'Wind River News', 'business_unit' => 'Wyoming'],
            'FN' => ['name' => 'Former News', 'business_unit' => 'Sold']
        ];

        return $papers[$paperCode] ?? ['name' => $paperCode, 'business_unit' => 'Unknown'];
    }

    /**
     * Parse date string from Newzware CSV
     *
     * Handles various date formats:
     * - M/D/Y format (6/5/25)
     * - M/D/YY format (12/4/25)
     * - Standard strtotime-compatible formats
     *
     * @param string $dateString Date string to parse
     * @return string|null Parsed date in 'Y-m-d' format or null if invalid
     */
    private function parseDate(string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        $dateString = trim($dateString);

        // Handle M/D/Y or M/D/YY format
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $dateString, $matches)) {
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
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Get ISO week number and year from date
     *
     * Used for soft backfill system to identify which weeks need data.
     *
     * @param string $date Date in 'Y-m-d' format
     * @return array{week: int, year: int}
     */
    private function getWeekAndYear(string $date): array
    {
        $dt = new DateTime($date);
        return [
            'week' => (int)$dt->format('W'),  // ISO week number
            'year' => (int)$dt->format('Y')   // Year
        ];
    }
}
