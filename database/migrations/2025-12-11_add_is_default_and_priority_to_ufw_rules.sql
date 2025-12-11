-- Add is_default and priority columns to ufw_rules table
-- Migration: 2025-12-11_add_is_default_and_priority_to_ufw_rules.sql

ALTER TABLE `ufw_rules`
ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
ADD COLUMN `priority` INT UNSIGNED NOT NULL DEFAULT 100 AFTER `is_default`,
ADD INDEX `idx_is_default` (`is_default`),
ADD INDEX `idx_priority` (`priority`);

-- Insert default rules if they don't exist
INSERT IGNORE INTO `ufw_rules` (`action`, `from_ip`, `to_port`, `protocol`, `comment`, `is_active`, `is_default`, `priority`, `created_at`, `updated_at`)
VALUES
    ('allow', NULL, '22', 'tcp', 'SSH Access', 1, 1, 10, NOW(), NOW()),
    ('allow', NULL, '80', 'tcp', 'HTTP Web Traffic', 1, 1, 20, NOW(), NOW()),
    ('allow', NULL, '443', 'tcp', 'HTTPS Web Traffic', 1, 1, 30, NOW(), NOW()),
    ('allow', NULL, '7777', 'tcp', 'FOS Admin Panel', 1, 1, 40, NOW(), NOW()),
    ('allow', NULL, '8000', 'tcp', 'Streaming Port', 1, 1, 50, NOW(), NOW()),
    ('allow', NULL, '1935', 'tcp', 'RTMP Port', 1, 1, 60, NOW(), NOW());
