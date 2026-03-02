<?php

/**
 * New Subscription Starts Importer
 *
 * Processes Newzware "New Subscription Starts" CSV files.
 * Tracks individual new subscriber events and classifies them as
 * truly new or restarts by cross-referencing against renewal_events.
 *
 * Shared library used by:
 * - upload_new_starts.php (manual web upload)
 * - NewStartsProcessor (automated processing)
 *
 * File Format:
 * - Filename: NewSubscriptionStarts*.csv
 * - Required Columns: SUB NUM, STARTED, Ed
 * - Header row contains "SUB NUM" (row 11 typically)
 * - Data rows end at "New,,Starts" summary section
 * - Date format: M/D/YY (e.g., 2/24/26)
 *
 * Processing:
 * - Parses CSV with decorative header rows
 * - Cross-references sub_num against renewal_events for classification
 * - UPSERT into new_start_events (dedup by sub_num + paper_code + event_date)
 * - Aggregates into new_starts_daily_summary
 *
 * Date: 2026-03-02
 */

namespace CirculationDashboard;

use PDO;
use Exception;

class NewStartsImporter
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /** @var array<string> Required CSV columns */
    private const REQUIRED_COLUMNS = ['SUB NUM', 'STARTED', 'Ed'];

    /** @var string Earliest date to import (aligned with renewal_events data) */
    private const MIN_DATE = '2025-12-16';

    /**
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Import new starts data from CSV file
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{date_range: string, new_records: int, updated_records: int, total_processed: int, truly_new: int, restarts: int, summary_html: string}
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
            'updated_records' => $stats['duplicates_skipped'],
            'total_processed' => $stats['events_imported'] + $stats['duplicates_skipped'],
            'truly_new' => $stats['truly_new'],
            'restarts' => $stats['restarts'],
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
            throw new Exception('Could not find header row (looking for "SUB NUM" column)');
        }

        $this->validateHeader($header);
        $colMap = array_flip($header);

        // Skip separator/blank rows after header
        $this->skipDecoratorRows($handle);

        // Batch-collect sub_nums first for efficient cross-reference
        $rows = $this->collectDataRows($handle, $colMap);
        fclose($handle);

        if (empty($rows)) {
            throw new Exception('No valid data rows found in CSV');
        }

        // Cross-reference all sub_nums against renewal_events in one batch query
        $subNums = array_unique(array_column($rows, 'sub_num'));
        $subsWithHistory = $this->findSubsWithRenewalHistory($subNums);

        // Insert events
        $stats = [
            'events_imported' => 0,
            'duplicates_skipped' => 0,
            'truly_new' => 0,
            'restarts' => 0,
            'min_date' => null,
            'max_date' => null,
            'by_publication' => [],
        ];

        $stmt = $this->prepareEventStatement();

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $isTrulyNew = !in_array($row['sub_num'], $subsWithHistory);

                $stmt->execute([
                    $filename,
                    $row['event_date'],
                    $row['sub_num'],
                    $row['paper_code'],
                    $row['issue_code'],
                    $row['delivery_type'],
                    $row['remark_code'],
                    $row['submit_code'],
                    $isTrulyNew ? 1 : 0,
                ]);

                if ($stmt->rowCount() > 0) {
                    $stats['events_imported']++;
                    if ($isTrulyNew) {
                        $stats['truly_new']++;
                    } else {
                        $stats['restarts']++;
                    }

                    if (!isset($stats['by_publication'][$row['paper_code']])) {
                        $stats['by_publication'][$row['paper_code']] = ['total' => 0, 'new' => 0, 'restart' => 0];
                    }
                    $stats['by_publication'][$row['paper_code']]['total']++;
                    $stats['by_publication'][$row['paper_code']][$isTrulyNew ? 'new' : 'restart']++;

                    if ($stats['min_date'] === null || $row['event_date'] < $stats['min_date']) {
                        $stats['min_date'] = $row['event_date'];
                    }
                    if ($stats['max_date'] === null || $row['event_date'] > $stats['max_date']) {
                        $stats['max_date'] = $row['event_date'];
                    }
                } else {
                    $stats['duplicates_skipped']++;
                }
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
     * Find header row containing "SUB NUM"
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
                if (stripos(trim($cell), 'SUB NUM') !== false) {
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
     * @param array<string> $header Header row
     * @throws Exception if missing required columns
     */
    private function validateHeader(array $header): void
    {
        $missing = [];
        foreach (self::REQUIRED_COLUMNS as $required) {
            $found = false;
            foreach ($header as $col) {
                if (strtoupper(trim($col)) === strtoupper($required)) {
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
     * Collect all valid data rows from CSV until summary section
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
            if (empty(array_filter($row))) {
                continue;
            }

            $firstCell = trim($row[0] ?? '');

            // Stop at summary sections: "     New,,Starts  , Summary By ,Edition"
            if (empty($firstCell)) {
                $thirdCell = trim($row[2] ?? '');
                if (stripos($thirdCell, 'Starts') !== false) {
                    break;
                }
                continue;
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
     * Parse a single data row
     *
     * @param array $row CSV row
     * @param array<string, int> $colMap Column map
     * @return array|null Parsed data or null if invalid
     */
    private function parseRow(array $row, array $colMap): ?array
    {
        $subNum = trim($row[$colMap['SUB NUM']] ?? '');
        $startedStr = trim($row[$colMap['STARTED']] ?? '');
        $paperCode = trim($row[$colMap['Ed']] ?? '');

        if (empty($subNum) || empty($startedStr) || empty($paperCode)) {
            return null;
        }

        // Must be numeric subscriber number
        if (!is_numeric($subNum)) {
            return null;
        }

        $eventDate = $this->parseDate($startedStr);
        if (!$eventDate) {
            return null;
        }

        // Only import data from alignment date onward
        if ($eventDate < self::MIN_DATE) {
            return null;
        }

        return [
            'sub_num' => $subNum,
            'event_date' => $eventDate,
            'paper_code' => $paperCode,
            'issue_code' => trim($row[$colMap['ISS']] ?? '') ?: null,
            'delivery_type' => trim($row[$colMap['DEL']] ?? '') ?: null,
            'remark_code' => trim($row[$colMap['Remark']] ?? '') ?: null,
            'submit_code' => trim($row[$colMap['SUBMIT']] ?? '') ?: null,
        ];
    }

    /**
     * Batch-query renewal_events to find sub_nums with prior history
     *
     * @param array<string> $subNums Subscriber numbers to check
     * @return array<string> Sub numbers that have renewal history
     */
    private function findSubsWithRenewalHistory(array $subNums): array
    {
        if (empty($subNums)) {
            return [];
        }

        // Batch in groups of 500 to avoid query size limits
        $subsWithHistory = [];
        $chunks = array_chunk($subNums, 500);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT sub_num
                FROM renewal_events
                WHERE sub_num IN ($placeholders)
            ");
            $stmt->execute($chunk);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $subsWithHistory = array_merge($subsWithHistory, $results);
        }

        return $subsWithHistory;
    }

    /**
     * Prepare UPSERT statement for new_start_events
     *
     * @return \PDOStatement
     */
    private function prepareEventStatement(): \PDOStatement
    {
        return $this->pdo->prepare("
            INSERT INTO new_start_events (
                source_filename, event_date, sub_num, paper_code,
                issue_code, delivery_type, remark_code, submit_code,
                is_truly_new
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                source_filename = VALUES(source_filename),
                issue_code = VALUES(issue_code),
                delivery_type = VALUES(delivery_type),
                remark_code = VALUES(remark_code),
                submit_code = VALUES(submit_code),
                is_truly_new = VALUES(is_truly_new)
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
            INSERT INTO new_starts_daily_summary (snapshot_date, paper_code, total_new_starts, truly_new_count, restart_count)
            SELECT
                event_date,
                paper_code,
                COUNT(*) AS total_new_starts,
                SUM(CASE WHEN is_truly_new = 1 THEN 1 ELSE 0 END) AS truly_new_count,
                SUM(CASE WHEN is_truly_new = 0 THEN 1 ELSE 0 END) AS restart_count
            FROM new_start_events
            WHERE event_date BETWEEN ? AND ?
            GROUP BY event_date, paper_code
            ON DUPLICATE KEY UPDATE
                total_new_starts = VALUES(total_new_starts),
                truly_new_count = VALUES(truly_new_count),
                restart_count = VALUES(restart_count)
        ");
        $stmt->execute([$minDate, $maxDate]);
    }

    /**
     * Parse date in M/D/YY format
     *
     * @param string $dateStr Date string (e.g., "2/24/26")
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
        $html .= '<h3>New Starts Data Imported</h3>';
        $html .= '<p>Events imported: ' . $stats['events_imported'];
        $html .= ' | Duplicates skipped: ' . $stats['duplicates_skipped'] . '</p>';
        $html .= '<p><strong>Truly new subscribers: ' . $stats['truly_new'] . '</strong>';
        $html .= ' | Restarts/overlaps: ' . $stats['restarts'] . '</p>';

        if (!empty($stats['by_publication'])) {
            $html .= '<h4>By Publication:</h4><ul>';
            foreach ($stats['by_publication'] as $paper => $counts) {
                $html .= "<li>$paper: {$counts['total']} total";
                $html .= " ({$counts['new']} new, {$counts['restart']} restarts)</li>";
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }
}
