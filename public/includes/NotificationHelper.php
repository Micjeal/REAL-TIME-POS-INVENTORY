<?php
class NotificationHelper {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Add a new notification
     * 
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, danger)
     * @param int|null $userId User ID to send notification to (null for all admins/managers)
     * @param string|null $relatedUrl URL related to the notification
     * @return bool True on success, false on failure
     */
    public function addNotification($title, $message, $type = 'info', $userId = null, $relatedUrl = null) {
        try {
            if ($userId === null) {
                // If no user specified, send to all admins and managers
                $stmt = $this->db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, related_url)
                    SELECT id, ?, ?, ?, ? FROM users 
                    WHERE role IN ('admin', 'manager') AND active = 1
                ");
                return $stmt->execute([$title, $message, $type, $relatedUrl]);
            } else {
                // Send to specific user
                $stmt = $this->db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, related_url)
                    VALUES (?, ?, ?, ?, ?)
                
                ");
                return $stmt->execute([$userId, $title, $message, $type, $relatedUrl]);
            }
        } catch (PDOException $e) {
            error_log('Notification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's unread notifications
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications to return
     * @return array Array of notifications
     */
    public function getUnreadNotifications($userId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT n.*, u.username, u.full_name 
                FROM notifications n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE (n.user_id = ? OR n.user_id IS NULL) 
                AND n.is_read = 0
                ORDER BY n.created_at DESC
                LIMIT ?
            
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notifications as read
     * 
     * @param array|int $notificationIds Single notification ID or array of IDs
     * @param int $userId User ID (for security validation)
     * @return bool True on success, false on failure
     */
    public function markAsRead($notificationIds, $userId) {
        if (!is_array($notificationIds)) {
            $notificationIds = [$notificationIds];
        }
        
        if (empty($notificationIds)) {
            return false;
        }
        
        try {
            $placeholders = rtrim(str_repeat('?,', count($notificationIds)), ',');
            $params = array_merge($notificationIds, [$userId]);
            
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id IN ($placeholders) 
                AND (user_id = ? OR user_id IS NULL)
            
            ");
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('Error marking notifications as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notification count for a user
     * 
     * @param int $userId User ID
     * @return int Number of unread notifications
     */
    public function getUnreadCount($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL) 
                AND is_read = 0
            
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log('Error getting notification count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get icon for notification type
     * 
     * @param string $type Notification type
     * @return string Font Awesome icon name
     */
    public function getNotificationIcon($type) {
        switch ($type) {
            case 'success':
                return 'check-circle';
            case 'warning':
                return 'exclamation-triangle';
            case 'danger':
                return 'exclamation-circle';
            case 'info':
            default:
                return 'info-circle';
        }
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return bool True on success, false on failure
     */
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE (user_id = ? OR user_id IS NULL)
                AND is_read = 0
            
            ");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log('Error marking all notifications as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get total notification count for a user
     * 
     * @param int $userId User ID
     * @return int Total number of notifications
     */
    public function getNotificationCount($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE (user_id = ? OR user_id IS NULL)
            
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log('Error getting notification count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get paginated notifications for a user
     * 
     * @param int $userId User ID
     * @param int $offset Offset for pagination
     * @param int $limit Number of notifications to return
     * @return array Array of notifications
     */
    public function getAllNotifications($userId, $offset = 0, $limit = 20) {
        try {
            $stmt = $this->db->prepare("
                SELECT n.*, u.username, u.full_name 
                FROM notifications n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE (n.user_id = ? OR n.user_id IS NULL)
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?
            
            ");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error getting notifications: ' . $e->getMessage());
            return [];
        }
    }
}
