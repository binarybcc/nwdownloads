<?php
/**
 * Export database via PHP (since we can connect from web context)
 */

$db_config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'circulation_dashboard',
    'username' => 'circ_dash',
    'password' => 'Barnaby358@Jones!',
    'socket' => '/run/mysqld/mysqld10.sock',
];

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="circulation_dashboard.sql"');

try {
    if (file_exists($db_config['socket'])) {
        $dsn = "mysql:unix_socket={$db_config['socket']};dbname={$db_config['database']};charset=utf8mb4";
    } else {
        $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    }
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo "-- Circulation Dashboard SQL Dump\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        echo "-- Table: $table\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $createTable['Create Table'] . ";\n\n";

        // Get row count
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "-- Inserting $count rows into $table\n";

        if ($count > 0) {
            // Export data in batches
            $batchSize = 1000;
            $offset = 0;

            while ($offset < $count) {
                $stmt = $pdo->query("SELECT * FROM `$table` LIMIT $batchSize OFFSET $offset");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    // Get column names from first row
                    $columns = array_keys($rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';

                    echo "INSERT INTO `$table` ($columnList) VALUES\n";

                    $values = [];
                    foreach ($rows as $row) {
                        $escapedValues = array_map(function($val) use ($pdo) {
                            return $val === null ? 'NULL' : $pdo->quote($val);
                        }, array_values($row));
                        $values[] = '(' . implode(', ', $escapedValues) . ')';
                    }

                    echo implode(",\n", $values) . ";\n\n";
                }

                $offset += $batchSize;
            }
        }

        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";

} catch (Exception $e) {
    echo "-- Error: " . $e->getMessage() . "\n";
}
?>
