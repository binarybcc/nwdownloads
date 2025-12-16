<?php

/**
 * Utility Functions Module
 * Provides shared utility functions for API endpoints
 */

/**
 * Get week boundaries (Sunday-Saturday) for a given date
 * @param string $date Date in Y-m-d format
 * @return array{start: string, end: string, week_num: int, year: int} Week boundaries
 */
function getWeekBoundaries(string $date): array
{
    $dt = new DateTime($date);
    $dayOfWeek = (int)$dt->format('w'); // 0=Sunday, 6=Saturday

    // Find Sunday of this week
    $sunday = clone $dt;
    $sunday->modify('-' . $dayOfWeek . ' days');

    // Find Saturday of this week
    $saturday = clone $sunday;
    $saturday->modify('+6 days');

    // Calculate week number from the actual date (not Sunday) to match upload.php logic
    // This ensures API and upload.php use the same week numbering
    $week_num = (int)$dt->format('W');
    $year = (int)$dt->format('Y');

    return [
        'start' => $sunday->format('Y-m-d'),
        'end' => $saturday->format('Y-m-d'),
        'week_num' => $week_num,
        'year' => $year
    ];
}

/**
 * Get Saturday date for a week (our snapshot day)
 * @param string $date Date in Y-m-d format
 * @return string Saturday date in Y-m-d format
 */
function getSaturdayForWeek(string $date): string
{
    $boundaries = getWeekBoundaries($date);
    return $boundaries['end'];
}

/**
 * Validate date format
 * @param string $date Date string to validate
 * @return bool True if valid Y-m-d format
 */
function isValidDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Sanitize and validate required parameter
 * @param array<string, mixed> $params Parameter array
 * @param string $key Parameter key
 * @param string $errorMessage Error message if missing
 * @return string Sanitized parameter value
 */
function requireParam(array $params, string $key, string $errorMessage): string
{
    if (empty($params[$key])) {
        sendBadRequest($errorMessage);
    }
    return trim((string)$params[$key]);
}

/**
 * Get optional parameter with default value
 * @param array<string, mixed> $params Parameter array
 * @param string $key Parameter key
 * @param mixed $default Default value if not set
 * @return mixed Parameter value or default
 */
function getParam(array $params, string $key, mixed $default = null): mixed
{
    return $params[$key] ?? $default;
}
