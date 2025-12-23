<?php

/**
 * Vacation Data Importer
 *
 * Processes "Subscribers On Vacation" CSV files from Newzware.
 * Updates vacation status and dates in subscriber_snapshots table.
 *
 * Shared library used by:
 * - upload_vacations.php (manual web upload)
 * - VacationProcessor (automated processing)
 *
 * File Format:
 * - Filename: SubscribersOnVacation*.csv
 * - Required Columns: SUB NUM, VAC BEG., VAC END, Ed
 * - Date Format: MM/DD/YY (2-digit years = 2000-2099)
 *
 * Processing:
 * - Updates subscriber_snapshots with vacation dates
 * - Recalculates daily_snapshots aggregates
 * - Saves raw CSV to raw_uploads table
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard;

use PDO;
use Exception;
use DateTime;

class VacationImporter
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /** @var array<string> Required CSV columns */
    private const REQUIRED_COLUMNS = ['SUB NUM', 'VAC BEG.', 'VAC END', 'Ed'];

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
     * Import vacation data from CSV file
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{date_range: string, new_records: int, updated_records: int, total_processed: int, summary_html: string}
     * @throws Exception on processing errors
     */
    public function import(string $filepath, string $filename): array
    {
        // Step 1: Save raw CSV to raw_uploads table
        $uploadId = $this->saveRawUpload($filepath, $filename);

        try {
            // Step 2: Process CSV and update vacation data
            $stats = $this->processVacationCSV($filepath);

            // Step 3: Update raw_uploads with final metadata
            $this->updateRawUploadSuccess($uploadId, $stats);

            // Step 4: Build summary for response
            $summary = $this->buildSummaryHTML($stats);

            return [
                'date_range' => $stats['date_range'],
                'new_records' => 0, // Vacation updates don't create new records
                'updated_records' => $stats['updated_rows'],
                'total_processed' => $stats['updated_rows'],
                'summary_html' => $summary
            ];
        } catch (Exception $e) {
            // Mark raw upload as failed
            $this->updateRawUploadFailure($uploadId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save raw CSV to raw_uploads table
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return int Upload ID
     * @throws Exception if file cannot be read
     */
    private function saveRawUpload(string $filepath, string $filename): int
    {
        $rawCsvData = file_get_contents($filepath);
        if ($rawCsvData === false) {
            throw new Exception('Could not read uploaded file');
        }

        $fileSize = filesize($filepath);
        $fileHash = hash('sha256', $rawCsvData);

        $stmt = $this->pdo->prepare("
            INSERT INTO raw_uploads (
                filename, file_size, file_hash, snapshot_date,
                row_count, subscriber_count, raw_csv_data,
                processing_status, uploaded_by, ip_address, user_agent
            ) VALUES (
                :filename, :file_size, :file_hash, '1970-01-01',
                0, 0, :raw_csv_data,
                'pending', 'automated_vacation', NULL, NULL
            )
        ");

        $stmt->execute([
            'filename' => $filename,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'raw_csv_data' => $rawCsvData
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Process vacation CSV and update database
     *
     * @param string $filepath Path to CSV file
     * @return array{total_rows: int, updated_rows: int, skipped_rows: int, errors: array<string>, by_paper: array<string, int>, date_range: string}
     * @throws Exception on processing errors
     */
    private function processVacationCSV(string $filepath): array
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file for processing');
        }

        $stats = [
            'total_rows' => 0,
            'skipped_rows' => 0,
            'updated_rows' => 0,
            'errors' => [],
            'by_paper' => [],
            'date_range' => ''
        ];

        $lineNumber = 0;
        $headerFound = false;
        $headerRow = null;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Look for header row
            if (!$headerFound) {
                $firstCell = trim($row[0] ?? '');
                if (stripos($firstCell, 'SUB NUM') !== false) {
                    $headerFound = true;
                    $headerRow = array_map('trim', $row);

                    // Validate required columns
                    foreach (self::REQUIRED_COLUMNS as $col) {
                        if (!in_array($col, $headerRow)) {
                            throw new Exception("Missing required column: $col. This doesn't appear to be a Subscribers On Vacation report.");
                        }
                    }
                    continue;
                }
                continue;
            }

            // Skip separator rows (all dashes)
            if (isset($row[0]) && preg_match('/^-+$/', trim($row[0]))) {
                continue;
            }

            // Skip footer rows
            $firstCell = trim($row[0] ?? '');
            if (
                stripos($firstCell, 'Total Vacations') !== false ||
                stripos($firstCell, 'Report') !== false ||
                empty($firstCell)
            ) {
                break; // End of data
            }

            $stats['total_rows']++;

            // Extract data using header positions
            $subNum = trim($row[array_search('SUB NUM', $headerRow)] ?? '');
            $vacBeg = trim($row[array_search('VAC BEG.', $headerRow)] ?? '');
            $vacEnd = trim($row[array_search('VAC END', $headerRow)] ?? '');
            $paperCode = trim($row[array_search('Ed', $headerRow)] ?? '');

            // Validate required fields
            if (empty($subNum) || empty($vacBeg) || empty($vacEnd) || empty($paperCode)) {
                $stats['skipped_rows']++;
                $stats['errors'][] = "Line $lineNumber: Missing required fields";
                continue;
            }

            // Parse dates
            $vacStartStr = $this->parseNewzwareDate($vacBeg);
            $vacEndStr = $this->parseNewzwareDate($vacEnd);

            if (!$vacStartStr || !$vacEndStr) {
                $stats['skipped_rows']++;
                $stats['errors'][] = "Line $lineNumber: Invalid date format (sub: $subNum)";
                continue;
            }

            // Validate date range
            if ($vacEndStr < $vacStartStr) {
                $stats['skipped_rows']++;
                $stats['errors'][] = "Line $lineNumber: End date before start date (sub: $subNum)";
                continue;
            }

            // Calculate vacation weeks
            $vacStart = new DateTime($vacStartStr);
            $vacEnd = new DateTime($vacEndStr);
            $vacationDays = $vacStart->diff($vacEnd)->days;
            $vacationWeeks = round($vacationDays / 7, 1);

            // Update subscriber snapshot
            $updated = $this->updateSubscriberVacation(
                $subNum,
                $paperCode,
                $vacStartStr,
                $vacEndStr,
                $vacationWeeks
            );

            if ($updated) {
                $stats['updated_rows']++;

                // Track by paper
                if (!isset($stats['by_paper'][$paperCode])) {
                    $stats['by_paper'][$paperCode] = 0;
                }
                $stats['by_paper'][$paperCode]++;
            } else {
                $stats['skipped_rows']++;
                $stats['errors'][] = "Line $lineNumber: No matching snapshot found for sub $subNum ($paperCode)";
            }
        }

        fclose($handle);

        if (!$headerFound) {
            throw new Exception('CSV does not appear to be a Subscribers On Vacation report (header row not found)');
        }

        if ($stats['total_rows'] === 0) {
            throw new Exception('No vacation data found in CSV file');
        }

        // Update daily_snapshots with recalculated vacation counts
        $this->updateDailySnapshots();

        // Get date range from latest snapshot
        $dateStmt = $this->pdo->query("SELECT MAX(snapshot_date) as max_date FROM subscriber_snapshots");
        $dateResult = $dateStmt->fetch();
        $stats['date_range'] = $dateResult['max_date'] ?? date('Y-m-d');

        return $stats;
    }

    /**
     * Update vacation data for a subscriber
     *
     * @param string $subNum Subscriber number
     * @param string $paperCode Paper code
     * @param string $vacStart Vacation start date (Y-m-d)
     * @param string $vacEnd Vacation end date (Y-m-d)
     * @param float $vacWeeks Number of weeks on vacation
     * @return bool True if row was updated
     */
    private function updateSubscriberVacation(
        string $subNum,
        string $paperCode,
        string $vacStart,
        string $vacEnd,
        float $vacWeeks
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE subscriber_snapshots
            SET on_vacation = 1,
                vacation_start = :vac_start,
                vacation_end = :vac_end,
                vacation_weeks = :vac_weeks
            WHERE sub_num = :sub_num
              AND paper_code = :paper_code
              AND snapshot_date = (
                  SELECT MAX(snapshot_date)
                  FROM subscriber_snapshots AS ss2
                  WHERE ss2.sub_num = :sub_num2
                    AND ss2.paper_code = :paper_code2
              )
        ");

        $stmt->execute([
            ':sub_num' => $subNum,
            ':sub_num2' => $subNum,
            ':paper_code' => $paperCode,
            ':paper_code2' => $paperCode,
            ':vac_start' => $vacStart,
            ':vac_end' => $vacEnd,
            ':vac_weeks' => $vacWeeks
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update daily_snapshots with recalculated vacation counts
     */
    private function updateDailySnapshots(): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE daily_snapshots ds
            SET ds.on_vacation = (
                SELECT COUNT(*)
                FROM subscriber_snapshots ss
                WHERE ss.snapshot_date = ds.snapshot_date
                  AND ss.paper_code = ds.paper_code
                  AND ss.on_vacation = 1
            )
            WHERE ds.snapshot_date = (
                SELECT MAX(snapshot_date) FROM subscriber_snapshots
            )
        ");

        $stmt->execute();
    }

    /**
     * Parse Newzware date format (MM/DD/YY)
     *
     * All 2-digit years treated as 2000-2099 (business requirement)
     *
     * @param string $dateStr Date string from Newzware
     * @return string|null Parsed date in Y-m-d format or null
     */
    private function parseNewzwareDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        // Try MM/DD/YY format
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2})$#', $dateStr, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = 2000 + (int)$matches[3]; // Convert 2-digit to 4-digit

            if (!checkdate($month, $day, $year)) {
                return null;
            }

            $dt = new DateTime("$year-$month-$day");
            return $dt->format('Y-m-d');
        }

        // Try MM/DD/YYYY format (full year)
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dateStr, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];

            if (!checkdate($month, $day, $year)) {
                return null;
            }

            $dt = new DateTime("$year-$month-$day");
            return $dt->format('Y-m-d');
        }

        return null;
    }

    /**
     * Update raw_uploads record on successful processing
     *
     * @param int $uploadId Upload ID
     * @param array $stats Processing statistics
     */
    private function updateRawUploadSuccess(int $uploadId, array $stats): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE raw_uploads SET
                snapshot_date = :snapshot_date,
                row_count = :row_count,
                subscriber_count = :subscriber_count,
                processed_at = NOW(),
                processing_status = 'completed'
            WHERE upload_id = :upload_id
        ");

        $stmt->execute([
            'snapshot_date' => $stats['date_range'],
            'row_count' => $stats['total_rows'],
            'subscriber_count' => $stats['updated_rows'],
            'upload_id' => $uploadId
        ]);
    }

    /**
     * Update raw_uploads record on processing failure
     *
     * @param int $uploadId Upload ID
     * @param string $errorMessage Error message
     */
    private function updateRawUploadFailure(int $uploadId, string $errorMessage): void
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE raw_uploads SET
                    processing_status = 'failed',
                    processing_errors = :error
                WHERE upload_id = :upload_id
            ");

            $stmt->execute([
                'error' => $errorMessage,
                'upload_id' => $uploadId
            ]);
        } catch (Exception $ignored) {
            // Ignore errors updating error status
        }
    }

    /**
     * Build HTML summary of processing results
     *
     * @param array $stats Processing statistics
     * @return string HTML summary
     */
    private function buildSummaryHTML(array $stats): string
    {
        $html = '<div class="summary">';
        $html .= '<h3>Vacation Data Imported</h3>';
        $html .= '<p>Updated ' . $stats['updated_rows'] . ' subscriber vacation records</p>';

        if (!empty($stats['by_paper'])) {
            $html .= '<h4>By Paper:</h4><ul>';
            foreach ($stats['by_paper'] as $paper => $count) {
                $html .= "<li>$paper: $count records</li>";
            }
            $html .= '</ul>';
        }

        if (!empty($stats['errors']) && count($stats['errors']) > 0) {
            $html .= '<h4>Warnings (' . count($stats['errors']) . '):</h4><ul>';
            foreach (array_slice($stats['errors'], 0, 5) as $error) {
                $html .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            if (count($stats['errors']) > 5) {
                $html .= '<li>... and ' . (count($stats['errors']) - 5) . ' more</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }
}
