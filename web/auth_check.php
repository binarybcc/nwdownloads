<?php
/**
 * Authentication Check
 *
 * Include this file at the top of any page that requires authentication.
 * Redirects to login.php if user is not logged in or session has expired.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in - redirect to login page
    header('Location: login.php');
    exit;
}

// Check session timeout (optional security feature)
if (defined('SESSION_TIMEOUT')) {
    $inactive = time() - ($_SESSION['last_activity'] ?? 0);

    if ($inactive > SESSION_TIMEOUT) {
        // Session expired - destroy and redirect
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Optional: Verify user type is still "NW"
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] !== 'NW') {
    // Unauthorized user type
    session_unset();
    session_destroy();
    header('Location: login.php?error=unauthorized');
    exit;
}

// User is authenticated - continue with page load
?>
