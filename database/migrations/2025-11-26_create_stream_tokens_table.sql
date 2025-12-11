-- Stream Tokens Table for Enterprise-Grade Streaming Security
-- Provides secure, time-limited token-based authentication for streaming access

CREATE TABLE IF NOT EXISTS `stream_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Cryptographically secure token (64-char hex)',
    `stream_id` INT UNSIGNED NOT NULL COMMENT 'Stream being accessed',
    `type` ENUM('staff', 'subscriber', 'api') NOT NULL DEFAULT 'subscriber' COMMENT 'Token type',
    `user_id` INT UNSIGNED NULL COMMENT 'Admin ID (staff) or Subscriber ID (subscriber)',
    `ip_address` VARCHAR(45) NULL COMMENT 'Bound IP address for security',
    `user_agent` TEXT NULL COMMENT 'Client user agent',
    `metadata` JSON NULL COMMENT 'Additional token metadata',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL COMMENT 'Token expiration time',
    `last_used_at` DATETIME NULL COMMENT 'Last usage timestamp',
    `use_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of times token used',
    `is_revoked` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Token revocation status',

    INDEX `idx_token` (`token`),
    INDEX `idx_stream_id` (`stream_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_is_revoked` (`is_revoked`),
    INDEX `idx_valid_tokens` (`is_revoked`, `expires_at`),

    FOREIGN KEY (`stream_id`) REFERENCES `streams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment to table
ALTER TABLE `stream_tokens` COMMENT = 'Secure streaming access tokens for staff and subscribers';
