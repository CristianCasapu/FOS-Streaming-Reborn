-- Add subscription-level access controls for enterprise streaming security
-- This migration adds:
-- 1. access_token: Subscription-level access token (auto-generated on activation)
-- 2. allowed_isps: JSON array of allowed ISPs (empty/null = no restriction)
-- 3. allowed_ips: JSON array of allowed IPs (empty/null = no restriction)
-- 4. token_expires_at: When the access token should be regenerated (matches subscription expiry)

-- Add access_token column
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS access_token VARCHAR(128) NULL;

-- Add token_expires_at column
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS token_expires_at DATETIME NULL;

-- Add allowed_isps column (JSON array)
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS allowed_isps JSON NULL;

-- Add allowed_ips column (JSON array)
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS allowed_ips JSON NULL;

-- Add index for token lookups (ignore if exists)
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'subscriptions' AND index_name = 'idx_subscriptions_access_token') = 0,
    'CREATE INDEX idx_subscriptions_access_token ON subscriptions(access_token)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create active_connections table to track concurrent connections
CREATE TABLE IF NOT EXISTS active_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT UNSIGNED NOT NULL,
    subscriber_id INT UNSIGNED NOT NULL,
    stream_id INT UNSIGNED NULL,
    session_id VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    isp VARCHAR(255) NULL,
    device_fingerprint VARCHAR(64) NULL,
    connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_heartbeat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    bandwidth BIGINT UNSIGNED DEFAULT 0,
    INDEX idx_active_connections_subscription (subscription_id),
    INDEX idx_active_connections_subscriber (subscriber_id),
    INDEX idx_active_connections_session (session_id),
    INDEX idx_active_connections_active (is_active),
    INDEX idx_active_connections_heartbeat (last_heartbeat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
