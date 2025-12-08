<?php

/**
 * Add contact information columns to subscriber_snapshots table
 * Run once to update schema
 * Date: 2025-12-05
 */

// Database connection
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
    'socket' => getenv('DB_SOCKET') !== false ? getenv('DB_SOCKET') : '/run/mysqld/mysqld10.sock',
];
try {
// Connect
    if (empty($db_config['socket']) || !file_exists($db_config['socket'])) {
        $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    } else {
        $dsn = "mysql:unix_socket={$db_config['socket']};dbname={$db_config['database']};charset=utf8mb4";
    }

    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to database\n\n";
// Add columns
    $columns_to_add = [
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS address VARCHAR(255) COMMENT 'Street address' AFTER on_vacation",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS city_state_postal VARCHAR(150) COMMENT 'City, State, Postal code' AFTER address",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS phone VARCHAR(50) COMMENT 'Phone number' AFTER city_state_postal",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS email VARCHAR(255) COMMENT 'Email address' AFTER phone",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS abc VARCHAR(50) COMMENT 'ABC column from Newzware' AFTER email",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS issue_code VARCHAR(50) COMMENT 'ISS column from Newzware' AFTER abc",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS last_payment_amount DECIMAL(10,2) COMMENT 'Last payment amount' AFTER issue_code",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS login_id VARCHAR(100) COMMENT 'Digital login ID' AFTER last_payment_amount",
        "ALTER TABLE subscriber_snapshots ADD COLUMN IF NOT EXISTS last_login DATETIME COMMENT 'Last digital login date/time' AFTER login_id"
    ];
    foreach ($columns_to_add as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Executed: " . substr($sql, 0, 80) . "...\n";
        } catch (PDOException $e) {
        // Ignore "Duplicate column" errors
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "⚠️  Column already exists (skipping)\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\n";
// Add indexes
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_email ON subscriber_snapshots(email)",
        "CREATE INDEX IF NOT EXISTS idx_phone ON subscriber_snapshots(phone)"
    ];
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Index created\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⚠️  Index already exists (skipping)\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\n✅ Schema update complete!\n\n";
// Verify columns
    echo "📋 Current table structure:\n";
    $stmt = $pdo->query("DESCRIBE subscriber_snapshots");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
