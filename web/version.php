<?php
/**
 * Auto-versioning system for Circulation Dashboard
 *
 * Version format: MAJOR.MINOR.BUILD (e.g., 2.0.145)
 * - MAJOR: Incremented manually for major releases
 * - MINOR: Incremented manually for feature releases
 * - BUILD: Auto-incremented on each deployment
 *
 * Build number is based on deployment count, not git commits.
 */

// Current version
define('VERSION_MAJOR', 2);
define('VERSION_MINOR', 0);

// Build number file location
$buildFile = __DIR__ . '/.build_number';

// Initialize build number if file doesn't exist
if (!file_exists($buildFile)) {
    file_put_contents($buildFile, '1');
}

// Read current build number
$buildNumber = (int) file_get_contents($buildFile);

// Full version string
define('VERSION_BUILD', $buildNumber);
define('VERSION_FULL', VERSION_MAJOR . '.' . VERSION_MINOR . '.' . VERSION_BUILD);
define('VERSION_STRING', 'v' . VERSION_FULL);

/**
 * Increment build number (call this on deployment)
 */
function incrementBuildNumber() {
    global $buildFile;
    $current = (int) file_get_contents($buildFile);
    $new = $current + 1;
    file_put_contents($buildFile, $new);
    return $new;
}

/**
 * Get version info array
 */
function getVersionInfo() {
    return [
        'major' => VERSION_MAJOR,
        'minor' => VERSION_MINOR,
        'build' => VERSION_BUILD,
        'full' => VERSION_FULL,
        'string' => VERSION_STRING,
        'deployment_date' => file_exists(__DIR__ . '/.build_number')
            ? date('Y-m-d H:i:s', filemtime(__DIR__ . '/.build_number'))
            : null
    ];
}
