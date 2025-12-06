<?php
/**
 * Configuration for Newzware Authentication
 *
 * This file contains the Newzware API endpoint and site ID
 * used for authenticating users against the centralized Newzware system.
 */

// Newzware Authentication API endpoint
define('NW_AUTH_URL', 'https://seneca.newzware.com/authentication/auth70_xml.jsp');

// Newzware Site ID
define('NW_SITE_ID', 'seneca');

// Session timeout (in seconds) - 2 hours
define('SESSION_TIMEOUT', 7200);

// Application name for login page
define('APP_NAME', 'Circulation Dashboard');

// ============================================================
// Session Security Configuration
// ============================================================

// Prevent JavaScript access to session cookies (protects against XSS)
ini_set('session.cookie_httponly', 1);

// Only send session cookies over HTTPS (conditional based on environment)
// For development on localhost (HTTP), this is disabled
// For production (HTTPS), this is enabled
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || ($_SERVER['SERVER_PORT'] == 443);

if ($isHttps) {
    ini_set('session.cookie_secure', 1);  // Only send over HTTPS
}

// Prevent CSRF attacks by restricting cookie sending to same-site requests
ini_set('session.cookie_samesite', 'Strict');

// Prevent session fixation attacks by rejecting uninitialized session IDs
ini_set('session.use_strict_mode', 1);

// Regenerate session ID periodically to reduce risk window
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

?>
