<?php

/**
 * Rates Data Importer
 *
 * Processes subscription rate CSV files from Newzware.
 * Updates rate configuration and market rate structure.
 *
 * Shared library used by:
 * - upload_rates.php (manual web upload) - FUTURE
 * - RatesProcessor (automated processing)
 *
 * File Format:
 * - Filename: rates*.csv or *Rates*.csv
 * - Columns: Rate.rr Online Desc, Rate.rr Edition, Rate.rr Issue, Rate.rr Length,
 *            Rate.rr Len Type, Rate.rr Zone, Sub Rate Id, Effective Date, Full Rate
 *
 * Processing:
 * - Updates rate_flags table with all rates (UPSERT)
 * - Updates rate_structure table with market rates (non-zero, recent effective dates)
 * - Calculates annualized rates for comparison
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard;

use PDO;
use Exception;
use DateTime;

class RatesImporter
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /** @var array<string> Expected CSV columns */
    private const EXPECTED_COLUMNS = [
        'Rate.rr Online Desc',
        'Rate.rr Edition',
        'Rate.rr Issue',
        'Rate.rr Length',
        'Rate.rr Len Type(m=month,Y-year,W=week)',
        'Rate.rr Zone',
        'Sub Rate Id',
        'Effective Date',
        'Full Rate'
    ];

    /** @var array<string, int> Annualization multipliers */
    private const ANNUALIZATION = [
        'W' => 52,     // Weekly subscriptions
        'M' => 12,     // Monthly subscriptions
        'Y' => 1,      // Yearly subscriptions
    ];

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
     * Import rates data from CSV file
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{date_range: string, new_records: int, updated_records: int, total_processed: int, summary_html: string}
     * @throws Exception on processing errors
     */
    public function import(string $filepath, string $filename): array
    {
        $stats = $this->processRatesCSV($filepath);
        $summary = $this->buildSummaryHTML($stats);

        return [
            'date_range' => $stats['date_range'],
            'new_records' => $stats['new_rates'],
            'updated_records' => $stats['updated_rates'],
            'total_processed' => $stats['total_rows'],
            'summary_html' => $summary
        ];
    }

    /**
     * Process rates CSV and update database
     *
     * @param string $filepath Path to CSV file
     * @return array{total_rows: int, new_rates: int, updated_rates: int, market_rates: int, by_paper: array, date_range: string}
     * @throws Exception on processing errors
     */
    private function processRatesCSV(string $filepath): array
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file for processing');
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('CSV file is empty');
        }

        // Trim whitespace from headers
        $header = array_map('trim', $header);

        // Validate header columns
        $this->validateHeader($header);

        // Create column map
        $colMap = array_flip($header);

        // Statistics
        $stats = [
            'total_rows' => 0,
            'new_rates' => 0,
            'updated_rates' => 0,
            'market_rates' => 0,
            'by_paper' => [],
            'date_range' => '',
            'min_date' => null,
            'max_date' => null
        ];

        // Prepare statements
        $flagStmt = $this->prepareRateFlagStatement();
        $structureStmt = $this->prepareRateStructureStatement();

        // Begin transaction
        $this->pdo->beginTransaction();

        try {
            // Process each rate row
            while (($row = fgetcsv($handle)) !== false) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $stats['total_rows']++;

                // Process rate
                $this->processRateRow($row, $colMap, $flagStmt, $structureStmt, $stats);
            }

            // Commit transaction
            $this->pdo->commit();
        } catch (Exception $e) {
            // Rollback on error
            $this->pdo->rollBack();
            fclose($handle);
            throw $e;
        }

        fclose($handle);

        // Format date range
        $stats['date_range'] = $this->formatDateRange($stats['min_date'], $stats['max_date']);

        return $stats;
    }

    /**
     * Validate header contains expected columns
     *
     * @param array<string> $header Header row
     * @throws Exception if missing expected columns
     */
    private function validateHeader(array $header): void
    {
        $missing = [];
        foreach (self::EXPECTED_COLUMNS as $expected) {
            if (!in_array($expected, $header)) {
                $missing[] = $expected;
            }
        }

        if (!empty($missing)) {
            throw new Exception('CSV missing expected columns: ' . implode(', ', $missing));
        }
    }

    /**
     * Prepare rate_flags INSERT/UPDATE statement
     *
     * @return \PDOStatement
     */
    private function prepareRateFlagStatement(): \PDOStatement
    {
        return $this->pdo->prepare("
            INSERT INTO rate_flags (
                paper_code,
                zone,
                rate_name,
                subscription_length,
                rate_amount,
                is_legacy,
                is_ignored,
                is_special,
                auto_detected_legacy
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rate_amount = VALUES(rate_amount),
                updated_at = CURRENT_TIMESTAMP
        ");
    }

    /**
     * Prepare rate_structure INSERT/UPDATE statement
     *
     * @return \PDOStatement
     */
    private function prepareRateStructureStatement(): \PDOStatement
    {
        return $this->pdo->prepare("
            INSERT INTO rate_structure (
                paper_code,
                subscription_length,
                market_rate,
                rate_name,
                annualized_rate
            )
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                market_rate = VALUES(market_rate),
                rate_name = VALUES(rate_name),
                annualized_rate = VALUES(annualized_rate),
                created_at = CURRENT_TIMESTAMP
        ");
    }

    /**
     * Process a single rate row
     *
     * @param array $row CSV row
     * @param array<string, int> $colMap Column map
     * @param \PDOStatement $flagStmt Rate flags statement
     * @param \PDOStatement $structureStmt Rate structure statement
     * @param array &$stats Statistics array
     */
    private function processRateRow(
        array $row,
        array $colMap,
        \PDOStatement $flagStmt,
        \PDOStatement $structureStmt,
        array &$stats
    ): void {
        try {
            // Extract fields
            $rateDesc = trim($row[$colMap['Rate.rr Online Desc']] ?? '');
            $paperCode = trim($row[$colMap['Rate.rr Edition']] ?? '');
            $length = trim($row[$colMap['Rate.rr Length']] ?? '');
            $lenType = trim($row[$colMap['Rate.rr Len Type(m=month,Y-year,W=week)']] ?? '');
            $zone = trim($row[$colMap['Rate.rr Zone']] ?? '');
            $rateId = trim($row[$colMap['Sub Rate Id']] ?? '');
            $effectiveDate = trim($row[$colMap['Effective Date']] ?? '');
            $fullRate = trim($row[$colMap['Full Rate']] ?? '');

            // Validate required fields
            if (empty($paperCode) || empty($length) || empty($lenType) || empty($zone)) {
                return;
            }

            // Parse rate amount
            $rateAmount = floatval($fullRate);

            // Build subscription length string (e.g., "52W", "12M", "1Y")
            $subscriptionLength = $length . strtoupper($lenType);

            // Parse effective date
            $parsedDate = $this->parseDate($effectiveDate);

            // Track date range
            if ($parsedDate) {
                if ($stats['min_date'] === null || $parsedDate < $stats['min_date']) {
                    $stats['min_date'] = $parsedDate;
                }
                if ($stats['max_date'] === null || $parsedDate > $stats['max_date']) {
                    $stats['max_date'] = $parsedDate;
                }
            }

            // Auto-detect legacy rates (effective date > 2 years old OR rate amount is 0)
            $isAutoLegacy = 0;
            if ($parsedDate) {
                $effectiveDateTime = new DateTime($parsedDate);
                $twoYearsAgo = new DateTime('-2 years');
                if ($effectiveDateTime < $twoYearsAgo || $rateAmount == 0) {
                    $isAutoLegacy = 1;
                }
            } elseif ($rateAmount == 0) {
                $isAutoLegacy = 1;
            }

            // Insert/update rate_flags
            $flagStmt->execute([
                $paperCode,
                $zone,
                $rateDesc,
                $subscriptionLength,
                $rateAmount,
                0,  // is_legacy (user-configurable)
                0,  // is_ignored (user-configurable)
                0,  // is_special (user-configurable)
                $isAutoLegacy
            ]);

            if ($flagStmt->rowCount() > 0) {
                $stats['new_rates']++;
            } else {
                $stats['updated_rates']++;
            }

            // Track by paper
            if (!isset($stats['by_paper'][$paperCode])) {
                $stats['by_paper'][$paperCode] = 0;
            }
            $stats['by_paper'][$paperCode]++;

            // Insert/update rate_structure for market rates (non-zero, recent)
            if ($rateAmount > 0 && !$isAutoLegacy) {
                $annualizedRate = $this->calculateAnnualizedRate($rateAmount, $lenType, $length);

                $structureStmt->execute([
                    $paperCode,
                    $subscriptionLength,
                    $rateAmount,
                    $rateDesc,
                    $annualizedRate
                ]);

                if ($structureStmt->rowCount() > 0) {
                    $stats['market_rates']++;
                }
            }
        } catch (Exception $e) {
            error_log("Error processing rate row: " . $e->getMessage());
        }
    }

    /**
     * Calculate annualized rate for comparison
     *
     * @param float $rateAmount Rate amount
     * @param string $lenType Length type (W, M, Y)
     * @param string $length Length number
     * @return float Annualized rate
     */
    private function calculateAnnualizedRate(float $rateAmount, string $lenType, string $length): float
    {
        $lenType = strtoupper($lenType);
        $lengthNum = floatval($length);

        if (!isset(self::ANNUALIZATION[$lenType]) || $lengthNum == 0) {
            return 0.0;
        }

        $periodsPerYear = self::ANNUALIZATION[$lenType];
        $ratePerPeriod = $rateAmount / $lengthNum;

        return round($ratePerPeriod * $periodsPerYear, 2);
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

        // Try M/D/YY format
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2})$#', $dateStr, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = 2000 + (int)$matches[3];  // Assume 2000s

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Try M/D/YYYY format
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dateStr, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];

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
            return date('Y-m-d');  // Default to today
        }

        return ($minDate === $maxDate) ? $minDate : "$minDate to $maxDate";
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
        $html .= '<h3>Rate Data Imported</h3>';
        $html .= '<p>Total Rates: ' . $stats['total_rows'] . '</p>';
        $html .= '<p>New: ' . $stats['new_rates'] . ' | Updated: ' . $stats['updated_rates'] . '</p>';
        $html .= '<p>Market Rates: ' . $stats['market_rates'] . '</p>';

        if (!empty($stats['by_paper'])) {
            $html .= '<h4>By Publication:</h4><ul>';
            foreach ($stats['by_paper'] as $paper => $count) {
                $html .= "<li>$paper: $count rates</li>";
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }
}
