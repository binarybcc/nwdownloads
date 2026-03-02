<?php

/**
 * New Subscription Starts Processor
 *
 * Processes Newzware "New Subscription Starts" CSV files.
 * Tracks individual new subscriber start events and classifies them
 * as truly new or restarts via cross-reference against renewal_events.
 *
 * File Format:
 * - Filename: NewSubscriptionStarts*.csv
 * - Required Columns: SUB NUM, STARTED, Ed
 * - Date format: M/D/YY
 *
 * Date: 2026-03-02
 */

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/IFileProcessor.php';
require_once __DIR__ . '/../lib/NewStartsImporter.php';

use CirculationDashboard\NewStartsImporter;
use PDO;
use Exception;

class NewStartsProcessor implements IFileProcessor
{
    private PDO $pdo;
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getName(): string
    {
        return 'New Subscription Starts Processor';
    }

    public function getDefaultPatterns(): array
    {
        return [
            'NewSubscriptionStarts*.csv',
            'NewSubscription*.csv',
            'NewStart*.csv'
        ];
    }

    public function getFileType(): string
    {
        return 'newstarts';
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
                if (stripos(trim($cell), 'SUB NUM') !== false) {
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
            throw new Exception('Not a New Subscription Starts report (missing "SUB NUM" column)');
        }

        return true;
    }

    public function process(string $filepath): ProcessResult
    {
        $startTime = microtime(true);
        $filename = basename($filepath);

        try {
            $importer = new NewStartsImporter($this->pdo);
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
                    'truly_new' => $result['truly_new'],
                    'restarts' => $result['restarts'],
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
