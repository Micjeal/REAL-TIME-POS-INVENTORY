-- Create user_password_history table to track password changes
CREATE TABLE IF NOT EXISTS `user_password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `changed_at` datetime NOT NULL,
  `changed_by` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_changed_at` (`changed_at`),
  KEY `idx_user_changed_at` (`user_id`, `changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add foreign key constraint after table creation to avoid issues with table order
ALTER TABLE `user_password_history`
ADD CONSTRAINT `fk_user_password_history_user` 
FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
