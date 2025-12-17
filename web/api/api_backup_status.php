<?php
/**
 * Backup Status API
 *
 * Returns information about backup system status, available backups, and recent logs
 *
 * Response format:
 * {
 *   "status": "success",
 *   "next_backup": "2025-12-21 23:30:00",
 *   "last_backup": "2025-12-17 07:30:35",
 *   "disk_space_gb": 26100,
 *   "backups": [
 *     {
 *       "number": 1,
 *       "name": "backup-1",
 *       "timestamp": "2025-12-17 07:30:35",
 *       "age": "2 hours ago",
 *       "database_size": "1.7M",
 *       "code_size": "996K",
 *       "total_size": "2.7M"
 *     }
 *   ],
 *   "recent_logs": [...]
 * }
 */

header('Content-Type: application/json');

// Backup configuration
define('BACKUP_BASE', '/volume1/homes/newzware/backup');
define('LOG_DIR', BACKUP_BASE . '/logs');

/**
 * Get disk space information (in GB)
 * Uses native PHP function - no shell execution
 */
function getDiskSpace() {
    $free_space = disk_free_space('/volume1');
    if ($free_space !== false) {
        return round($free_space / 1024 / 1024 / 1024, 2); // Convert to GB
    }
    return null;
}

/**
 * Get backup schedule information
 */
function getNextBackupTime() {
    // Schedule: Sun 23:30, Wed 00:30, Fri 00:30
    $now = time();
    $schedules = [
        0 => '23:30', // Sunday
        3 => '00:30', // Wednesday
        5 => '00:30'  // Friday
    ];

    $current_day = (int)date('w'); // 0=Sun, 1=Mon, ..., 6=Sat
    $next_backup = null;

    // Find next scheduled backup
    foreach ($schedules as $day => $time) {
        list($hour, $minute) = explode(':', $time);
        $scheduled_time = strtotime("next " . date('l', strtotime("Sunday +{$day} days")) . " {$hour}:{$minute}:00");

        if ($scheduled_time > $now && ($next_backup === null || $scheduled_time < $next_backup)) {
            $next_backup = $scheduled_time;
        }
    }

    return $next_backup ? date('Y-m-d H:i:s', $next_backup) : null;
}

/**
 * Get information about a specific backup
 */
function getBackupInfo($backup_num) {
    $backup_dir = BACKUP_BASE . "/backup-{$backup_num}";

    if (!is_dir($backup_dir)) {
        return null;
    }

    $db_file = "{$backup_dir}/database/circulation_dashboard.sql.gz";
    $code_file = "{$backup_dir}/code/web-files.tar.gz";

    // Get timestamps
    $timestamp = null;
    if (file_exists($db_file)) {
        $timestamp = filemtime($db_file);
    }

    // Get file sizes
    $db_size = file_exists($db_file) ? filesize($db_file) : 0;
    $code_size = file_exists($code_file) ? filesize($code_file) : 0;

    // Format sizes
    $db_size_formatted = formatBytes($db_size);
    $code_size_formatted = formatBytes($code_size);
    $total_size_formatted = formatBytes($db_size + $code_size);

    // Calculate age
    $age = $timestamp ? getTimeAgo($timestamp) : 'Unknown';

    return [
        'number' => $backup_num,
        'name' => "backup-{$backup_num}",
        'timestamp' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : null,
        'age' => $age,
        'database_size' => $db_size_formatted,
        'code_size' => $code_size_formatted,
        'total_size' => $total_size_formatted,
        'exists' => true
    ];
}

/**
 * Format bytes to human-readable size
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 1) . $units[$pow];
}

/**
 * Get time ago string
 */
function getTimeAgo($timestamp) {
    $diff = time() - $timestamp;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return floor($diff / 604800) . ' weeks ago';
}

/**
 * Get recent backup logs
 */
function getRecentLogs($limit = 10) {
    $log_files = glob(LOG_DIR . '/backup-*.log');
    if (!$log_files) {
        return [];
    }

    // Sort by modification time (newest first)
    usort($log_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $logs = [];
    foreach (array_slice($log_files, 0, $limit) as $log_file) {
        $content = file_get_contents($log_file);
        $timestamp = filemtime($log_file);

        // Check if backup was successful
        $success = strpos($content, 'Backup completed successfully') !== false;

        $logs[] = [
            'filename' => basename($log_file),
            'timestamp' => date('Y-m-d H:i:s', $timestamp),
            'status' => $success ? 'success' : 'failed',
            'age' => getTimeAgo($timestamp)
        ];
    }

    return $logs;
}

/**
 * Main execution
 */
try {
    $response = [
        'status' => 'success',
        'next_backup' => getNextBackupTime(),
        'disk_space_gb' => getDiskSpace(),
        'backups' => [],
        'recent_logs' => getRecentLogs(10)
    ];

    // Get info for all 3 backups
    for ($i = 1; $i <= 3; $i++) {
        $backup_info = getBackupInfo($i);
        if ($backup_info) {
            $response['backups'][] = $backup_info;
        }
    }

    // Get last backup time from most recent backup
    if (!empty($response['backups'])) {
        $response['last_backup'] = $response['backups'][0]['timestamp'];
    } else {
        $response['last_backup'] = null;
    }

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
