-- Raw Uploads Table - Immutable Source of Truth
-- This table stores the exact CSV data as uploaded, never modified
-- Acts as authoritative source for reprocessing if needed

CREATE TABLE IF NOT EXISTS raw_uploads (
    upload_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    upload_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- File metadata
    filename VARCHAR(255) NOT NULL COMMENT 'Original CSV filename',
    file_size INT NOT NULL COMMENT 'File size in bytes',
    file_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash for duplicate detection',

    -- CSV metadata
    snapshot_date DATE NOT NULL COMMENT 'Snapshot date extracted from data',
    row_count INT NOT NULL COMMENT 'Total rows in CSV',
    subscriber_count INT NOT NULL COMMENT 'Actual subscriber records processed',

    -- Raw data storage
    raw_csv_data LONGTEXT NOT NULL COMMENT 'Complete CSV file content',

    -- Processing metadata
    processed_at TIMESTAMP NULL COMMENT 'When data was processed into subscriber_snapshots',
    processing_status ENUM('pending', 'completed', 'failed', 'reprocessing') DEFAULT 'completed',
    processing_errors TEXT NULL COMMENT 'Any errors during processing',

    -- Upload source tracking
    uploaded_by VARCHAR(100) DEFAULT 'web_interface' COMMENT 'Upload method',
    ip_address VARCHAR(45) NULL COMMENT 'Uploader IP address',
    user_agent TEXT NULL COMMENT 'Browser user agent',

    -- Indexes for common queries
    INDEX idx_upload_date (upload_timestamp),
    INDEX idx_snapshot_date (snapshot_date),
    INDEX idx_filename (filename),
    INDEX idx_file_hash (file_hash),
    INDEX idx_processing_status (processing_status),

    -- Unique constraint to prevent duplicate uploads
    UNIQUE KEY unique_file_hash (file_hash)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Immutable source of truth for all uploaded CSV files';
