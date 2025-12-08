<?php

/**
 * Phinx Database Migration Configuration
 *
 * This file configures database migrations for the NWDownloads project.
 * Migrations are stored in db/migrations/ directory.
 *
 * To run migrations:
 * - Development: phinx migrate (runs from host machine, connects to Docker database via exec)
 * - Production: Run from NAS after SSH
 */

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',

        // Development environment (via Docker exec - runs SQL from inside container)
        'development' => [
            'adapter' => 'mysql',
            'host' => 'database',  // Docker service name
            'name' => 'circulation_dashboard',
            'user' => 'root',
            'pass' => 'Mojave48ice',  // From .env DB_ROOT_PASSWORD
            'port' => '3306',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        // Production environment (Synology NAS)
        'production' => [
            'adapter' => 'mysql',
            'host' => 'database',  // Docker service name on NAS
            'name' => 'circulation_dashboard',
            'user' => 'root',
            'pass' => 'RootPassword456!',
            'port' => '3306',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation'
];
