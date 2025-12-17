<?php

/**
 * Save Notification Settings API
 * Updates notification_settings table with new configuration
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate email addresses
$emailAddresses = json_decode($input['email_addresses'] ?? '[]', true);
if (!is_array($emailAddresses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email addresses format']);
    exit;
}

foreach ($emailAddresses as $email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address: ' . htmlspecialchars($email)]);
        exit;
    }
}

// Update settings
try {
    $stmt = $pdo->prepare("
        UPDATE notification_settings
        SET setting_value = :value
        WHERE setting_key = :key
    ");

    $settings = [
        'email_addresses' => $input['email_addresses'],
        'enable_failure_emails' => $input['enable_failure_emails'] ?? 'true',
        'enable_success_dashboard' => $input['enable_success_dashboard'] ?? 'true'
    ];

    foreach ($settings as $key => $value) {
        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save settings']);
}
