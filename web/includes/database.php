<?php

/**
 * Centralized Database Connection Helper
 *
 * Provides a single source of truth for database connections.
 * Uses singleton pattern to ensure only one connection per request.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/database.php';
 *   $pdo = getDatabase();
 *
 * @return PDO Database connection instance
 * @throws PDOException if connection fails
 */

/**
 * Get database connection (singleton)
 *
 * @return PDO
 */
function getDatabase()
{
    static $pdo = null;

    if ($pdo === null) {
        // Get database configuration from environment or defaults
        $db_host = getenv('DB_HOST') ?: 'localhost';
        $db_port = getenv('DB_PORT') ?: '3306';
        $db_name = getenv('DB_NAME') ?: 'circulation_dashboard';
        $db_user = getenv('DB_USER') ?: 'circ_dash';
        $db_pass = getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!';
        $db_socket = getenv('DB_SOCKET') ?: '';

        // Determine DSN based on socket vs TCP connection
        if ($db_socket) {
            $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name";
        } else {
            $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name";
        }

        // Create PDO connection with options
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
    }

    return $pdo;
}

/**
 * Get database configuration (for debugging/info purposes)
 *
 * @return array Database configuration (without password)
 */
function getDatabaseConfig()
{
    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
        'user' => getenv('DB_USER') ?: 'circ_dash',
        'socket' => getenv('DB_SOCKET') ?: '',
        'using_socket' => (bool)(getenv('DB_SOCKET') ?: '')
    ];
}
