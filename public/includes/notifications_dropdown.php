<?php
// Only show notifications for logged-in users
if (!isset($_SESSION['user_id'])) {
    return;
}

// Initialize notification helper
require_once __DIR__ . '/NotificationHelper.php';
$db = get_db_connection();
$notificationHelper = new NotificationHelper($db);

// Get unread notifications
$notifications = $notificationHelper->getUnreadNotifications($_SESSION['user_id'], 5);
$unreadCount = $notificationHelper->getUnreadCount($_SESSION['user_id']);
?>

<!-- Notifications Dropdown -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" 
       data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="badge badge-danger badge-counter"><?php echo $unreadCount; ?></span>
        <?php endif; ?>
    </a>
    <!-- Dropdown - Notifications -->
    <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in" 
         aria-labelledby="notificationsDropdown">
        <h6 class="dropdown-header bg-primary text-white">
            Notifications Center
        </h6>
        
        <?php if (empty($notifications)): ?>
            <a class="dropdown-item text-center small text-gray-500" href="#">
                No new notifications
            </a>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <a class="dropdown-item d-flex align-items-center notification-item" 
                   href="<?php echo htmlspecialchars($notification['related_url'] ?? '#'); ?>" 
                   data-id="<?php echo $notification['id']; ?>"
                   data-url="<?php echo htmlspecialchars($notification['related_url'] ?? '#'); ?>">
                    <div class="mr-3">
                        <div class="icon-circle bg-<?php echo $notification['type'] ?? 'primary'; ?>">
                            <i class="fas fa-<?php echo $this->getNotificationIcon($notification['type'] ?? 'info'); ?> text-white"></i>
                        </div>
                    </div>
                    <div>
                        <div class="small text-gray-500">
                            <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                        </div>
                        <span class="font-weight-bold"><?php echo htmlspecialchars($notification['title']); ?></span>
                        <div class="small text-muted">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            <a class="dropdown-item text-center small text-gray-500" href="management/notifications.php">
                <i class="fas fa-list mr-1"></i> View All Notifications
            </a>
        <?php endif; ?>
    </div>
</li>

<!-- Notification styles -->
<style>
.notification-item {
    white-space: normal !important;
    padding: 0.5rem 1rem !important;
    border-left: 3px solid transparent;
    transition: all 0.2s;
}

.notification-item:hover {
    background-color: #f8f9fa;
    border-left-color: #4e73df;
    text-decoration: none;
}

.icon-circle {
    border-radius: 50%;
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.badge-counter {
    position: absolute;
    transform: scale(0.7);
    transform-origin: top right;
    right: 0.35rem;
    top: 0.25rem;
}

.dropdown-list {
    width: 20rem !important;
    padding: 0;
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.dropdown-header {
    border-radius: 0.35rem 0.35rem 0 0;
    padding: 1rem 1.35rem;
    margin-bottom: 0;
    font-weight: 600;
}

.dropdown-item {
    padding: 0.5rem 1.35rem;
    border-bottom: 1px solid #e3e6f0;
}

.dropdown-item:last-child {
    border-bottom: none;
}

/* Notification type colors */
.bg-success { background-color: #1cc88a !important; }
.bg-info { background-color: #36b9cc !important; }
.bg-warning { background-color: #f6c23e !important; }
.bg-danger { background-color: #e74a3b !important; }
</style>

<!-- Notification JavaScript -->
<script>
$(document).ready(function() {
    // Handle notification click
    $('.notification-item').on('click', function(e) {
        e.preventDefault();
        
        var $notificationItem = $(this);
        var notificationId = $notificationItem.data('id');
        var targetUrl = $notificationItem.data('url');
        
        // Mark as read via AJAX if we have a notification ID
        if (notificationId) {
            $.ajax({
                url: 'mark_notification_read.php',
                type: 'POST',
                data: { notification_id: notificationId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update the notification count
                        var $badge = $('.badge-counter');
                        var count = parseInt($badge.text());
                        if (count > 1) {
                            $badge.text(count - 1);
                        } else {
                            $badge.remove();
                        }
                        // Remove the notification from the list
                        $notificationItem.fadeOut(200, function() {
                            $(this).remove();
                            // If no more notifications, show message
                            if ($('.notification-item').length === 0) {
                                $('.dropdown-list').html(`
                                    <h6 class="dropdown-header bg-primary text-white">
                                        Notifications Center
                                    </h6>
                                    <a class="dropdown-item text-center small text-gray-500" href="#">
                                        No new notifications
                                    </a>
                                    <a class="dropdown-item text-center small text-gray-500" href="management/notifications.php">
                                        <i class="fas fa-list mr-1"></i> View All Notifications
                                    </a>
                                `);
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error marking notification as read:', error);
                },
                complete: function() {
                    // Navigate to the target URL after marking as read
                    if (targetUrl) {
                        window.location.href = targetUrl;
                    }
                }
            });
        } else if (targetUrl) {
            // If no notification ID but we have a URL, just navigate
            window.location.href = targetUrl;
        }
    });
});
</script>
