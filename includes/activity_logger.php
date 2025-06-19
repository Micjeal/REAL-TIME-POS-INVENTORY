<?php
/**
 * Activity Logger for MTECH UGANDA POS System
 * 
 * This file contains functions to log user activities and system events
 * for tracking and auditing purposes.
 */

/**
 * Log an activity to the database
 * 
 * @param string $action_type Type of action (e.g., 'login', 'sale', 'product_update')
 * @param string|null $entity_type Type of entity being acted upon (e.g., 'product', 'sale', 'user')
 * @param int|null $entity_id ID of the entity being acted upon
 * @param array|null $old_values Associative array of old values (before change)
 * @param array|null $new_values Associative array of new values (after change)
 * @param string|null $details Additional details about the action
 * @return bool True on success, false on failure
 */
function log_activity($action_type, $entity_type = null, $entity_id = null, $old_values = null, $new_values = null, $details = null) {
    global $conn; // Changed from $db to $conn to match config.php
    
    try {
        // Get user information from session if available
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'system';
        
        // Get client information
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Prepare old and new values as JSON
        $old_json = $old_values ? json_encode($old_values, JSON_PRETTY_PRINT) : null;
        $new_json = $new_values ? json_encode($new_values, JSON_PRETTY_PRINT) : null;
        
        // Insert log into database
        $sql = "
            INSERT INTO activity_logs 
            (user_id, username, ip_address, user_agent, action_type, entity_type, entity_id, old_value, new_value, details, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bind_param(
            'isssssisss',
            $user_id,
            $username,
            $ip_address,
            $user_agent,
            $action_type,
            $entity_type,
            $entity_id,
            $old_json,
            $new_json,
            $details
        );
        
        $result = $stmt->execute();
        
        return $result;
    } catch (Exception $e) {
        // Log error to PHP error log if logging fails
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent activities
 * 
 * @param int $limit Number of activities to retrieve (default: 50)
 * @param int $offset Offset for pagination (default: 0)
 * @param int|null $user_id Filter by user ID (optional)
 * @param string|null $action_type Filter by action type (optional)
 * @param string|null $entity_type Filter by entity type (optional)
 * @param int|null $entity_id Filter by entity ID (optional)
 * @param string $order_by Column to order by (default: 'created_at')
 * @param string $order_direction Order direction ('ASC' or 'DESC', default: 'DESC')
 * @return array Array of activity logs
 */
function get_recent_activities($limit = 50, $offset = 0, $user_id = null, $action_type = null, $entity_type = null, $entity_id = null, $order_by = 'created_at', $order_direction = 'DESC') {
    global $db;
    
    // Validate order direction
    $order_direction = strtoupper($order_direction) === 'ASC' ? 'ASC' : 'DESC';
    
    // Build query
    $query = "
        SELECT al.*, u.name as user_fullname
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add filters
    if ($user_id !== null) {
        $query .= " AND al.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($action_type !== null) {
        $query .= " AND al.action_type = ?";
        $params[] = $action_type;
    }
    
    if ($entity_type !== null) {
        $query .= " AND al.entity_type = ?";
        $params[] = $entity_type;
    }
    
    if ($entity_id !== null) {
        $query .= " AND al.entity_id = ?";
        $params[] = $entity_id;
    }
    
    // Add ordering and limit/offset
    $query .= " ORDER BY $order_by $order_direction";
    $query .= " LIMIT ? OFFSET ?";
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a human-readable description of an activity
 * 
 * @param array $activity Activity data from the database
 * @return string Human-readable description of the activity
 */
function get_activity_description($activity) {
    $username = htmlspecialchars($activity['user_fullname'] ?? $activity['username'] ?? 'System');
    $action = strtolower($activity['action_type'] ?? 'performed an action');
    $entity = $activity['entity_type'] ?? null;
    $entity_id = $activity['entity_id'] ?? null;
    $details = $activity['details'] ?? null;
    $timestamp = date('Y-m-d H:i:s', strtotime($activity['created_at']));
    
    // Default description
    $description = "$username $action";
    
    // Add entity information if available
    if ($entity && $entity_id) {
        $entity_name = ucfirst(str_replace('_', ' ', $entity));
        $description .= " on $entity_name #$entity_id";
    }
    
    // Add details if available
    if ($details) {
        $description .= ": $details";
    }
    
    // Add timestamp
    $description .= " at $timestamp";
    
    return $description;
}

// Include this file in config.php or at the top of files that need logging
// require_once __DIR__ . '/includes/activity_logger.php';
?>
