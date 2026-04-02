<?php

/**
 * Authentication Guard
 *
 * Include this file at the top of any page that requires authentication.
 *
 * Flow:
 *   1. Already has a PHP session? → continue (fastest path)
 *   2. Has Newzware cookies (hash + login_id)? → verify with API → auto-login
 *   3. Neither? → redirect to login.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/NwAuth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─────────────────────────────────────────────────
// Path 1: Active PHP session — check validity
// ─────────────────────────────────────────────────
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Check session timeout
    if (defined('SESSION_TIMEOUT')) {
        $inactive = time() - ($_SESSION['last_activity'] ?? 0);
        if ($inactive > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: login.php?timeout=1');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    // Verify user type is still allowed
    if (isset($_SESSION['user_type']) && !in_array($_SESSION['user_type'], NW_ALLOWED_USER_TYPES, true)) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=unauthorized');
        exit;
    }

    // Session valid — continue to page
    return;
}

// ─────────────────────────────────────────────────
// Path 2: No session — try auto-login via cookies
// ─────────────────────────────────────────────────
$auth = new NwAuth();
$result = $auth->tryAutoLogin();

if ($result['success']) {
    $auth->createSession($result['user']);
    // Session created — continue to page
    return;
}

// ─────────────────────────────────────────────────
// Path 3: No session, no valid cookies — login required
// ─────────────────────────────────────────────────
header('Location: login.php');
exit;
