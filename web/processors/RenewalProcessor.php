<?php

/**
 * Renewal Churn Processor
 *
 * Processes Newzware "Renewal Churn Report by Issue" CSV files.
 * Tracks individual subscriber renewal/expiration events.
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

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/IFileProcessor.php';
require_once __DIR__ . '/../lib/RenewalImporter.php';

use CirculationDashboard\RenewalImporter;
use PDO;
use Exception;

class RenewalProcessor implements IFileProcessor
{
    private PDO $pdo;
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getName(): string
    {
        return 'Renewal Churn Processor';
    }

    public function getDefaultPatterns(): array
    {
        return [
            '*Renewal*.csv',
            '*Churn*.csv'
        ];
    }

    public function getFileType(): string
    {
        return 'renewal';
    }

    public function validate(string $filepath): bool
    {
        if (!file_exists($filepath)) {
            throw new Exception("File not found: $filepath");
        }

        $filesize = filesize($filepath);
        if ($filesize > self::MAX_FILE_SIZE) {
            throw new Exception("File too large (max 50MB)");
        }

        // Basic header validation
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception("Could not open file");
        }

        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            if (stripos($row[0] ?? '', 'Sub ID') !== false) {
                $header = $row;
                break;
            }
        }
        fclose($handle);

        if (!$header) {
            throw new Exception('Not a Renewal Churn Report (missing "Sub ID" column)');
        }

        return true;
    }

    public function process(string $filepath): ProcessResult
    {
        $startTime = microtime(true);
        $filename = basename($filepath);

        try {
            // Import using RenewalImporter
            $importer = new RenewalImporter($this->pdo);
            $result = $importer->import($filepath, $filename);

            // Calculate processing duration
            $duration = microtime(true) - $startTime;

            // Return success result
            $successResult = ProcessResult::success(
                $filename,
                $this->getFileType(),
                $result['total_processed'],
                $result['date_range'],
                [
                    'new_records' => $result['new_records'],
                    'updated_records' => $result['updated_records'],
                    'summary_html' => $result['summary_html']
                ]
            );
            $successResult->processingDuration = $duration;

            return $successResult;
        } catch (Exception $e) {
            // Calculate processing duration even on failure
            $duration = microtime(true) - $startTime;

            // Return failure result
            $failureResult = ProcessResult::failure(
                $filename,
                $this->getFileType(),
                $e->getMessage(),
                [
                    'trace' => $e->getTraceAsString()
                ]
            );
            $failureResult->processingDuration = $duration;

            return $failureResult;
        }
    }
}
