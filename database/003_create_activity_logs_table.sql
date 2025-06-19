-- Create activity logs table to track system activities
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    action_type VARCHAR(50) NOT NULL COMMENT 'e.g., login, sale, product_update, etc.',
    entity_type VARCHAR(50) DEFAULT NULL COMMENT 'e.g., product, sale, user, etc.',
    entity_id INT DEFAULT NULL,
    old_value TEXT DEFAULT NULL COMMENT 'JSON string of old values',
    new_value TEXT DEFAULT NULL COMMENT 'JSON string of new values',
    details TEXT DEFAULT NULL COMMENT 'Additional details about the action',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_created_at (created_at),
    KEY idx_action_type (action_type),
    KEY idx_entity (entity_type, entity_id),
    CONSTRAINT fk_activity_logs_user 
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for frequently queried fields
CREATE INDEX idx_activity_logs_composite ON activity_logs (user_id, action_type, created_at);
