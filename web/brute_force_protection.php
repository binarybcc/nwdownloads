<?php
/**
 * Brute Force Protection
 *
 * Prevents password guessing and credential stuffing attacks by:
 * - Tracking failed login attempts per user + IP combination
 * - Blocking after 5 failed attempts for 15 minutes
 * - Resetting counter after successful login
 *
 * Usage:
 *   require_once 'brute_force_protection.php';
 *   if (!checkBruteForce($login_id)) { die('Too many attempts'); }
 *   recordFailedAttempt($login_id);  // After failed login
 *   resetAttempts($login_id);        // After successful login
 */

/**
 * Check if user+IP combination is allowed to attempt login
 *
 * @param string $login_id User login ID
 * @return bool True if login attempt allowed, false if blocked
 */
function checkBruteForce($login_id) {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Create unique key combining login ID and IP address
    // This prevents one user from locking out another user's account
    $key = 'login_attempts_' . md5($login_id . $_SERVER['REMOTE_ADDR']);

    // Initialize tracking if not exists
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
        return true;
    }

    $attempts = $_SESSION[$key];

    // Reset counter after 15 minutes (900 seconds)
    if (time() - $attempts['first_attempt'] > 900) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
        return true;
    }

    // Block after 5 failed attempts
    if ($attempts['count'] >= 5) {
        $timeRemaining = 900 - (time() - $attempts['first_attempt']);
        $minutesRemaining = ceil($timeRemaining / 60);

        // Store lockout message for display
        $_SESSION['lockout_message'] = "Too many failed attempts. Please try again in {$minutesRemaining} minute(s).";

        return false;
    }

    return true;
}

/**
 * Record a failed login attempt
 *
 * @param string $login_id User login ID
 * @return void
 */
function recordFailedAttempt($login_id) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = 'login_attempts_' . md5($login_id . $_SERVER['REMOTE_ADDR']);

    // Initialize if not exists
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'first_attempt' => time()
        ];
    }

    // Increment attempt counter
    $_SESSION[$key]['count']++;

    // Log suspicious activity (for security monitoring)
    if ($_SESSION[$key]['count'] >= 5) {
        error_log(sprintf(
            'SECURITY: Brute force attempt blocked - Login: %s, IP: %s, Attempts: %d',
            $login_id,
            $_SERVER['REMOTE_ADDR'],
            $_SESSION[$key]['count']
        ));
    }
}

/**
 * Reset attempt counter after successful login
 *
 * @param string $login_id User login ID
 * @return void
 */
function resetAttempts($login_id) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = 'login_attempts_' . md5($login_id . $_SERVER['REMOTE_ADDR']);

    // Clear all attempt tracking for this user+IP
    unset($_SESSION[$key]);
    unset($_SESSION['lockout_message']);
}

/**
 * Get remaining lockout time in seconds
 *
 * @param string $login_id User login ID
 * @return int Seconds remaining in lockout (0 if not locked)
 */
function getLockoutTimeRemaining($login_id) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = 'login_attempts_' . md5($login_id . $_SERVER['REMOTE_ADDR']);

    if (!isset($_SESSION[$key])) {
        return 0;
    }

    $attempts = $_SESSION[$key];

    if ($attempts['count'] < 5) {
        return 0;
    }

    $timeRemaining = 900 - (time() - $attempts['first_attempt']);

    return max(0, $timeRemaining);
}
?>
