<?php
/**
 * Audit Logging Functions for MTECH UGANDA
 * 
 * This file contains functions to log various system activities for auditing purposes.
 */

/**
 * Log an activity in the audit log
 * 
 * @param PDO $db Database connection
 * @param int $user_id ID of the user performing the action
 * @param string $action_type Type of action (e.g., 'stock_add', 'stock_adjust')
 * @param string $description Human-readable description of the action
 * @param array $details Additional details as key-value pairs
 * @return bool True on success, false on failure
 */
function log_activity($db, $user_id, $action_type, $description = '', $details = []) {
    try {
        // Ensure audit_logs table exists
        $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            details TEXT,
            INDEX idx_user_id (user_id),
            INDEX idx_action_type (action_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Get client IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Prepare and execute the insert statement
        $stmt = $db->prepare("INSERT INTO audit_logs 
            (user_id, action_type, description, ip_address, user_agent, details)
            VALUES (:user_id, :action_type, :description, :ip_address, :user_agent, :details)");
            
        return $stmt->execute([
            'user_id' => $user_id,
            'action_type' => $action_type,
            'description' => $description,
            'ip_address' => $ip_address,
            'user_agent' => substr($user_agent, 0, 255),
            'details' => json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ]);
        
    } catch (Exception $e) {
        // Log to PHP error log if database logging fails
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs with optional filters
 * 
 * @param PDO $db Database connection
 * @param array $filters Optional filters (user_id, action_type, date_from, date_to, limit, offset)
 * @return array Array of log entries
 */
function get_audit_logs($db, $filters = []) {
    $where = [];
    $params = [];
    
    // Build WHERE clause based on filters
    if (!empty($filters['user_id'])) {
        $where[] = 'l.user_id = :user_id';
        $params['user_id'] = $filters['user_id'];
    }
    
    if (!empty($filters['action_type'])) {
        $where[] = 'l.action_type = :action_type';
        $params['action_type'] = $filters['action_type'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = 'l.created_at >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = 'l.created_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Build the query
    $sql = "SELECT l.*, u.username, u.full_name 
            FROM audit_logs l 
            LEFT JOIN users u ON l.user_id = u.id ";
            
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    
    $sql .= ' ORDER BY l.created_at DESC';
    
    // Add limit and offset if provided
    if (isset($filters['limit'])) {
        $sql .= ' LIMIT ' . (int)$filters['limit'];
        if (isset($filters['offset'])) {
            $sql .= ' OFFSET ' . (int)$filters['offset'];
        }
    }
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON details
        foreach ($logs as &$log) {
            if (!empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }
        }
        
        return $logs;
    } catch (Exception $e) {
        error_log("Failed to fetch audit logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of audit logs with optional filters
 * 
 * @param PDO $db Database connection
 * @param array $filters Optional filters (same as get_audit_logs)
 * @return int Number of matching log entries
 */
function count_audit_logs($db, $filters = []) {
    $where = [];
    $params = [];
    
    // Build WHERE clause based on filters (same as get_audit_logs)
    if (!empty($filters['user_id'])) {
        $where[] = 'user_id = :user_id';
        $params['user_id'] = $filters['user_id'];
    }
    
    if (!empty($filters['action_type'])) {
        $where[] = 'action_type = :action_type';
        $params['action_type'] = $filters['action_type'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = 'created_at >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = 'created_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Build the query
    $sql = 'SELECT COUNT(*) as count FROM audit_logs';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Failed to count audit logs: " . $e->getMessage());
        return 0;
    }
}
