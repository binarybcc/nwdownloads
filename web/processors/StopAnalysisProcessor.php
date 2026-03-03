<?php

/**
 * Stop Analysis Report Processor
 *
 * Processes Newzware "Stop Analysis Report" CSV files.
 * Captures per-subscriber stop data including contact info,
 * stop reasons, and remarks for drill-down analysis.
 *
 * File Format:
 * - Filename: StopAnalysisReport*.csv
 * - Required Columns: Sub Number, Stop Date, Edition
 * - Date format: M/D/YY
 *
 * Date: 2026-03-03
 */

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/IFileProcessor.php';
require_once __DIR__ . '/../lib/StopAnalysisImporter.php';

use CirculationDashboard\StopAnalysisImporter;
use PDO;
use Exception;

class StopAnalysisProcessor implements IFileProcessor
{
    private PDO $pdo;
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getName(): string
    {
        return 'Stop Analysis Processor';
    }

    public function getDefaultPatterns(): array
    {
        return [
            'StopAnalysisReport*.csv',
            'StopAnalysis*.csv'
        ];
    }

    public function getFileType(): string
    {
        return 'stopanalysis';
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
        $lineCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $lineCount++;
            foreach ($row as $cell) {
                if (stripos(trim($cell), 'Sub Number') !== false) {
                    $header = $row;
                    break 2;
                }
            }
            if ($lineCount > 50) {
                break;
            }
        }
        fclose($handle);

        if (!$header) {
            throw new Exception('Not a Stop Analysis Report (missing "Sub Number" column)');
        }

        return true;
    }

    public function process(string $filepath): ProcessResult
    {
        $startTime = microtime(true);
        $filename = basename($filepath);

        try {
            $importer = new StopAnalysisImporter($this->pdo);
            $result = $importer->import($filepath, $filename);

            $duration = microtime(true) - $startTime;

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
            $duration = microtime(true) - $startTime;

            $failureResult = ProcessResult::failure(
                $filename,
                $this->getFileType(),
                $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );
            $failureResult->processingDuration = $duration;

            return $failureResult;
        }
    }
}
