<?php

/**
 * Vacation Data Processor
 *
 * Processes Newzware "Subscribers On Vacation" CSV files containing
 * vacation start/end dates for active subscribers.
 *
 * File Format:
 * - Filename: SubscribersOnVacation*.csv
 * - Required Columns: SUB NUM, VAC BEG., VAC END, Ed
 * - Date Format: MM/DD/YY (2-digit years = 2000-2099)
 *
 * Processing:
 * - Updates subscriber_snapshots with vacation dates and weeks
 * - Recalculates daily_snapshots vacation counts
 * - Does NOT create new records, only updates existing ones
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/IFileProcessor.php';
require_once __DIR__ . '/../lib/VacationImporter.php';

use CirculationDashboard\VacationImporter;
use PDO;
use Exception;

class VacationProcessor implements IFileProcessor
{
    /** @var PDO Database connection */
    private PDO $pdo;

    /** @var int Maximum file size in bytes (10MB) */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

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
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'Vacation Data Processor';
    }

    /**
     * @inheritDoc
     */
    public function getDefaultPatterns(): array
    {
        return [
            'SubscribersOnVacation*.csv',
            '*Vacation*.csv'
        ];
    }

    /**
     * @inheritDoc
     */
    public function getFileType(): string
    {
        return 'vacation';
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
            throw new Exception('Could not find header row (looking for "SUB NUM" column). This does not appear to be a Subscribers On Vacation report.');
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
                'CSV does not appear to be a Subscribers On Vacation report (missing required columns: ' .
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
            // Import using VacationImporter
            $importer = new VacationImporter($this->pdo);
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
