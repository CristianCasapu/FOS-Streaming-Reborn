-- Fix system_command_logs table to match model expectations
-- The table has different column names than what the model expects

-- Add missing columns
ALTER TABLE `system_command_logs`
    ADD COLUMN IF NOT EXISTS `admin_id` INT UNSIGNED NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `description` VARCHAR(255) NULL AFTER `command`,
    ADD COLUMN IF NOT EXISTS `execution_time` FLOAT NULL AFTER `exit_code`,
    ADD COLUMN IF NOT EXISTS `success` TINYINT(1) NOT NULL DEFAULT 0 AFTER `execution_time`,
    ADD COLUMN IF NOT EXISTS `ip_address` VARCHAR(45) NULL AFTER `success`,
    ADD COLUMN IF NOT EXISTS `user_agent` TEXT NULL AFTER `ip_address`,
    ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER `user_agent`;

-- Fix started_at column to allow NULL and have default value
ALTER TABLE `system_command_logs` MODIFY COLUMN `started_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

-- Copy data from old columns to new columns if they exist
UPDATE `system_command_logs` SET `admin_id` = `executed_by` WHERE `admin_id` IS NULL AND `executed_by` IS NOT NULL;
UPDATE `system_command_logs` SET `execution_time` = `execution_time_ms` / 1000 WHERE `execution_time` IS NULL AND `execution_time_ms` IS NOT NULL;
UPDATE `system_command_logs` SET `created_at` = `started_at` WHERE `created_at` IS NULL AND `started_at` IS NOT NULL;
UPDATE `system_command_logs` SET `success` = CASE WHEN `exit_code` = 0 THEN 1 ELSE 0 END WHERE `exit_code` IS NOT NULL;

-- Add index on admin_id
ALTER TABLE `system_command_logs` ADD INDEX IF NOT EXISTS `idx_admin_id` (`admin_id`);
