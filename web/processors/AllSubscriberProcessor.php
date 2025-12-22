<?php

/**
 * All Subscriber Report Processor
 *
 * Processes Newzware "All Subscriber Report" CSV files containing
 * complete subscriber data for all publications.
 *
 * File Format:
 * - Filename: AllSubscriberReportYYYYMMDDHHMMSS.csv
 * - Required Columns: SUB NUM, Ed, ISS, DEL
 * - Contains: ~8,000 rows of subscriber data
 * - Date Extraction: Filename timestamp minus 7 days = data week
 *
 * Processing:
 * - Validates complete data (all expected papers present)
 * - Uses soft backfill algorithm for missing weeks
 * - Updates both daily_snapshots and subscriber_snapshots tables
 * - Clears dashboard caches after successful import
 *
 * Date: 2025-12-16
 */

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/IFileProcessor.php';
require_once __DIR__ . '/../SimpleCache.php';

use CirculationDashboard\SimpleCache;
use PDO;
use Exception;
use DateTime;

class AllSubscriberProcessor implements IFileProcessor
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /** @var int Maximum file size in bytes (10MB) */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** @var array<string> Required CSV columns */
    private const REQUIRED_COLUMNS = ['SUB NUM', 'Ed', 'ISS', 'DEL'];

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
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'All Subscriber Report Processor';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultPatterns(): array
    {
        return [
            'AllSubscriberReport*.csv',
            'AllSub*.csv'  // Alternative pattern
        ];
    }

    /**
     * @inheritDoc
     */
    public function getFileType(): string
    {
        return 'allsubscriber';
    }

    /**
     * @inheritDoc
     */
    public function validate(string $filepath): bool
    {
        // Check file exists
        if (!file_exists($filepath)) {
            throw new Exception("File not found: $filepath");
        }

        // Check file size
        $filesize = filesize($filepath);
        if ($filesize === false) {
            throw new Exception("Could not determine file size");
        }

        if ($filesize > self::MAX_FILE_SIZE) {
            $sizeMB = round($filesize / (1024 * 1024), 2);
            throw new Exception("File too large: {$sizeMB}MB (max 10MB)");
        }

        // Check file is readable
        if (!is_readable($filepath)) {
            throw new Exception("File is not readable");
        }

        // Validate CSV structure and required columns
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception("Could not open file for validation");
        }

        // Find header row (contains "SUB NUM")
        $header = null;
        $headerLine = 0;
        while (($row = fgetcsv($handle)) !== false && $headerLine < 50) {
            $headerLine++;
            foreach ($row as $cell) {
                if (stripos($cell, 'SUB NUM') !== false) {
                    $header = array_map('trim', $row);
                    break 2;
                }
            }
        }

        fclose($handle);

        if (!$header) {
            throw new Exception('Could not find header row (looking for "SUB NUM" column). This does not appear to be an All Subscriber Report.');
        }

        // Validate required columns exist
        $missingColumns = [];
        foreach (self::REQUIRED_COLUMNS as $required) {
            $found = false;
            foreach ($header as $col) {
                if (strtoupper(trim($col)) === strtoupper($required)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingColumns[] = $required;
            }
        }

        if (!empty($missingColumns)) {
            throw new Exception(
                'CSV does not appear to be an All Subscriber Report (missing required columns: ' .
                implode(', ', $missingColumns) . ')'
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function process(string $filepath): ProcessResult
    {
        $startTime = microtime(true);
        $filename = basename($filepath);

        try {
            // Import using existing upload logic
            // This function is extracted from web/upload.php
            $result = $this->processAllSubscriberReport($filepath, $filename);

            // Clear dashboard caches (new data imported)
            $cache = new SimpleCache();
            $cache->clear();

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

    /**
     * Process All Subscriber Report CSV
     *
     * Core processing logic extracted from web/upload.php
     * Handles Newzware CSV format with soft backfill algorithm
     *
     * @param string $filepath Path to CSV file
     * @param string $filename Original filename
     * @return array{date_range: string, new_records: int, updated_records: int, total_processed: int, summary_html: string}
     * @throws Exception on processing errors
     */
    private function processAllSubscriberReport(string $filepath, string $filename): array
    {
        // This method will contain the extracted logic from upload.php
        // For now, we'll include the core processing functions inline
        // In a production refactor, these could be extracted to a shared library

        require_once __DIR__ . '/../lib/AllSubscriberImporter.php';

        $importer = new \CirculationDashboard\AllSubscriberImporter($this->pdo);
        return $importer->import($filepath, $filename);
    }
}
