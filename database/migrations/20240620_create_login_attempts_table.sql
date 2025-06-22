-- Create login_attempts table to track all login attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL COMMENT 'Null if user not found',
    `username` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for faster lookups
CREATE INDEX `idx_username` ON `login_attempts` (`username`);
CREATE INDEX `idx_ip_address` ON `login_attempts` (`ip_address`);
CREATE INDEX `idx_created_at` ON `login_attempts` (`created_at`);
