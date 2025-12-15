-- Migration tracking table
-- This table tracks which migrations have been applied to the database
-- Created: 2025-12-11

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_number INT NOT NULL PRIMARY KEY,
  migration_name VARCHAR(255) NOT NULL,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  applied_by VARCHAR(100) DEFAULT NULL,
  INDEX idx_applied_at (applied_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mark this migration as applied
INSERT INTO schema_migrations (migration_number, migration_name, applied_by)
VALUES (0, 'create_migrations_table', 'system')
ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP;
