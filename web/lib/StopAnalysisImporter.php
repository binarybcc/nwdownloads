<?php

/**
 * Stop Analysis Report Importer
 *
 * Processes Newzware "Stop Analysis Report" CSV files.
 * Captures per-subscriber stop data including contact info,
 * stop reasons, and remarks for drill-down analysis.
 *
 * Shared library used by:
 * - upload_stop_analysis.php (manual web upload)
 * - StopAnalysisProcessor (automated processing)
 *
 * File Format:
 * - Filename: StopAnalysisReport*.csv
 * - Required Columns: Sub Number, Stop Date, Edition
 * - Header row contains "Sub Number" (row 7 typically)
 * - Data rows end at count row or "Report Criteria" section
 * - Date format: M/D/YY (e.g., 12/20/25)
 *
 * Processing:
 * - Parses CSV with decorative header rows
 * - UPSERT into stop_events (dedup by sub_num + paper_code + stop_date)
 * - Aggregates into stop_daily_summary
 *
 * Date: 2026-03-03
 */

namespace CirculationDashboard;

use PDO;
use Exception;

class StopAnalysisImporter
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /** @var array<string> Required CSV columns (after normalization) */
    private const REQUIRED_COLUMNS = ['Sub Number', 'Stop Date', 'Edition'];

    /** @var string Earliest date to import */
    private const MIN_DATE = '2025-12-15';

    /**
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Import stop analysis data from CSV file
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{date_range: string, new_records: int, updated_records: int, total_processed: int, summary_html: string}
     * @throws Exception on processing errors
     */
    public function import(string $filepath, string $filename): array
    {
        $stats = $this->processCSV($filepath, $filename);

        $this->rebuildDailySummary($stats['min_date'], $stats['max_date']);

        $summary = $this->buildSummaryHTML($stats);

        return [
            'date_range' => $stats['date_range'],
            'new_records' => $stats['events_imported'],
            'updated_records' => $stats['duplicates_updated'],
            'total_processed' => $stats['rows_parsed'],
            'summary_html' => $summary
        ];
    }

    /**
     * Process the CSV and insert events
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array Import statistics
     * @throws Exception on processing errors
     */
    private function processCSV(string $filepath, string $filename): array
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file for processing');
        }

        $header = $this->findHeader($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('Could not find header row (looking for "Sub Number" column)');
        }

        // Normalize double-spaces in column names (Newzware quirk)
        $header = array_map(function ($col) {
            return preg_replace('/\s+/', ' ', trim($col));
        }, $header);

        $this->validateHeader($header);
        $colMap = array_flip($header);

        // Skip separator/blank rows after header
        $this->skipDecoratorRows($handle);

        // Collect all data rows
        $rows = $this->collectDataRows($handle, $colMap);
        fclose($handle);

        if (empty($rows)) {
            throw new Exception('No valid data rows found in CSV');
        }

        // Insert events
        $stats = [
            'events_imported' => 0,
            'duplicates_updated' => 0,
            'duplicates_skipped' => 0,
            'rows_parsed' => count($rows),
            'min_date' => null,
            'max_date' => null,
            'by_publication' => [],
            'by_reason' => [],
        ];

        $stmt = $this->prepareEventStatement();

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $stmt->execute([
                    $filename,
                    $row['sub_num'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['street_address'],
                    $row['address2'],
                    $row['city'],
                    $row['state'],
                    $row['zip'],
                    $row['phone'],
                    $row['email'],
                    $row['start_date'],
                    $row['rate'],
                    $row['stop_date'],
                    $row['paid_date'],
                    $row['stop_reason'],
                    $row['remark'],
                    $row['paper_code'],
                ]);

                $rowCount = $stmt->rowCount();
                // rowCount: 1 = new insert, 2 = updated existing row, 0 = unchanged duplicate
                if ($rowCount === 1) {
                    $stats['events_imported']++;
                } elseif ($rowCount === 2) {
                    $stats['duplicates_updated']++;
                } else {
                    $stats['duplicates_skipped']++;
                }

                // Track date range
                if ($stats['min_date'] === null || $row['stop_date'] < $stats['min_date']) {
                    $stats['min_date'] = $row['stop_date'];
                }
                if ($stats['max_date'] === null || $row['stop_date'] > $stats['max_date']) {
                    $stats['max_date'] = $row['stop_date'];
                }

                // Track by publication
                $pc = $row['paper_code'];
                if (!isset($stats['by_publication'][$pc])) {
                    $stats['by_publication'][$pc] = 0;
                }
                $stats['by_publication'][$pc]++;

                // Track top stop reasons
                $reason = $row['stop_reason'] ?: 'Unknown';
                if (!isset($stats['by_reason'][$reason])) {
                    $stats['by_reason'][$reason] = 0;
                }
                $stats['by_reason'][$reason]++;
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $stats['date_range'] = $this->formatDateRange($stats['min_date'], $stats['max_date']);

        return $stats;
    }

    /**
     * Find header row containing "Sub Number"
     *
     * @param resource $handle File handle
     * @return array<string>|null Header columns or null
     */
    private function findHeader($handle): ?array
    {
        $lineCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $lineCount++;
            foreach ($row as $cell) {
                if (stripos(trim($cell), 'Sub Number') !== false) {
                    return array_map('trim', $row);
                }
            }
            if ($lineCount > 50) {
                break;
            }
        }
        return null;
    }

    /**
     * Validate header contains required columns
     *
     * @param array<string> $header Normalized header row
     * @throws Exception if missing required columns
     */
    private function validateHeader(array $header): void
    {
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $required) {
            $found = false;
            foreach ($header as $col) {
                if (strcasecmp(trim($col), $required) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $required;
            }
        }

        if (!empty($missing)) {
            throw new Exception('CSV missing required columns: ' . implode(', ', $missing));
        }
    }

    /**
     * Skip separator and blank rows after the header
     *
     * @param resource $handle File handle
     */
    private function skipDecoratorRows($handle): void
    {
        $pos = ftell($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $firstCell = trim($row[0] ?? '');
            // Skip if blank or dashes
            if (empty($firstCell) || preg_match('/^[-=_]+$/', $firstCell)) {
                $pos = ftell($handle);
                continue;
            }
            // Found a data row — rewind to start of this row
            fseek($handle, $pos);
            return;
        }
    }

    /**
     * Collect all valid data rows from CSV until footer section
     *
     * @param resource $handle File handle
     * @param array<string, int> $colMap Column name to index mapping
     * @return array<array> Parsed rows
     */
    private function collectDataRows($handle, array $colMap): array
    {
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row, function ($v) { return trim($v) !== ''; }))) {
                continue;
            }

            $firstCell = trim($row[0] ?? '');

            // Stop at "Report Criteria" footer
            if (stripos($firstCell, 'Report Criteria') !== false) {
                break;
            }

            // Stop at bare count row (purely numeric first cell, rest empty)
            if (is_numeric($firstCell) && $this->isCountRow($row)) {
                break;
            }

            // Parse the row
            $parsed = $this->parseRow($row, $colMap);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * Detect a summary count row (e.g., "593,,,,,,,,,,,,,,,,,,")
     *
     * @param array $row CSV row
     * @return bool True if this is a count-only row
     */
    private function isCountRow(array $row): bool
    {
        $nonEmpty = 0;
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                $nonEmpty++;
            }
        }
        return $nonEmpty <= 1;
    }

    /**
     * Parse a single data row
     *
     * @param array $row CSV row
     * @param array<string, int> $colMap Column map
     * @return array|null Parsed data or null if invalid
     */
    private function parseRow(array $row, array $colMap): ?array
    {
        $subNum = trim($row[$colMap['Sub Number']] ?? '');
        $stopDateStr = trim($row[$colMap['Stop Date']] ?? '');
        $paperCode = trim($row[$colMap['Edition']] ?? '');

        if (empty($subNum) || empty($stopDateStr) || empty($paperCode)) {
            return null;
        }

        // Must be numeric subscriber number
        if (!is_numeric($subNum)) {
            return null;
        }

        $stopDate = $this->parseDate($stopDateStr);
        if (!$stopDate) {
            return null;
        }

        // Only import data from alignment date onward
        if ($stopDate < self::MIN_DATE) {
            return null;
        }

        // Parse optional dates
        $startDateStr = trim($row[$colMap['Start Date']] ?? '');
        $paidDateStr = trim($row[$colMap['Paid Date']] ?? '');

        return [
            'sub_num' => $subNum,
            'first_name' => trim($row[$colMap['First Name']] ?? '') ?: null,
            'last_name' => trim($row[$colMap['Last Name']] ?? '') ?: null,
            'street_address' => trim($row[$colMap['Street Address']] ?? '') ?: null,
            'address2' => trim($row[$colMap['Address 2']] ?? '') ?: null,
            'city' => trim($row[$colMap['City']] ?? '') ?: null,
            'state' => trim($row[$colMap['St']] ?? '') ?: null,
            'zip' => trim($row[$colMap['Zip']] ?? '') ?: null,
            'phone' => trim($row[$colMap['Phone']] ?? '') ?: null,
            'email' => trim($row[$colMap['Email']] ?? '') ?: null,
            'start_date' => $startDateStr ? $this->parseDate($startDateStr) : null,
            'rate' => trim($row[$colMap['Rate']] ?? '') ?: null,
            'stop_date' => $stopDate,
            'paid_date' => $paidDateStr ? $this->parseDate($paidDateStr) : null,
            'stop_reason' => trim($row[$colMap['Stop Reason']] ?? '') ?: null,
            'remark' => trim($row[$colMap['Remark']] ?? '') ?: null,
            'paper_code' => $paperCode,
        ];
    }

    /**
     * Prepare UPSERT statement for stop_events
     *
     * @return \PDOStatement
     */
    private function prepareEventStatement(): \PDOStatement
    {
        return $this->pdo->prepare("
            INSERT INTO stop_events (
                source_filename, sub_num, first_name, last_name,
                street_address, address2, city, state, zip,
                phone, email, start_date, rate,
                stop_date, paid_date, stop_reason, remark, paper_code
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                source_filename = VALUES(source_filename),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                street_address = VALUES(street_address),
                address2 = VALUES(address2),
                city = VALUES(city),
                state = VALUES(state),
                zip = VALUES(zip),
                phone = VALUES(phone),
                email = VALUES(email),
                start_date = VALUES(start_date),
                rate = VALUES(rate),
                paid_date = VALUES(paid_date),
                stop_reason = VALUES(stop_reason),
                remark = VALUES(remark)
        ");
    }

    /**
     * Rebuild daily summary table for the imported date range
     *
     * @param string|null $minDate Start of range
     * @param string|null $maxDate End of range
     */
    private function rebuildDailySummary(?string $minDate, ?string $maxDate): void
    {
        if (!$minDate || !$maxDate) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO stop_daily_summary (snapshot_date, paper_code, stop_count)
            SELECT
                stop_date,
                paper_code,
                COUNT(*) AS stop_count
            FROM stop_events
            WHERE stop_date BETWEEN ? AND ?
            GROUP BY stop_date, paper_code
            ON DUPLICATE KEY UPDATE
                stop_count = VALUES(stop_count)
        ");
        $stmt->execute([$minDate, $maxDate]);
    }

    /**
     * Parse date in M/D/YY format
     *
     * @param string $dateStr Date string (e.g., "12/20/25")
     * @return string|null Formatted date (Y-m-d) or null
     */
    private function parseDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $dateStr, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];

            if ($year < 100) {
                $year += ($year < 50) ? 2000 : 1900;
            }

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return null;
    }

    /**
     * Format date range string
     *
     * @param string|null $minDate Minimum date
     * @param string|null $maxDate Maximum date
     * @return string Formatted date range
     */
    private function formatDateRange(?string $minDate, ?string $maxDate): string
    {
        if (!$minDate || !$maxDate) {
            return 'unknown';
        }
        return ($minDate === $maxDate) ? $minDate : "$minDate to $maxDate";
    }

    /**
     * Build HTML summary of import results
     *
     * @param array $stats Import statistics
     * @return string HTML summary
     */
    private function buildSummaryHTML(array $stats): string
    {
        $html = '<div class="summary">';
        $html .= '<h3>Stop Analysis Data Imported</h3>';
        $html .= '<p>New records: ' . $stats['events_imported'];
        $html .= ' | Updated: ' . $stats['duplicates_updated'] . '</p>';

        if (!empty($stats['by_publication'])) {
            $html .= '<h4>By Publication:</h4><ul>';
            foreach ($stats['by_publication'] as $paper => $count) {
                $html .= "<li>$paper: $count stops</li>";
            }
            $html .= '</ul>';
        }

        if (!empty($stats['by_reason'])) {
            arsort($stats['by_reason']);
            $html .= '<h4>Top Stop Reasons:</h4><ul>';
            $shown = 0;
            foreach ($stats['by_reason'] as $reason => $count) {
                $html .= '<li>' . htmlspecialchars($reason) . ": $count</li>";
                if (++$shown >= 5) break;
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }
}
