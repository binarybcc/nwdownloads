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
?>
