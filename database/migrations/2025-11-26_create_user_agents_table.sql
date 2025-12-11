-- Create user_agents table for storing predefined user agents
-- Used by FFmpeg/FFprobe when connecting to stream sources

CREATE TABLE IF NOT EXISTS `user_agents` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_agent` VARCHAR(512) NOT NULL,
    `name` VARCHAR(100) DEFAULT NULL COMMENT 'Friendly name for the user agent',
    `version` VARCHAR(20) DEFAULT NULL COMMENT 'Version number if applicable',
    `os` VARCHAR(50) DEFAULT NULL COMMENT 'Operating system (iOS, Android, Windows, etc.)',
    `hardware_type` VARCHAR(50) DEFAULT NULL COMMENT 'Hardware type (phone, mobile, tv, desktop, None)',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this is the default user agent',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether this user agent is enabled for use',
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_user_agent_unique` (`user_agent`(255)),
    KEY `idx_enabled` (`enabled`),
    KEY `idx_is_default` (`is_default`),
    KEY `idx_os` (`os`),
    KEY `idx_hardware_type` (`hardware_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add user_agent_id column to streams table for per-stream user agent selection
ALTER TABLE `streams`
ADD COLUMN IF NOT EXISTS `user_agent_id` INT UNSIGNED DEFAULT NULL AFTER `trans_id`,
ADD CONSTRAINT `fk_streams_user_agent` FOREIGN KEY (`user_agent_id`) REFERENCES `user_agents`(`id`) ON DELETE SET NULL;

-- Add default_user_agent_id to settings table
ALTER TABLE `settings`
ADD COLUMN IF NOT EXISTS `default_user_agent_id` INT UNSIGNED DEFAULT NULL,
ADD CONSTRAINT `fk_settings_default_user_agent` FOREIGN KEY (`default_user_agent_id`) REFERENCES `user_agents`(`id`) ON DELETE SET NULL;
