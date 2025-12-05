-- Add contact information fields to subscriber_snapshots table
-- These fields are needed for subscriber exports and contact lists
-- Date: 2025-12-05

ALTER TABLE subscriber_snapshots
ADD COLUMN IF NOT EXISTS address VARCHAR(255) COMMENT 'Street address' AFTER on_vacation,
ADD COLUMN IF NOT EXISTS city_state_postal VARCHAR(150) COMMENT 'City, State, Postal code' AFTER address,
ADD COLUMN IF NOT EXISTS phone VARCHAR(50) COMMENT 'Phone number' AFTER city_state_postal,
ADD COLUMN IF NOT EXISTS email VARCHAR(255) COMMENT 'Email address' AFTER phone,
ADD COLUMN IF NOT EXISTS abc VARCHAR(50) COMMENT 'ABC column from Newzware' AFTER email,
ADD COLUMN IF NOT EXISTS issue_code VARCHAR(50) COMMENT 'ISS column from Newzware' AFTER abc,
ADD COLUMN IF NOT EXISTS last_payment_amount DECIMAL(10,2) COMMENT 'Last payment amount' AFTER issue_code,
ADD COLUMN IF NOT EXISTS login_id VARCHAR(100) COMMENT 'Digital login ID' AFTER last_payment_amount,
ADD COLUMN IF NOT EXISTS last_login DATETIME COMMENT 'Last digital login date/time' AFTER login_id;

-- Add indexes for common searches
CREATE INDEX IF NOT EXISTS idx_email ON subscriber_snapshots(email);
CREATE INDEX IF NOT EXISTS idx_phone ON subscriber_snapshots(phone);

-- Verify columns added
DESCRIBE subscriber_snapshots;
