<?php

/**
 * File Processor Interface
 *
 * All file processors must implement this interface to enable plugin-like
 * auto-discovery and consistent processing architecture.
 *
 * Design Pattern: Strategy Pattern + Plugin Architecture
 * - Each processor encapsulates a specific file processing strategy
 * - Processors are auto-discovered by scanning the processors/ directory
 * - New processors can be added without modifying orchestrator code
 *
 * Usage:
 *   1. Create new class implementing IFileProcessor
 *   2. Place in web/processors/ directory
 *   3. System auto-discovers on next cron run
 *   4. Configure filename patterns in Settings page
 *
 * Date: 2025-12-16
 */

namespace CirculationDashboard\Processors;

require_once __DIR__ . '/ProcessResult.php';

/**
 * Interface for file processors
 *
 * All file processors must implement these methods to enable
 * auto-discovery and consistent processing workflow.
 */
interface IFileProcessor
{
    /**
     * Get processor name for logging and display
     *
     * Used in Settings page, processing logs, and notifications.
     *
     * @return string Human-readable processor name (e.g., "All Subscriber Processor")
     */
    public function getName(): string;

    /**
     * Get default filename patterns this processor handles
     *
     * Returns array of glob-style patterns. These become default
     * patterns in file_processing_patterns table on first run.
     *
     * Pattern syntax:
     * - * = matches any characters (e.g., "Report*.csv")
     * - ? = matches single character (e.g., "file?.csv")
     *
     * @return array<string> Array of filename patterns
     *
     * @example
     * return ['AllSubscriberReport*.csv', 'AllSub*.csv'];
     */
    public function getDefaultPatterns(): array;

    /**
     * Get file type identifier
     *
     * Short string identifying this file type for database logging
     * and notification routing.
     *
     * @return string File type (e.g., "allsubscriber", "vacation", "renewals")
     */
    public function getFileType(): string;

    /**
     * Validate file before processing
     *
     * Checks file meets requirements:
     * - File size within limits
     * - Required columns present
     * - Data appears complete
     * - File format is correct
     *
     * Called BEFORE process() to fail fast on invalid files.
     * Validation failures move file to failed/ folder immediately.
     *
     * @param string $filepath Absolute path to file in processing/ folder
     * @return bool True if file is valid and ready to process
     * @throws \Exception with detailed error message if validation fails
     */
    public function validate(string $filepath): bool;

    /**
     * Process the file and import data
     *
     * Main processing logic:
     * 1. Parse file contents
     * 2. Transform data as needed
     * 3. Import to database (with transaction)
     * 4. Return result with statistics
     *
     * Called AFTER validate() succeeds.
     * Exceptions are caught by orchestrator and logged.
     * Database transactions should handle rollback internally.
     *
     * @param string $filepath Absolute path to file in processing/ folder
     * @return ProcessResult Success/failure with statistics
     * @throws \Exception with detailed error message if processing fails
     */
    public function process(string $filepath): ProcessResult;
}
