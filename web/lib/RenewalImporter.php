<?php

/**
 * Renewal Churn Data Importer
 *
 * Processes Newzware "Renewal Churn Report by Issue" CSV files.
 * Tracks individual subscriber renewal/expiration events.
 *
 * Shared library used by:
 * - upload_renewals.php (manual web upload)
 * - RenewalProcessor (automated processing)
 *
 * File Format:
 * - Filename: *Renewal*.csv or *Churn*.csv
 * - Required Columns: Sub ID, Stat, Ed., Issue Date
 * - Status Values: RENEW or EXPIRE
 * - Subscription Types: REGULAR, MONTHLY, COMPLIMENTARY
 *
 * Processing:
 * - Inserts renewal events into renewal_events table (append-only)
 * - Inserts/updates daily summaries in churn_daily_summary table
 * - Deduplication via ON DUPLICATE KEY UPDATE
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard;

use PDO;
use Exception;

class RenewalImporter
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /** @var array<string> Required CSV columns */
    private const REQUIRED_COLUMNS = ['Sub ID', 'Stat', 'Ed.', 'Issue Date'];

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Import renewal data from CSV file
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{date_range: string, new_records: int, updated_records: int, total_processed: int, summary_html: string}
     * @throws Exception on processing errors
     */
    public function import(string $filepath, string $filename): array
    {
        $stats = $this->processRenewalCSV($filepath, $filename);

        $summary = $this->buildSummaryHTML($stats);

        return [
            'date_range' => $stats['date_range'],
            'new_records' => $stats['events_imported'],
            'updated_records' => $stats['summaries_imported'],
            'total_processed' => $stats['events_imported'],
            'summary_html' => $summary
        ];
    }

    /**
     * Process renewal CSV and update database
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{events_imported: int, duplicates_skipped: int, summaries_imported: int, date_range: string, by_publication: array, by_type: array}
     * @throws Exception on processing errors
     */
    private function processRenewalCSV(string $filepath, string $filename): array
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file for processing');
        }

        // Find header row
        $header = $this->findHeader($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('Could not find header row (looking for "Sub ID" column)');
        }

        // Validate required columns
        $this->validateHeader($header);

        // Create column map
        $colMap = array_flip($header);

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

        // Prepare statements
        $eventStmt = $this->prepareEventStatement();
        $summaryStmt = $this->prepareSummaryStatement();

        // Process rows
        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $firstCell = trim($row[0] ?? '');
            $secondCell = strtoupper(trim($row[1] ?? ''));

            // Process ISSUE summary rows
            if (empty($firstCell) && $secondCell === 'ISSUE') {
                $this->processIssueSummaryRow($row, $summaryStmt, $stats);
                continue;
            }

            // Stop at footer
            if (stripos($firstCell, 'Total') !== false || stripos($firstCell, 'Report') !== false) {
                break;
            }

            // Process renewal event row
            $this->processRenewalRow($row, $colMap, $eventStmt, $filename, $stats);
        }

        fclose($handle);

        // Format date range
        $stats['date_range'] = $this->formatDateRange($stats['min_date'], $stats['max_date']);

        return $stats;
    }

    /**
     * Find header row in CSV
     *
     * @param resource $handle File handle
     * @return array<string>|null Header row or null
     */
    private function findHeader($handle): ?array
    {
        while (($row = fgetcsv($handle)) !== false) {
            if (stripos($row[0] ?? '', 'Sub ID') !== false) {
                return array_map('trim', $row);
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
            if (!in_array($required, $header)) {
                $missing[] = $required;
            }
        }

        if (!empty($missing)) {
            throw new Exception('CSV missing required columns: ' . implode(', ', $missing));
        }
    }

    /**
     * Prepare event INSERT statement
     *
     * @return \PDOStatement
     */
    private function prepareEventStatement(): \PDOStatement
    {
        return $this->pdo->prepare("
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
            ON DUPLICATE KEY UPDATE id = id
        ");
    }

    /**
     * Prepare summary INSERT/UPDATE statement
     *
     * @return \PDOStatement
     */
    private function prepareSummaryStatement(): \PDOStatement
    {
        return $this->pdo->prepare("
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
        ");
    }

    /**
     * Process a single renewal event row
     *
     * @param array $row CSV row
     * @param array<string, int> $colMap Column map
     * @param \PDOStatement $stmt Prepared statement
     * @param string $filename Source filename
     * @param array &$stats Statistics array
     */
    private function processRenewalRow(array $row, array $colMap, \PDOStatement $stmt, string $filename, array &$stats): void
    {
        try {
            // Extract fields
            $subId = trim($row[$colMap['Sub ID']] ?? '');
            $status = strtoupper(trim($row[$colMap['Stat']] ?? ''));
            $paperCode = trim($row[$colMap['Ed.'] ?? $colMap['Ed'] ?? ''] ?? '');
            $issueDate = trim($row[$colMap['Issue Date']] ?? '');

            // Validate required fields
            if (empty($subId) || empty($status) || empty($paperCode) || empty($issueDate)) {
                return;
            }

            // Validate status
            if (!in_array($status, ['RENEW', 'EXPIRE'])) {
                return;
            }

            // Parse date
            $eventDate = $this->parseDate($issueDate);
            if (!$eventDate) {
                return;
            }

            // Determine subscription type from column data
            $regExpiring = (int)($row[5] ?? 0);
            $mthyExpiring = (int)($row[9] ?? 0);
            $compExpiring = (int)($row[13] ?? 0);

            if ($regExpiring > 0) {
                $subscriptionType = 'REGULAR';
            } elseif ($mthyExpiring > 0) {
                $subscriptionType = 'MONTHLY';
            } elseif ($compExpiring > 0) {
                $subscriptionType = 'COMPLIMENTARY';
            } else {
                return;
            }

            // Execute insert
            $stmt->execute([
                $filename,
                $eventDate,
                $subId,
                $paperCode,
                $status,
                $subscriptionType
            ]);

            // Track statistics
            if ($stmt->rowCount() > 0) {
                $stats['events_imported']++;
                $stats['by_type'][$subscriptionType]++;

                if (!isset($stats['by_publication'][$paperCode])) {
                    $stats['by_publication'][$paperCode] = 0;
                }
                $stats['by_publication'][$paperCode]++;

                // Track date range
                if ($stats['min_date'] === null || $eventDate < $stats['min_date']) {
                    $stats['min_date'] = $eventDate;
                }
                if ($stats['max_date'] === null || $eventDate > $stats['max_date']) {
                    $stats['max_date'] = $eventDate;
                }
            } else {
                $stats['duplicates_skipped']++;
            }
        } catch (Exception $e) {
            error_log("Error processing renewal row: " . $e->getMessage());
        }
    }

    /**
     * Process ISSUE summary row
     *
     * @param array $row CSV row
     * @param \PDOStatement $stmt Prepared statement
     * @param array &$stats Statistics array
     */
    private function processIssueSummaryRow(array $row, \PDOStatement $stmt, array &$stats): void
    {
        try {
            $paperCode = trim($row[2] ?? '');
            $issueDate = trim($row[4] ?? '');

            if (empty($paperCode) || empty($issueDate)) {
                return;
            }

            $eventDate = $this->parseDate($issueDate);
            if (!$eventDate) {
                return;
            }

            // Process each subscription type
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
                if ($data['expiring'] > 0) {
                    $renewalRate = (float)str_replace('%', '', $data['renewal_pct']);
                    $churnRate = 100.0 - $renewalRate;

                    $stmt->execute([
                        $eventDate,
                        $paperCode,
                        $type,
                        $data['expiring'],
                        $data['renewed'],
                        $data['stopped'],
                        $renewalRate,
                        $churnRate
                    ]);

                    if ($stmt->rowCount() > 0) {
                        $stats['summaries_imported']++;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error processing summary row: " . $e->getMessage());
        }
    }

    /**
     * Parse date in M/D/YY or M/D/YYYY format
     *
     * @param string $dateStr Date string
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

            // Convert 2-digit year
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
     * Build HTML summary
     *
     * @param array $stats Statistics
     * @return string HTML summary
     */
    private function buildSummaryHTML(array $stats): string
    {
        $html = '<div class="summary">';
        $html .= '<h3>Renewal Data Imported</h3>';
        $html .= '<p>Events: ' . $stats['events_imported'] . ' | ';
        $html .= 'Duplicates: ' . $stats['duplicates_skipped'] . ' | ';
        $html .= 'Summaries: ' . $stats['summaries_imported'] . '</p>';

        if (!empty($stats['by_type'])) {
            $html .= '<h4>By Type:</h4><ul>';
            foreach ($stats['by_type'] as $type => $count) {
                if ($count > 0) {
                    $html .= "<li>$type: $count events</li>";
                }
            }
            $html .= '</ul>';
        }

        if (!empty($stats['by_publication'])) {
            $html .= '<h4>By Publication:</h4><ul>';
            foreach ($stats['by_publication'] as $paper => $count) {
                $html .= "<li>$paper: $count events</li>";
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }
}
