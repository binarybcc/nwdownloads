# Automated File Processing - Phase 1 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build core infrastructure for automated SFTP file processing - database schema, orchestrator script, processor interface, and file movement logic.

**Architecture:** Event-driven file processing system with pluggable processors. Orchestrator scans inbox folder, matches files to processors via registry pattern, executes processing, and moves files based on results. All processing attempts logged to database for audit trail.

**Tech Stack:** PHP 8.2, MariaDB 10, native file operations, existing upload.php logic

---

## Prerequisites

- Working directory: `.worktrees/automated-file-processing` (feature branch)
- Database credentials available in `.env.credentials`
- SSH access to NAS verified
- Folder structure verified at `/volume1/homes/newzware/`

---

## Task 1: Database Schema - Create Tables

**Files:**

- Create: `database/migrations/20251216_create_file_processing_tables.sql`

**Step 1: Write SQL migration for file_processing_log table**

Create migration file with complete schema:

```sql
-- Migration: Create file processing tables
-- Date: 2025-12-16
-- Purpose: Audit trail and configuration for automated file processing

-- Drop tables if they exist (for clean re-runs during development)
DROP TABLE IF EXISTS file_processing_log;
DROP TABLE IF EXISTS file_processing_patterns;

-- Audit trail of all processing attempts
CREATE TABLE file_processing_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL COMMENT 'allsubscriber, vacation, renewals',
    processor_class VARCHAR(100) NOT NULL COMMENT 'AllSubscriberProcessor, etc',
    status ENUM('processing', 'completed', 'failed', 'skipped') NOT NULL,
    records_processed INT DEFAULT 0,
    error_message TEXT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    processing_duration_seconds DECIMAL(10,2) NULL,
    file_size_bytes INT NULL,
    file_moved_to VARCHAR(255) NULL COMMENT 'completed/, failed/, etc',
    is_backfill BOOLEAN DEFAULT FALSE,
    backfill_weeks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_filename (filename),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    INDEX idx_file_type (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for automated file processing';

-- Configurable filename patterns (Settings page)
CREATE TABLE file_processing_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern VARCHAR(255) NOT NULL COMMENT 'Glob pattern like AllSubscriberReport*.csv',
    processor_class VARCHAR(100) NOT NULL COMMENT 'Class name to handle this pattern',
    description TEXT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE COMMENT 'System default vs user-added',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_pattern (pattern),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Filename pattern to processor mapping';

-- Insert default patterns
INSERT INTO file_processing_patterns
    (pattern, processor_class, description, is_default, enabled)
VALUES
    ('AllSubscriberReport*.csv', 'AllSubscriberProcessor', 'Newzware All Subscriber Report (weekly circulation data)', TRUE, TRUE),
    ('SubscribersOnVacation*.csv', 'VacationProcessor', 'Newzware Subscribers On Vacation export', TRUE, TRUE),
    ('*Renewal*.csv', 'RenewalProcessor', 'Renewal and churn tracking data', TRUE, TRUE),
    ('*Churn*.csv', 'RenewalProcessor', 'Alternative renewal filename pattern', TRUE, TRUE);
```

**Step 2: Test migration on Development database**

Run migration:

```bash
# Source credentials
source .env.credentials

# Run on local development database
docker exec circulation_db mariadb \
  -u"$DEV_DB_USERNAME" -p"$DEV_DB_PASSWORD" -D "$DEV_DB_DATABASE" \
  < database/migrations/20251216_create_file_processing_tables.sql
```

Expected: Success, no errors

**Step 3: Verify tables created**

Check schema:

```bash
docker exec circulation_db mariadb \
  -u"$DEV_DB_USERNAME" -p"$DEV_DB_PASSWORD" -D "$DEV_DB_DATABASE" \
  -e "SHOW TABLES LIKE 'file_processing%';"
```

Expected output:

```
file_processing_log
file_processing_patterns
```

Verify default patterns:

```bash
docker exec circulation_db mariadb \
  -u"$DEV_DB_USERNAME" -p"$DEV_DB_PASSWORD" -D "$DEV_DB_DATABASE" \
  -e "SELECT pattern, processor_class FROM file_processing_patterns ORDER BY id;"
```

Expected: 4 rows with patterns for AllSubscriber, Vacation, Renewal, Churn

**Step 4: Commit migration**

```bash
git add database/migrations/20251216_create_file_processing_tables.sql
git commit -m "feat(database): Add file processing tables and default patterns

- file_processing_log: Audit trail for all processing attempts
- file_processing_patterns: Configurable filename → processor mapping
- Default patterns for AllSubscriber, Vacation, Renewal files
- Tested on development database"
```

---

## Task 2: Processor Interface - Define Contract

**Files:**

- Create: `web/processors/IFileProcessor.php`

**Step 1: Create processors directory**

```bash
mkdir -p web/processors
```

**Step 2: Write processor interface**

Create `web/processors/IFileProcessor.php`:

```php
<?php

namespace CirculationDashboard\Processors;

/**
 * File Processor Interface
 *
 * All file processors must implement this interface to be auto-discovered
 * and registered by the processing orchestrator.
 *
 * Processors are responsible for:
 * - Validating file format and content
 * - Processing file data (import to database, etc.)
 * - Returning structured results
 *
 * Date: 2025-12-16
 */
interface IFileProcessor
{
    /**
     * Get processor name (for logging and UI display)
     *
     * @return string Human-readable processor name
     */
    public function getName(): string;

    /**
     * Get default filename patterns this processor handles
     *
     * Returns glob patterns like: ['AllSubscriberReport*.csv']
     * Used to seed file_processing_patterns table
     *
     * @return array<string> Array of glob patterns
     */
    public function getDefaultPatterns(): array;

    /**
     * Get file type identifier
     *
     * Short identifier stored in file_processing_log.file_type
     * Examples: 'allsubscriber', 'vacation', 'renewals'
     *
     * @return string File type identifier (lowercase, no spaces)
     */
    public function getFileType(): string;

    /**
     * Validate file before processing
     *
     * Check:
     * - File size (< 10MB)
     * - Required columns present
     * - Data completeness (all expected records)
     * - Format correctness
     *
     * @param string $filepath Absolute path to file
     * @return ProcessorValidationResult Validation result with error messages
     */
    public function validate(string $filepath): ProcessorValidationResult;

    /**
     * Process the file
     *
     * Main processing logic:
     * - Parse CSV
     * - Validate data
     * - Import to database
     * - Return results
     *
     * @param string $filepath Absolute path to file
     * @return ProcessorResult Processing result with stats
     * @throws ProcessorException On fatal errors
     */
    public function process(string $filepath): ProcessorResult;
}

/**
 * Validation Result
 */
class ProcessorValidationResult
{
    public bool $valid;
    public array $errors;
    public array $warnings;

    public function __construct(bool $valid, array $errors = [], array $warnings = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
        $this->warnings = $warnings;
    }

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }
}

/**
 * Processing Result
 */
class ProcessorResult
{
    public bool $success;
    public int $recordsProcessed;
    public ?string $errorMessage;
    public array $metadata;

    public function __construct(
        bool $success,
        int $recordsProcessed = 0,
        ?string $errorMessage = null,
        array $metadata = []
    ) {
        $this->success = $success;
        $this->recordsProcessed = $recordsProcessed;
        $this->errorMessage = $errorMessage;
        $this->metadata = $metadata;
    }

    public static function success(int $records, array $metadata = []): self
    {
        return new self(true, $records, null, $metadata);
    }

    public static function failure(string $error): self
    {
        return new self(false, 0, $error);
    }
}

/**
 * Processor Exception
 */
class ProcessorException extends \Exception
{
}
```

**Step 3: Verify PHP syntax**

```bash
cd web/processors
php -l IFileProcessor.php
```

Expected: "No syntax errors detected"

**Step 4: Commit interface**

```bash
git add web/processors/IFileProcessor.php
git commit -m "feat(processors): Add IFileProcessor interface and result classes

- Define contract for all file processors
- Auto-discovery via interface implementation
- Validation and processing result types
- Exception handling for fatal errors"
```

---

## Task 3: AllSubscriber Processor - Wrap Existing Logic

**Files:**

- Create: `web/processors/AllSubscriberProcessor.php`
- Reference: `web/upload.php` (existing logic to wrap)

**Step 1: Create AllSubscriberProcessor class**

Create `web/processors/AllSubscriberProcessor.php`:

```php
<?php

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/IFileProcessor.php';
require_once __DIR__ . '/../upload.php';

/**
 * All Subscriber Report Processor
 *
 * Processes Newzware AllSubscriberReport CSV files
 * Wraps existing upload.php logic for reuse in automated processing
 *
 * File format: AllSubscriberReportYYYYMMDDHHMMSS.csv
 * Example: AllSubscriberReport20251216120000.csv
 *
 * Date: 2025-12-16
 */
class AllSubscriberProcessor implements IFileProcessor
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getName(): string
    {
        return 'All Subscriber Report Processor';
    }

    public function getDefaultPatterns(): array
    {
        return ['AllSubscriberReport*.csv'];
    }

    public function getFileType(): string
    {
        return 'allsubscriber';
    }

    public function validate(string $filepath): ProcessorValidationResult
    {
        $errors = [];
        $warnings = [];

        // Check file exists
        if (!file_exists($filepath)) {
            $errors[] = "File not found: $filepath";
            return new ProcessorValidationResult(false, $errors);
        }

        // Check file size (max 10MB)
        $filesize = filesize($filepath);
        if ($filesize === false) {
            $errors[] = "Could not determine file size";
            return new ProcessorValidationResult(false, $errors);
        }

        if ($filesize > 10 * 1024 * 1024) {
            $errors[] = "File too large: " . round($filesize / 1024 / 1024, 2) . "MB (max 10MB)";
            return new ProcessorValidationResult(false, $errors);
        }

        if ($filesize === 0) {
            $errors[] = "File is empty";
            return new ProcessorValidationResult(false, $errors);
        }

        // Check file is readable
        if (!is_readable($filepath)) {
            $errors[] = "File is not readable (permission denied)";
            return new ProcessorValidationResult(false, $errors);
        }

        // Validate CSV format and required columns
        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            $errors[] = "Failed to open file for reading";
            return new ProcessorValidationResult(false, $errors);
        }

        // Find header row (contains "SUB NUM")
        $header = null;
        $line_count = 0;
        while (($row = fgetcsv($handle)) !== false && $line_count < 50) {
            $line_count++;
            foreach ($row as $cell) {
                if (stripos($cell, 'SUB NUM') !== false) {
                    $header = array_map('trim', $row);
                    break 2;
                }
            }
        }

        if (!$header) {
            fclose($handle);
            $errors[] = "Could not find header row (looking for 'SUB NUM' column). This does not appear to be an All Subscriber Report.";
            return new ProcessorValidationResult(false, $errors);
        }

        // Validate required columns
        $required_columns = ['SUB NUM', 'Ed', 'ISS', 'DEL'];
        $missing_columns = [];

        foreach ($required_columns as $required) {
            $found = false;
            foreach ($header as $col) {
                if (strtoupper(trim($col)) === strtoupper($required)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing_columns[] = $required;
            }
        }

        fclose($handle);

        if (!empty($missing_columns)) {
            $errors[] = "Missing required columns: " . implode(', ', $missing_columns);
            return new ProcessorValidationResult(false, $errors);
        }

        // All validations passed
        return new ProcessorValidationResult(true, $errors, $warnings);
    }

    public function process(string $filepath): ProcessorResult
    {
        try {
            // Extract filename for passing to existing logic
            $filename = basename($filepath);

            // Simulate $_FILES array for existing upload.php logic
            $simulated_files = [
                'allsubscriber' => [
                    'name' => $filename,
                    'tmp_name' => $filepath,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($filepath)
                ]
            ];

            // Call existing processAllSubscriberReport function from upload.php
            // This function expects ($pdo, $tmp_name, $original_filename)
            $result = processAllSubscriberReport(
                $this->pdo,
                $filepath,
                $filename
            );

            // Convert result to ProcessorResult
            return ProcessorResult::success(
                $result['total_processed'],
                [
                    'new_records' => $result['new_records'],
                    'updated_records' => $result['updated_records'],
                    'date_range' => $result['date_range']
                ]
            );
        } catch (\Exception $e) {
            return ProcessorResult::failure($e->getMessage());
        }
    }
}
```

**Step 2: Verify PHP syntax**

```bash
php -l web/processors/AllSubscriberProcessor.php
```

Expected: "No syntax errors detected"

**Step 3: Commit processor**

```bash
git add web/processors/AllSubscriberProcessor.php
git commit -m "feat(processors): Add AllSubscriberProcessor implementation

- Wraps existing upload.php logic for reuse
- Validates file size, format, and required columns
- Handles AllSubscriberReport*.csv files
- Returns structured results for logging"
```

---

## Task 4: Process Orchestrator - Core File Processing Logic

**Files:**

- Create: `web/process-inbox.php`

**Step 1: Write orchestrator script**

Create `web/process-inbox.php`:

```php
<?php

/**
 * File Processing Orchestrator
 *
 * Scans /volume1/homes/newzware/inbox/ for CSV files
 * Matches files to processors via registry pattern
 * Processes files and moves based on results
 * Logs all processing attempts to database
 *
 * Designed to be called by:
 * - Cron job (weekly automated processing)
 * - Manual trigger (Settings page button)
 * - CLI for testing
 *
 * Usage:
 *   php process-inbox.php
 *   php process-inbox.php --dry-run (test mode)
 *
 * Date: 2025-12-16
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Require dependencies
require_once __DIR__ . '/processors/IFileProcessor.php';
require_once __DIR__ . '/processors/AllSubscriberProcessor.php';

use CirculationDashboard\Processors\IFileProcessor;
use CirculationDashboard\Processors\AllSubscriberProcessor;

/**
 * Main Orchestrator Class
 */
class FileProcessingOrchestrator
{
    private \PDO $pdo;
    private string $inboxPath;
    private string $processingPath;
    private string $completedPath;
    private string $failedPath;
    private array $processors = [];
    private bool $dryRun;

    public function __construct(\PDO $pdo, bool $dryRun = false)
    {
        $this->pdo = $pdo;
        $this->dryRun = $dryRun;

        // Set folder paths (production paths on NAS)
        $this->inboxPath = '/volume1/homes/newzware/inbox';
        $this->processingPath = '/volume1/homes/newzware/processing';
        $this->completedPath = '/volume1/homes/newzware/completed';
        $this->failedPath = '/volume1/homes/newzware/failed';

        // Register processors
        $this->registerProcessors();
    }

    /**
     * Register all available processors
     */
    private function registerProcessors(): void
    {
        // Register AllSubscriberProcessor
        $this->processors[] = new AllSubscriberProcessor($this->pdo);

        // Future: Auto-discovery by scanning /processors/ directory
        // For now, manually register each processor
    }

    /**
     * Main execution method
     */
    public function run(): array
    {
        $this->log("=== File Processing Orchestrator Started ===");
        $this->log("Dry run mode: " . ($this->dryRun ? 'YES' : 'NO'));

        $results = [
            'files_processed' => 0,
            'files_succeeded' => 0,
            'files_failed' => 0,
            'files_skipped' => 0,
            'errors' => []
        ];

        try {
            // Step 1: Scan inbox for files
            $files = $this->scanInbox();
            $this->log("Found " . count($files) . " file(s) in inbox");

            if (empty($files)) {
                $this->log("No files to process. Exiting.");
                return $results;
            }

            // Step 2: Match files to processors
            $matched = $this->matchFilesToProcessors($files);

            // Step 3: Process each file
            foreach ($matched as $match) {
                $result = $this->processFile($match['file'], $match['processor']);

                $results['files_processed']++;

                if ($result['success']) {
                    $results['files_succeeded']++;
                } elseif ($result['skipped']) {
                    $results['files_skipped']++;
                } else {
                    $results['files_failed']++;
                    $results['errors'][] = $result['error'];
                }
            }

            $this->log("=== Processing Complete ===");
            $this->log("Processed: {$results['files_processed']}");
            $this->log("Succeeded: {$results['files_succeeded']}");
            $this->log("Failed: {$results['files_failed']}");
            $this->log("Skipped: {$results['files_skipped']}");
        } catch (\Exception $e) {
            $this->log("FATAL ERROR: " . $e->getMessage());
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Scan inbox directory for CSV files
     */
    private function scanInbox(): array
    {
        if (!is_dir($this->inboxPath)) {
            throw new \Exception("Inbox directory does not exist: {$this->inboxPath}");
        }

        $files = [];
        $entries = scandir($this->inboxPath);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $filepath = $this->inboxPath . '/' . $entry;

            // Only process CSV files
            if (is_file($filepath) && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'csv') {
                $files[] = [
                    'filename' => $entry,
                    'filepath' => $filepath,
                    'size' => filesize($filepath),
                    'mtime' => filemtime($filepath)
                ];
            }
        }

        // Sort by modification time (newest first)
        usort($files, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });

        return $files;
    }

    /**
     * Match files to processors based on patterns
     */
    private function matchFilesToProcessors(array $files): array
    {
        $matched = [];

        foreach ($files as $file) {
            $processor = $this->findProcessorForFile($file['filename']);

            if ($processor) {
                $matched[] = [
                    'file' => $file,
                    'processor' => $processor
                ];
            } else {
                $this->log("No processor found for file: {$file['filename']}");
            }
        }

        return $matched;
    }

    /**
     * Find processor for a given filename
     */
    private function findProcessorForFile(string $filename): ?IFileProcessor
    {
        foreach ($this->processors as $processor) {
            $patterns = $processor->getDefaultPatterns();

            foreach ($patterns as $pattern) {
                // Convert glob pattern to regex
                $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/i';

                if (preg_match($regex, $filename)) {
                    return $processor;
                }
            }
        }

        return null;
    }

    /**
     * Process a single file
     */
    private function processFile(array $file, IFileProcessor $processor): array
    {
        $filename = $file['filename'];
        $filepath = $file['filepath'];

        $this->log("\n--- Processing: $filename ---");
        $this->log("Processor: " . $processor->getName());
        $this->log("File size: " . round($file['size'] / 1024, 2) . " KB");

        $startTime = microtime(true);
        $logId = null;

        try {
            // Log processing start
            $logId = $this->logProcessingStart($filename, $processor, $file['size']);

            // Step 1: Move to processing folder
            $processingPath = $this->processingPath . '/' . $filename;

            if (!$this->dryRun) {
                if (!rename($filepath, $processingPath)) {
                    throw new \Exception("Failed to move file to processing folder");
                }
            }

            $this->log("Moved to processing/");

            // Step 2: Validate file
            $validation = $processor->validate($this->dryRun ? $filepath : $processingPath);

            if (!$validation->valid) {
                $error = "Validation failed: " . implode(', ', $validation->errors);
                $this->log("ERROR: $error");

                // Move to failed
                $failedPath = $this->failedPath . '/' . $filename;
                if (!$this->dryRun) {
                    rename($processingPath, $failedPath);
                }

                $this->logProcessingComplete($logId, 'failed', 0, $error, $failedPath, $startTime);

                return [
                    'success' => false,
                    'skipped' => false,
                    'error' => $error
                ];
            }

            $this->log("Validation passed");

            // Step 3: Process file
            $result = $processor->process($this->dryRun ? $filepath : $processingPath);

            if (!$result->success) {
                $error = "Processing failed: " . $result->errorMessage;
                $this->log("ERROR: $error");

                // Move to failed
                $failedPath = $this->failedPath . '/' . $filename;
                if (!$this->dryRun) {
                    rename($processingPath, $failedPath);
                }

                $this->logProcessingComplete($logId, 'failed', 0, $error, $failedPath, $startTime);

                return [
                    'success' => false,
                    'skipped' => false,
                    'error' => $error
                ];
            }

            // Success! Move to completed
            $completedPath = $this->completedPath . '/' . $filename;
            if (!$this->dryRun) {
                rename($processingPath, $completedPath);
            }

            $this->log("SUCCESS: Processed {$result->recordsProcessed} records");
            $this->log("Moved to completed/");

            $this->logProcessingComplete(
                $logId,
                'completed',
                $result->recordsProcessed,
                null,
                $completedPath,
                $startTime
            );

            return [
                'success' => true,
                'skipped' => false,
                'records' => $result->recordsProcessed
            ];
        } catch (\Exception $e) {
            $error = "Exception: " . $e->getMessage();
            $this->log("ERROR: $error");

            // Move to failed if still in processing
            if (!$this->dryRun && isset($processingPath) && file_exists($processingPath)) {
                $failedPath = $this->failedPath . '/' . $filename;
                rename($processingPath, $failedPath);

                $this->logProcessingComplete($logId, 'failed', 0, $error, $failedPath, $startTime);
            }

            return [
                'success' => false,
                'skipped' => false,
                'error' => $error
            ];
        }
    }

    /**
     * Log processing start to database
     */
    private function logProcessingStart(string $filename, IFileProcessor $processor, int $filesize): ?int
    {
        if ($this->dryRun) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO file_processing_log (
                filename, file_type, processor_class, status, file_size_bytes, started_at
            ) VALUES (?, ?, ?, 'processing', ?, NOW())
        ");

        $stmt->execute([
            $filename,
            $processor->getFileType(),
            get_class($processor),
            $filesize
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Log processing completion to database
     */
    private function logProcessingComplete(
        ?int $logId,
        string $status,
        int $records,
        ?string $error,
        ?string $movedTo,
        float $startTime
    ): void {
        if ($this->dryRun || $logId === null) {
            return;
        }

        $duration = microtime(true) - $startTime;

        $stmt = $this->pdo->prepare("
            UPDATE file_processing_log
            SET status = ?,
                records_processed = ?,
                error_message = ?,
                file_moved_to = ?,
                completed_at = NOW(),
                processing_duration_seconds = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $records,
            $error,
            $movedTo,
            round($duration, 2),
            $logId
        ]);
    }

    /**
     * Log message to console
     */
    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

// ============================================================================
// Script Execution
// ============================================================================

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from command line\n");
}

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv ?? []);

// Database configuration
$db_config = [
    'host' => getenv('DB_HOST') ?: 'database',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
];

try {
    // Connect to database
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Run orchestrator
    $orchestrator = new FileProcessingOrchestrator($pdo, $dryRun);
    $results = $orchestrator->run();

    // Exit with appropriate code
    exit($results['files_failed'] > 0 ? 1 : 0);
} catch (\Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
```

**Step 2: Verify PHP syntax**

```bash
php -l web/process-inbox.php
```

Expected: "No syntax errors detected"

**Step 3: Make script executable**

```bash
chmod +x web/process-inbox.php
```

**Step 4: Commit orchestrator**

```bash
git add web/process-inbox.php
git commit -m "feat(orchestrator): Add process-inbox.php file processing orchestrator

- Scans inbox for CSV files
- Matches files to processors via registry
- Validates, processes, and moves files based on results
- Logs all attempts to database
- Supports --dry-run mode for testing
- CLI-only execution for security"
```

---

## Task 5: End-to-End Testing

**Files:**

- Create test files in development environment
- Verify processing workflow

**Step 1: Create test CSV file**

Create a minimal valid All Subscriber Report CSV:

```bash
# Create test file
cat > /tmp/AllSubscriberReport20251216120000.csv << 'EOF'
Company: Newspaper Holding Company
Report Date: 12/16/2025

SUB NUM,Ed,ISS,DEL,Zone
12345,TJ,2025-12-16,MAIL,
12346,TJ,2025-12-16,CARR,
12347,TA,2025-12-16,MAIL,
12348,TA,2025-12-16,INTE,
EOF
```

**Step 2: Copy test file to development inbox (simulate SFTP arrival)**

For local development testing:

```bash
# Note: In development, we'll test with /tmp/ folders first
# Production will use /volume1/homes/newzware/

# Create test folders
mkdir -p /tmp/newzware/{inbox,processing,completed,failed}

# Copy test file
cp /tmp/AllSubscriberReport20251216120000.csv /tmp/newzware/inbox/
```

**Step 3: Temporarily modify orchestrator to use /tmp/ paths for testing**

Edit `web/process-inbox.php` and add constructor parameter for custom paths:

```php
public function __construct(\PDO $pdo, bool $dryRun = false, ?string $basePath = null)
{
    $this->pdo = $pdo;
    $this->dryRun = $dryRun;

    // Use custom base path for testing, or production paths
    $base = $basePath ?? '/volume1/homes/newzware';

    $this->inboxPath = "$base/inbox";
    $this->processingPath = "$base/processing";
    $this->completedPath = "$base/completed";
    $this->failedPath = "$base/failed";

    $this->registerProcessors();
}
```

Then update script execution section to allow test path:

```php
// Add test mode for development
$testMode = in_array('--test', $argv ?? []);
$basePath = $testMode ? '/tmp/newzware' : null;

$orchestrator = new FileProcessingOrchestrator($pdo, $dryRun, $basePath);
```

**Step 4: Run orchestrator in test mode**

```bash
cd web
php process-inbox.php --test
```

Expected output:

```
[2025-12-16 12:00:00] === File Processing Orchestrator Started ===
[2025-12-16 12:00:00] Dry run mode: NO
[2025-12-16 12:00:00] Found 1 file(s) in inbox
[2025-12-16 12:00:00]
--- Processing: AllSubscriberReport20251216120000.csv ---
[2025-12-16 12:00:00] Processor: All Subscriber Report Processor
[2025-12-16 12:00:00] File size: 0.25 KB
[2025-12-16 12:00:00] Moved to processing/
[2025-12-16 12:00:00] Validation passed
[2025-12-16 12:00:00] SUCCESS: Processed 4 records
[2025-12-16 12:00:00] Moved to completed/
[2025-12-16 12:00:00] === Processing Complete ===
[2025-12-16 12:00:00] Processed: 1
[2025-12-16 12:00:00] Succeeded: 1
[2025-12-16 12:00:00] Failed: 0
[2025-12-16 12:00:00] Skipped: 0
```

**Step 5: Verify file moved to completed folder**

```bash
ls /tmp/newzware/completed/
```

Expected: `AllSubscriberReport20251216120000.csv`

**Step 6: Verify database logging**

```bash
docker exec circulation_db mariadb \
  -ucirc_dash -p'Barnaby358@Jones!' -D circulation_dashboard \
  -e "SELECT filename, status, records_processed, file_moved_to FROM file_processing_log ORDER BY id DESC LIMIT 1;"
```

Expected output:

```
+----------------------------------------+-----------+-------------------+------------------------------------------+
| filename                               | status    | records_processed | file_moved_to                            |
+----------------------------------------+-----------+-------------------+------------------------------------------+
| AllSubscriberReport20251216120000.csv  | completed | 4                 | /tmp/newzware/completed/AllSubscriber... |
+----------------------------------------+-----------+-------------------+------------------------------------------+
```

**Step 7: Test validation failure scenario**

Create invalid file (missing required column):

```bash
cat > /tmp/newzware/inbox/AllSubscriberReport20251216130000.csv << 'EOF'
Company: Test
Report Date: 12/16/2025

SUB NUM,Ed,ISS
12345,TJ,2025-12-16
EOF

php process-inbox.php --test
```

Expected: File moves to `failed/` folder, database shows `status='failed'` with error message

**Step 8: Clean up test files**

```bash
rm -rf /tmp/newzware
rm /tmp/AllSubscriberReport*.csv
```

**Step 9: Commit test mode changes**

```bash
git add web/process-inbox.php
git commit -m "feat(orchestrator): Add test mode for development testing

- Support --test flag for local testing with /tmp/ folders
- Allow custom base path for folder locations
- Enables testing without NAS access"
```

---

## Task 6: Production Deployment Preparation

**Files:**

- Create: `docs/DEPLOYMENT-PHASE1.md`

**Step 1: Document production deployment steps**

Create `docs/DEPLOYMENT-PHASE1.md`:

````markdown
# Phase 1 Production Deployment Guide

**Date:** 2025-12-16
**Phase:** Core Infrastructure
**Risk Level:** Low (database-only changes, no UI impact)

---

## Pre-Deployment Checklist

- [ ] All Phase 1 commits merged to master branch
- [ ] Local development testing completed successfully
- [ ] Database migration tested on development
- [ ] Test file processed successfully
- [ ] SSH access to NAS verified
- [ ] Database credentials available

---

## Deployment Steps

### 1. Backup Production Database

```bash
# SSH into NAS
ssh it@192.168.1.254

# Backup database
mysqldump -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock \
  circulation_dashboard > ~/backups/circulation_$(date +%Y%m%d_%H%M%S).sql

# Verify backup
ls -lh ~/backups/circulation_*.sql | tail -1
```
````

### 2. Run Database Migration

```bash
# Still on NAS via SSH

# Navigate to deployment directory
cd /volume1/homes/it/circulation-deploy

# Pull latest from GitHub
git pull origin master

# Run migration
mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock \
  circulation_dashboard < database/migrations/20251216_create_file_processing_tables.sql

# Verify tables created
mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock \
  circulation_dashboard \
  -e "SHOW TABLES LIKE 'file_processing%';"

# Verify default patterns
mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock \
  circulation_dashboard \
  -e "SELECT pattern, processor_class FROM file_processing_patterns;"
```

Expected: 2 tables created, 4 default patterns inserted

### 3. Deploy Code Files

```bash
# Run deployment script (pulls from GitHub, syncs to production)
~/deploy-circulation.sh

# Verify process-inbox.php deployed
ls -lh /volume1/web/circulation/process-inbox.php

# Verify processors directory
ls -lh /volume1/web/circulation/processors/
```

### 4. Test Orchestrator (Dry Run)

```bash
# Test with --dry-run flag (won't actually move files or modify database)
php /volume1/web/circulation/process-inbox.php --dry-run
```

Expected: "No files to process. Exiting." (inbox is empty)

### 5. Verify Folder Permissions

```bash
# Check folder ownership and permissions
ls -la /volume1/homes/newzware/

# All folders should be writable by 'it' user (or http user if different)
```

---

## Post-Deployment Verification

### Database Tables

```bash
mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock \
  circulation_dashboard \
  -e "DESC file_processing_log;"

mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock \
  circulation_dashboard \
  -e "DESC file_processing_patterns;"
```

### Code Files

```bash
# Verify orchestrator exists
php -l /volume1/web/circulation/process-inbox.php

# Verify processors
php -l /volume1/web/circulation/processors/IFileProcessor.php
php -l /volume1/web/circulation/processors/AllSubscriberProcessor.php
```

---

## Rollback Procedure

**If deployment fails:**

```bash
# 1. Restore database from backup
mysql -uroot -p'P@ta675N0id' -S /run/mysqld/mysqld10.sock \
  circulation_dashboard < ~/backups/circulation_YYYYMMDD_HHMMSS.sql

# 2. Revert code deployment
cd /volume1/homes/it/circulation-deploy
git reset --hard <previous-commit-hash>
~/deploy-circulation.sh
```

---

## Success Criteria

- ✅ Database tables created and populated with default patterns
- ✅ Code files deployed to `/volume1/web/circulation/`
- ✅ Orchestrator runs without errors (even with empty inbox)
- ✅ No impact on existing dashboard functionality
- ✅ Manual upload still works via upload.html

---

## Next Steps

**After Phase 1 deployment:**

- Phase 1 is complete and production-ready
- Phase 2 will add notification system (email + dashboard banners)
- Phase 3 will add Settings page UI for configuration
- No cron job scheduled yet (manual trigger only for now)

````

**Step 2: Commit deployment documentation**

```bash
git add docs/DEPLOYMENT-PHASE1.md
git commit -m "docs: Add Phase 1 production deployment guide

- Pre-deployment checklist
- Step-by-step deployment instructions
- Verification procedures
- Rollback procedure
- Success criteria"
````

---

## Task 7: Merge to Master

**Prerequisites:**

- All commits completed and tested
- Documentation updated
- Ready for production deployment

**Step 1: Switch to worktree and verify all commits**

```bash
cd .worktrees/automated-file-processing
git log --oneline master..HEAD
```

Expected: List of all Phase 1 commits

**Step 2: Run final verification**

```bash
# Verify PHP syntax on all new files
find web/processors -name "*.php" -exec php -l {} \;
php -l web/process-inbox.php

# Verify database migration syntax
# (no SQL syntax checker, but visual inspection)
cat database/migrations/20251216_create_file_processing_tables.sql
```

**Step 3: Switch back to main directory**

```bash
cd /Users/johncorbin/Desktop/projs/nwdownloads
```

**Step 4: Create Pull Request**

```bash
# Push feature branch to GitHub
git push -u origin feature/automated-file-processing

# Create PR
gh pr create \
  --title "Phase 1: Automated File Processing Core Infrastructure" \
  --body "## Summary
Implements core infrastructure for automated SFTP file processing system.

## Phase 1 Deliverables
- ✅ Database schema (file_processing_log, file_processing_patterns)
- ✅ IFileProcessor interface for pluggable processors
- ✅ AllSubscriberProcessor implementation (wraps upload.php)
- ✅ process-inbox.php orchestrator with file scanning and routing
- ✅ File movement logic (inbox → processing → completed/failed)
- ✅ Database logging for all processing attempts
- ✅ Test mode for local development
- ✅ Production deployment documentation

## Testing
- ✅ Database migration tested on development
- ✅ End-to-end file processing tested with sample CSV
- ✅ Validation failure scenario tested
- ✅ Folder permissions verified on NAS
- ✅ Orchestrator syntax verified

## Database Changes
- New table: file_processing_log (audit trail)
- New table: file_processing_patterns (filename → processor mapping)
- Default patterns seeded for AllSubscriber, Vacation, Renewal files

## Deployment Notes
- No cron job scheduled yet (manual trigger only)
- No UI changes (invisible to users)
- Manual upload via upload.html still works
- See docs/DEPLOYMENT-PHASE1.md for deployment guide

## Next Steps
- Phase 2: Notification system (email + dashboard)
- Phase 3: Settings page UI
- Phase 4: Additional processors (Vacation, Renewal)

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

**Step 5: Wait for review or merge**

If ready to merge immediately:

```bash
gh pr merge --squash
```

**Step 6: Clean up worktree (after merge)**

Use `superpowers:finishing-a-development-branch` skill for proper cleanup

---

## Summary

**Phase 1 Complete!**

This plan creates the foundational infrastructure for automated file processing:

1. ✅ **Database tables** for logging and configuration
2. ✅ **Processor interface** for pluggable file handlers
3. ✅ **AllSubscriber processor** wrapping existing upload logic
4. ✅ **Orchestrator script** for scanning, matching, and processing files
5. ✅ **File movement** based on processing results
6. ✅ **Database logging** for audit trail
7. ✅ **Testing support** with --test and --dry-run modes
8. ✅ **Deployment documentation** for production

**Next Phases:**

- **Phase 2:** Email and dashboard notifications
- **Phase 3:** Settings page UI for configuration
- **Phase 4:** Vacation and Renewal processors
- **Phase 5:** Cron job setup and production testing
- **Phase 6:** Monitoring and optimization

---

**Estimated Time:** 2-3 hours for implementation + testing + deployment

**Risk Level:** Low (no UI changes, database-only, backward compatible)

**Dependencies:** None (all prerequisites verified)
