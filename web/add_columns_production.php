<?php

/**
 * Add new columns to production database
 */

$db_config = [
    'host' => getenv('DB_HOST') ?: 'database',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
];
try {
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected to database successfully.\n\n";
// Add columns
    $columns = [
        "ALTER TABLE subscriber_snapshots ADD COLUMN address VARCHAR(255)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN city_state_postal VARCHAR(100)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN abc VARCHAR(10)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN issue_code VARCHAR(10)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN last_payment_amount DECIMAL(10,2)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN phone VARCHAR(20)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN email VARCHAR(100)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN login_id VARCHAR(50)",
        "ALTER TABLE subscriber_snapshots ADD COLUMN last_login DATE"
    ];
    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ " . substr($sql, 0, 80) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "⊘ Column already exists: " . substr($sql, 0, 80) . "...\n";
            } else {
                throw $e;
            }
        }
    }

    // Add indexes
    echo "\nAdding indexes...\n";
    $indexes = [
        "CREATE INDEX idx_email ON subscriber_snapshots(email)",
        "CREATE INDEX idx_phone ON subscriber_snapshots(phone)",
        "CREATE INDEX idx_login_id ON subscriber_snapshots(login_id)"
    ];
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
            echo "✓ " . substr($sql, 0, 80) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "⊘ Index already exists: " . substr($sql, 0, 80) . "...\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\n✓ Database schema updated successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
