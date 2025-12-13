<?php

/**
 * Cache Management API
 *
 * Provides cache statistics and manual cache clearing functionality
 * for the dashboard's file-based cache system.
 *
 * Actions:
 * - stats: Get cache statistics (file count, size, age)
 * - clear: Clear all cache files
 */

require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../SimpleCache.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $cache = new SimpleCache();
    $action = $_GET['action'] ?? 'stats';

    switch ($action) {
        case 'stats':
            // Get cache statistics
            $stats = $cache->getStats();

            echo json_encode([
                'success' => true,
                'action' => 'stats',
                'cache_directory' => '/tmp/dashboard_cache/',
                'file_count' => $stats['file_count'],
                'total_size_mb' => $stats['total_size_mb'],
                'oldest_file_age_hours' => $stats['oldest_file_age_hours'],
                'oldest_file_age_days' => round($stats['oldest_file_age_hours'] / 24, 1),
                'status' => $stats['file_count'] > 0 ? 'active' : 'empty'
            ], JSON_PRETTY_PRINT);
            break;

        case 'clear':
            // Verify CSRF token for destructive actions
            session_start();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Clear action requires POST request');
            }

            $csrf_token = $_POST['csrf_token'] ?? '';
            if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
                throw new Exception('Invalid CSRF token');
            }

            // Clear all caches
            $cleared_count = $cache->clear();

            echo json_encode([
                'success' => true,
                'action' => 'clear',
                'cleared_count' => $cleared_count,
                'message' => "Cleared $cleared_count cache file(s). Next API request will regenerate fresh data.",
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            break;

        default:
            throw new Exception("Unknown action: $action. Valid actions: stats, clear");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
