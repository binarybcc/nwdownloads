<?php

/**
 * Phinx Database Migration Configuration
 *
 * This file configures database migrations for the NWDownloads project.
 * Migrations are stored in db/migrations/ directory.
 *
 * To run migrations:
 * - Run from NAS after SSH: ssh nas, then run phinx migrate
 */

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'production',

        // Production environment (Synology NAS - native MariaDB via Unix socket)
        'production' => [
            'adapter' => 'mysql',
            'unix_socket' => '/run/mysqld/mysqld10.sock',
            'name' => 'circulation_dashboard',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation'
];
