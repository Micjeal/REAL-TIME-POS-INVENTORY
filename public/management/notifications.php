<?php
// Start the session
session_start();

// Include database configuration
require_once '../config.php';
require_once '../includes/NotificationHelper.php';

// Check if user is logged in and is admin/manager
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'manager'])) {
    header('Location: ../login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = strtolower($_SESSION['role'] ?? 'cashier');

// Initialize notification helper
$db = get_db_connection();
$notificationHelper = new NotificationHelper($db);

// Mark all notifications as read if requested
if (isset($_GET['mark_all_read'])) {
    $notificationHelper->markAllAsRead($user_id);
    header('Location: notifications.php');
    exit();
}

// Get all notifications
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalNotifications = $notificationHelper->getNotificationCount($user_id);
$totalPages = ceil($totalNotifications / $perPage);

$notifications = $notificationHelper->getAllNotifications($user_id, $offset, $perPage);

// Set page title
$page_title = 'Notifications';

// Include header
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Notifications</h1>
        <div>
            <a href="?mark_all_read=1" class="btn btn-primary btn-sm">
                <i class="fas fa-check-double fa-sm text-white-50"></i> Mark All as Read
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">All Notifications</h6>
            <span class="badge badge-primary badge-pill"><?php echo $totalNotifications; ?> total</span>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="far fa-bell-slash fa-3x text-gray-300 mb-3"></i>
                    <p class="text-muted">No notifications found.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <a href="<?php echo htmlspecialchars($notification['related_url'] ?? '#'); ?>" 
                           class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>"
                           data-id="<?php echo $notification['id']; ?>"
                           data-url="<?php echo htmlspecialchars($notification['related_url'] ?? '#'); ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="badge badge-primary mr-2">New</span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                </small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <?php if ($notification['user_id']): ?>
                                <small>From: <?php echo htmlspecialchars($notification['full_name'] ?? $notification['username'] ?? 'System'); ?></small>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                        <span class="sr-only">Previous</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                        <span class="sr-only">Next</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Mark notification as read when clicked
    $('.list-group-item').on('click', function(e) {
        e.preventDefault();
        
        const notificationId = $(this).data('id');
        const targetUrl = $(this).data('url');
        
        // Mark as read via AJAX
        if (notificationId) {
            $.ajax({
                url: '../ajax/mark_notification_read.php',
                type: 'POST',
                data: { id: notificationId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update the UI
                        const $item = $(`[data-id="${notificationId}"]`);
                        $item.removeClass('bg-light');
                        $item.find('.badge').remove();
                        
                        // Update notification count in the header if it exists
                        const $badge = $('#notificationsDropdown .badge-counter');
                        if ($badge.length) {
                            const count = parseInt($badge.text()) - 1;
                            if (count > 0) {
                                $badge.text(count);
                            } else {
                                $badge.remove();
                            }
                        }
                    }
                },
                error: function() {
                    console.error('Error marking notification as read');
                }
            });
        }
        
        // Navigate to the target URL
        window.location.href = targetUrl;
    });
});
</script>

<?php include '../includes/footer.php'; ?>
