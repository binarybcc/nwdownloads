<?php

/**
 * CSV Import Helper Functions
 *
 * Reusable functions extracted from upload.php for use across
 * multiple CSV import implementations.
 *
 * @package CirculationDashboard
 * @version 1.0.0
 * @date December 8, 2025
 */

/**
 * Extract date from Newzware CSV filename
 *
 * Parses standard Newzware filename format to extract the report generation date.
 * Supports AllSubscriberReport format: AllSubscriberReport20251208120000.csv
 *
 * @param string $filename Original CSV filename
 * @return string Date in YYYY-MM-DD format
 *
 * @example
 * extractDateFromFilename('AllSubscriberReport20251208120000.csv') // Returns: '2025-12-08'
 */
function extractDateFromFilename($filename)
{
    // Pattern: ReportName + YYYYMMDDHHMMSS + .csv
    // Extract the 14-digit timestamp: YYYYMMDDHHMMSS
    if (preg_match('/(\d{14})\.csv$/i', $filename, $matches)) {
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
 * Calculate ISO week number and year from date
 *
 * Uses ISO 8601 week date system:
 * - Week 1 is the week with the first Thursday of the year
 * - Weeks run Monday to Sunday
 * - Week numbers range from 1-53
 * - Uses ISO year ('o') not calendar year ('Y') for year boundaries
 *
 * @param string $date Date in YYYY-MM-DD format
 * @return array ['week' => int, 'year' => int]
 *
 * @example
 * calculateWeekNumber('2025-12-08') // Returns: ['week' => 50, 'year' => 2025]
 * calculateWeekNumber('2025-01-01') // Returns: ['week' => 1, 'year' => 2025]
 * calculateWeekNumber('2024-12-30') // Returns: ['week' => 1, 'year' => 2025] (ISO year boundary)
 */
function calculateWeekNumber($date)
{
    $dt = new DateTime($date);
    return [
        'week' => (int)$dt->format('W'),  // ISO week number (1-53)
        'year' => (int)$dt->format('o')   // ISO year (handles year boundaries correctly)
    ];
}

/**
 * Calculate the Monday (first day) of a specific ISO week
 *
 * Given an ISO week number and year, returns the date of that week's Monday.
 * Useful for generating snapshot_date values for week-based data.
 *
 * @param int $year ISO year
 * @param int $week ISO week number (1-53)
 * @return string Date in YYYY-MM-DD format (Monday of that week)
 *
 * @example
 * getWeekStartDate(2025, 50) // Returns: '2025-12-08' (Monday of Week 50, 2025)
 * getWeekStartDate(2025, 1)  // Returns: '2024-12-30' (Monday of Week 1, 2025)
 */
function getWeekStartDate($year, $week)
{
    $dt = new DateTime();
    $dt->setISODate($year, $week, 1); // 1 = Monday
    return $dt->format('Y-m-d');
}

/**
 * Determine backfill range for snapshot data
 *
 * Implements backward-only backfill algorithm:
 * 1. Start at upload week
 * 2. Work backward week-by-week
 * 3. Stop when hitting existing data OR minimum date
 * 4. Upload week can replace older data at that specific week
 *
 * @param PDO $pdo Database connection
 * @param string $table Table name to check for existing data
 * @param int $upload_week ISO week number of upload
 * @param int $upload_year ISO year of upload
 * @param string $file_date Original filename date (YYYY-MM-DD)
 * @param string $min_backfill_date Earliest date to backfill (YYYY-MM-DD)
 * @return array List of weeks to process: [['week' => int, 'year' => int, 'weeks_offset' => int, 'is_backfilled' => bool], ...]
 *
 * @example
 * // Empty database, upload Week 50 with min date of 2025-11-24 (Week 48)
 * determineBackfillRange($pdo, 'daily_snapshots', 50, 2025, '2025-12-08', '2025-11-24')
 * // Returns: [
 * //   ['week' => 48, 'year' => 2025, 'weeks_offset' => 2, 'is_backfilled' => true],
 * //   ['week' => 49, 'year' => 2025, 'weeks_offset' => 1, 'is_backfilled' => true],
 * //   ['week' => 50, 'year' => 2025, 'weeks_offset' => 0, 'is_backfilled' => false]
 * // ]
 */
function determineBackfillRange($pdo, $table, $upload_week, $upload_year, $file_date, $min_backfill_date)
{
    // Calculate minimum backfill week
    $min_week_data = calculateWeekNumber($min_backfill_date);
    $min_backfill_week = $min_week_data['week'];
    $min_backfill_year = $min_week_data['year'];

    $weeks_to_process = [];
    $current_week = $upload_week;
    $current_year = $upload_year;
    $weeks_back = 0;

    while (true) {
        // Check if we've reached the minimum date
        if ($current_year < $min_backfill_year ||
            ($current_year == $min_backfill_year && $current_week < $min_backfill_week)) {
            error_log("🛑 Backfill stopped at minimum date ($min_backfill_date)");
            break;
        }

        // Check if this week has data
        $check_stmt = $pdo->prepare("
            SELECT source_date, is_backfilled
            FROM $table
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
            $current_week = 52; // Simplified; actual last week could be 52 or 53
        }
    }

    // Reverse so we process oldest to newest (cleaner for logging)
    return array_reverse($weeks_to_process);
}

/**
 * Execute UPSERT for snapshot data
 *
 * Inserts new records or updates existing records using MySQL's
 * ON DUPLICATE KEY UPDATE syntax. Handles source tracking metadata.
 *
 * @param PDO $pdo Database connection
 * @param string $table Table name
 * @param array $data Associative array of column => value pairs
 * @param array $primary_keys List of columns that form the primary key
 * @return bool True on success
 *
 * @example
 * executeUpsert($pdo, 'daily_snapshots', [
 *     'snapshot_date' => '2025-12-08',
 *     'paper_code' => 'TJ',
 *     'total_active' => 3106,
 *     'source_filename' => 'AllSubscriberReport20251208.csv',
 *     'source_date' => '2025-12-08',
 *     'is_backfilled' => 0,
 *     'backfill_weeks' => 0
 * ], ['snapshot_date', 'paper_code']);
 */
function executeUpsert($pdo, $table, $data, $primary_keys)
{
    $columns = array_keys($data);
    $placeholders = array_fill(0, count($columns), '?');

    // Build INSERT clause
    $insert_sql = "INSERT INTO $table (" . implode(', ', $columns) . ")
                   VALUES (" . implode(', ', $placeholders) . ")";

    // Build ON DUPLICATE KEY UPDATE clause
    // Update all columns except primary keys
    $update_parts = [];
    foreach ($columns as $col) {
        if (!in_array($col, $primary_keys)) {
            $update_parts[] = "$col = VALUES($col)";
        }
    }

    $sql = $insert_sql;
    if (!empty($update_parts)) {
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $update_parts);
    }

    $stmt = $pdo->prepare($sql);
    return $stmt->execute(array_values($data));
}

/**
 * Validate CSV header contains required columns
 *
 * Checks that all required columns are present in the CSV header.
 * Case-insensitive and whitespace-tolerant.
 *
 * @param array $header CSV header row (column names)
 * @param array $required_columns List of required column names
 * @return array Empty if valid, or list of missing columns
 *
 * @example
 * validateCsvHeader(['SUB NUM', 'Ed', 'ISS', 'DEL'], ['SUB NUM', 'Ed', 'ISS'])
 * // Returns: [] (all required columns present)
 *
 * validateCsvHeader(['Name', 'Address'], ['SUB NUM', 'Ed'])
 * // Returns: ['SUB NUM', 'Ed'] (missing both columns)
 */
function validateCsvHeader($header, $required_columns)
{
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
    return $missing_columns;
}

/**
 * Find CSV header row by searching for a key column
 *
 * Searches the first N rows of a CSV file to find the header row
 * containing a specific column name. Useful for CSVs with report
 * headers or metadata rows before the actual column headers.
 *
 * @param resource $handle File handle from fopen()
 * @param string $key_column Column name to search for (e.g., 'SUB NUM')
 * @param int $max_lines Maximum lines to search (default: 50)
 * @return array|null Header row if found, null otherwise
 *
 * @example
 * $handle = fopen('report.csv', 'r');
 * $header = findCsvHeader($handle, 'SUB NUM');
 * // Returns: ['SUB NUM', 'Name', 'Address', ...]
 */
function findCsvHeader($handle, $key_column, $max_lines = 50)
{
    $line_count = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $line_count++;

        // Look for key column in any cell (case-insensitive)
        foreach ($row as $cell) {
            if (stripos($cell, $key_column) !== false) {
                // Trim whitespace from all column names
                return array_map('trim', $row);
            }
        }

        // Safety: Don't search forever
        if ($line_count > $max_lines) {
            break;
        }
    }

    return null;
}

/**
 * Skip decorative separator rows in CSV
 *
 * After finding the header, many CSVs have decorative separator rows
 * (dashes, equals signs, etc.) before the actual data begins.
 * This function skips those and returns the first real data row.
 *
 * @param resource $handle File handle from fopen()
 * @param int $max_skip Maximum separator rows to skip (default: 5)
 * @return array|null First data row if found, null if EOF
 *
 * @example
 * // After reading header row:
 * $first_row = skipDecoratorRows($handle);
 * // Returns: ['12345', 'John Smith', '123 Main St', ...]
 */
function skipDecoratorRows($handle, $max_skip = 5)
{
    $rows_skipped = 0;
    while (($row = fgetcsv($handle)) !== false && $rows_skipped < $max_skip) {
        // Check if row is decorative (all dashes, equals, or mostly empty)
        $first_cell = trim($row[0] ?? '');

        // If first cell is empty or matches decorator pattern, skip
        if (empty($first_cell) || preg_match('/^[-=_]+$/', $first_cell)) {
            $rows_skipped++;
            continue;
        }

        // Found first data row
        return $row;
    }

    return null;
}

/**
 * Format import statistics for logging/display
 *
 * Generates a human-readable summary of import results.
 *
 * @param array $stats Statistics array with keys: new_records, updated_records, total_processed, etc.
 * @return string Formatted statistics message
 *
 * @example
 * formatImportStats([
 *     'new_records' => 5,
 *     'updated_records' => 0,
 *     'total_processed' => 5,
 *     'min_date' => '2025-12-08',
 *     'max_date' => '2025-12-08'
 * ])
 * // Returns: "Imported 5 new records, updated 0 records (total: 5) for date range 2025-12-08 to 2025-12-08"
 */
function formatImportStats($stats)
{
    return sprintf(
        "Imported %d new records, updated %d records (total: %d) for date range %s to %s",
        $stats['new_records'] ?? 0,
        $stats['updated_records'] ?? 0,
        $stats['total_processed'] ?? 0,
        $stats['min_date'] ?? 'unknown',
        $stats['max_date'] ?? 'unknown'
    );
}
