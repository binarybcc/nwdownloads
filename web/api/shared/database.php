<?php

/**
 * Database Connection Module
 * Provides database connection functionality for all API endpoints
 */

/**
 * Get database configuration
 * @return array<string, mixed> Database configuration array
 */
function getDBConfig(): array
{
    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
        'username' => getenv('DB_USER') ?: 'circ_dash',
        'password' => getenv('DB_PASSWORD'),
        'socket' => getenv('DB_SOCKET') !== false ? getenv('DB_SOCKET') : '/run/mysqld/mysqld10.sock',
    ];
}

/**
 * Connect to database
 * @param array<string, mixed> $config Database configuration array
 * @return PDO Database connection
 */
function connectDB(array $config): PDO
{
    try {
        if (empty($config['socket']) || !file_exists($config['socket'])) {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
        } else {
            $dsn = "mysql:unix_socket={$config['socket']};dbname={$config['database']};charset=utf8mb4";
        }
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}
