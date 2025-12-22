<?php

/**
 * Get Processing History API
 * Returns recent file processing runs from database
 */

header('Content-Type: application/json');

// Security: Require authentication
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Database connection
try {
    $pdo = new PDO(
        'mysql:host=database;port=3306;dbname=circulation_dashboard;charset=utf8mb4',
        getenv('DB_USER') ?: 'circ_dash',
        getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get optional status filter
$statusFilter = $_GET['status'] ?? null;
$validStatuses = ['completed', 'failed', 'processing'];

if ($statusFilter && !in_array($statusFilter, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid status filter']);
    exit;
}

// Build query
$query = "
    SELECT
        id,
        filename,
        file_type,
        processor_class,
        status,
        records_processed,
        processing_duration_seconds,
        error_message,
        DATE_FORMAT(started_at, '%Y-%m-%d %H:%i:%s') as started_at,
        DATE_FORMAT(completed_at, '%Y-%m-%d %H:%i:%s') as completed_at
    FROM file_processing_log
";

if ($statusFilter) {
    $query .= " WHERE status = :status";
}

$query .= " ORDER BY started_at DESC LIMIT 50";

try {
    $stmt = $pdo->prepare($query);

    if ($statusFilter) {
        $stmt->execute(['status' => $statusFilter]);
    } else {
        $stmt->execute();
    }

    $history = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'history' => $history,
        'count' => count($history)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch history']);
}
