<?php

/**
 * Process Result Value Object
 *
 * Standardizes return format across all file processors for consistent
 * logging, notification, and error handling.
 *
 * Date: 2025-12-23
 * Split from IFileProcessor.php for PSR-12 compliance
 */

namespace CirculationDashboard\Processors;

/**
 * Result object returned by file processors
 *
 * Standardizes return format across all processors for consistent
 * logging, notification, and error handling.
 */
class ProcessResult
{
    /** @var bool Whether processing succeeded */
    public bool $success;

    /** @var string Original filename */
    public string $filename;

    /** @var string File type identifier (allsubscriber, vacation, etc.) */
    public string $fileType;

    /** @var int Number of records processed */
    public int $recordsProcessed;

    /** @var string|null Date range of processed data (e.g., "2025-12-01 to 2025-12-07") */
    public ?string $dateRange;

    /** @var string|null Error message if failed */
    public ?string $errorMessage;

    /** @var array<string, mixed> Additional metadata for notifications/logging */
    public array $metadata;

    /** @var float Processing duration in seconds */
    public float $processingDuration;

    /**
     * Create successful result
     *
     * @param string $filename Original filename
     * @param string $fileType File type identifier
     * @param int $recordsProcessed Number of records imported
     * @param string|null $dateRange Date range of data (optional)
     * @param array<string, mixed> $metadata Additional data (optional)
     * @return self
     */
    public static function success(
        string $filename,
        string $fileType,
        int $recordsProcessed,
        ?string $dateRange = null,
        array $metadata = []
    ): self {
        $result = new self();
        $result->success = true;
        $result->filename = $filename;
        $result->fileType = $fileType;
        $result->recordsProcessed = $recordsProcessed;
        $result->dateRange = $dateRange;
        $result->errorMessage = null;
        $result->metadata = $metadata;
        $result->processingDuration = 0.0; // Will be set by orchestrator
        return $result;
    }

    /**
     * Create failure result
     *
     * @param string $filename Original filename
     * @param string $fileType File type identifier
     * @param string $errorMessage Detailed error message
     * @param array<string, mixed> $metadata Additional error context (optional)
     * @return self
     */
    public static function failure(
        string $filename,
        string $fileType,
        string $errorMessage,
        array $metadata = []
    ): self {
        $result = new self();
        $result->success = false;
        $result->filename = $filename;
        $result->fileType = $fileType;
        $result->recordsProcessed = 0;
        $result->dateRange = null;
        $result->errorMessage = $errorMessage;
        $result->metadata = $metadata;
        $result->processingDuration = 0.0; // Will be set by orchestrator
        return $result;
    }
}
