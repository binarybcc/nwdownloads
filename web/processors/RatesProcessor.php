<?php

/**
 * Rates Data Processor
 *
 * Processes Newzware subscription rate CSV files.
 * Updates rate configuration and market rate structure.
 *
 * File Format:
 * - Filename: rates*.csv or *Rates*.csv
 * - Required Columns: Rate.rr Online Desc, Rate.rr Edition, Rate.rr Length,
 *                     Rate.rr Len Type, Rate.rr Zone, Effective Date, Full Rate
 *
 * Processing:
 * - Updates rate_flags table with all rates (UPSERT)
 * - Updates rate_structure table with market rates (non-zero, recent dates)
 * - Calculates annualized rates for comparison
 *
 * Date: 2025-12-17
 */

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/IFileProcessor.php';
require_once __DIR__ . '/../lib/RatesImporter.php';

use CirculationDashboard\RatesImporter;
use PDO;
use Exception;

class RatesProcessor implements IFileProcessor
{
    private PDO $pdo;
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    private const REQUIRED_COLUMNS = [
        'Rate.rr Online Desc',
        'Rate.rr Edition',
        'Rate.rr Length',
        'Rate.rr Len Type(m=month,Y-year,W=week)',
        'Full Rate'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getName(): string
    {
        return 'Rates Data Processor';
    }

    public function getDefaultPatterns(): array
    {
        return [
            'rates*.csv',
            '*Rates*.csv'
        ];
    }

    public function getFileType(): string
    {
        return 'rates';
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

        $header = fgetcsv($handle);
        fclose($handle);

        if (!$header) {
            throw new Exception('CSV file is empty');
        }

        // Trim whitespace from headers
        $header = array_map('trim', $header);

        // Check for required columns
        $hasRequiredColumns = true;
        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!in_array($required, $header)) {
                $hasRequiredColumns = false;
                break;
            }
        }

        if (!$hasRequiredColumns) {
            throw new Exception('Not a Rates CSV (missing required columns: ' . implode(', ', self::REQUIRED_COLUMNS) . ')');
        }

        return true;
    }

    public function process(string $filepath): ProcessResult
    {
        $startTime = microtime(true);
        $filename = basename($filepath);

        try {
            // Import using RatesImporter
            $importer = new RatesImporter($this->pdo);
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
