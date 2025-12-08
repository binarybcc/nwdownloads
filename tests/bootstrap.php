<?php

/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment before running tests
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone
date_default_timezone_set('America/Denver');

// Define test constants
define('TESTING', true);
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Load environment variables for testing
$_ENV['DB_HOST'] = 'localhost';
$_ENV['DB_PORT'] = '3306';
$_ENV['DB_NAME'] = 'circulation_dashboard_test';
$_ENV['DB_USER'] = 'circ_dash';
$_ENV['DB_PASSWORD'] = 'Barnaby358@Jones!';
