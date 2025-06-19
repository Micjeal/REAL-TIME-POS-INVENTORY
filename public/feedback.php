<?php
// Start the session
session_start();

// Include database configuration
require_once 'config.php';
require_once 'includes/NotificationHelper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_fullname = $_SESSION['full_name'] ?? $username;
$user_role = strtolower($_SESSION['role'] ?? 'cashier');
$is_admin = in_array($user_role, ['admin', 'manager']);
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $rating = !empty($_POST['rating']) ? (int)$_POST['rating'] : null;
    
    // Validate input
    if (empty($subject) || empty($message)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            $db = get_db_connection();
            $stmt = $db->prepare("INSERT INTO feedback (user_id, subject, message, rating) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $subject, $message, $rating]);
            
            // Send notification to all admins/managers
            $notificationHelper = new NotificationHelper($db);
            $ratingText = $rating ? " (Rating: " . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . ")" : "";
            $notificationTitle = "New Feedback: " . htmlspecialchars($subject);
            $notificationMessage = "New feedback received from " . htmlspecialchars($user_fullname) . $ratingText . ". Click to view details.";
            
            // Send to all admins/managers
            $notificationHelper->addNotification(
                $notificationTitle,
                $notificationMessage,
                'info',
                null, // null means send to all admins/managers
                'management/feedback.php' // Link to view all feedback
            );
            
            $success_message = 'Thank you for your feedback! We will review it shortly.';
            // Clear form
            $_POST = [];
        } catch (PDOException $e) {
            $error_message = 'Error submitting feedback. Please try again later.';
            error_log('Feedback submission error: ' . $e->getMessage());
        }
    }
}

// Get feedback for admin/manager view
$feedback_list = [];
if ($is_admin) {
    try {
        $db = get_db_connection();
        $query = "SELECT f.*, u.username, u.full_name 
                 FROM feedback f 
                 JOIN users u ON f.user_id = u.id 
                 ORDER BY f.status = 'new' DESC, f.created_at DESC";
        $feedback_list = $db->query($query)->fetchAll();
    } catch (PDOException $e) {
        $error_message = 'Error loading feedback. Please try again later.';
        error_log('Feedback load error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --dark-bg: #1e2130;
            --med-bg: #2a2e43;
            --light-bg: #3a3f55;
            --text-light: #f0f0f0;
            --text-muted: #a0a0a0;
            --accent-blue: #3584e4;
            --accent-green: #2fac66;
            --accent-red: #e35d6a;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background-color: var(--med-bg);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--light-bg);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 15px 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-control, .form-control:focus {
            background-color: var(--dark-bg);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-light);
        }
        
        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 0.2rem rgba(53, 132, 228, 0.25);
        }
        
        .btn-primary {
            background-color: var(--accent-blue);
            border: none;
            padding: 8px 20px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #2a6fc9;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .status-new {
            background-color: rgba(53, 132, 228, 0.2);
            color: var(--accent-blue);
        }
        
        .status-in_progress {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-resolved {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .feedback-item {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 15px 0;
        }
        
        .feedback-item:last-child {
            border-bottom: none;
        }
        
        .feedback-meta {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        
        .feedback-actions {
            margin-top: 10px;
        }
        
        .btn-sm {
            padding: 2px 8px;
            font-size: 12px;
            margin-right: 5px;
        }
        
        .alert {
            border: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        /* Star Rating Styles */
        .rating-stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            margin: 10px 0;
        }
        
        .rating-stars input[type="radio"] {
            display: none;
        }
        
        .rating-stars label {
            color: #ddd;
            font-size: 28px;
            padding: 0 3px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input[type="radio"]:checked ~ label {
            color: #ffc107;
        }
        
        .rating-stars input[type="radio"]:checked ~ label {
            color: #ffc107;
        }
        
        .rating-value {
            margin-left: 10px;
            font-weight: bold;
            color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fas fa-comment-dots"></i> Feedback</h2>
                <hr>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!$is_admin): ?>
                <!-- Feedback Form for Regular Users -->
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-paper-plane"></i> Submit Feedback</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="feedback.php">
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required><?php 
                                    echo htmlspecialchars($_POST['message'] ?? ''); 
                                ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Rating (Optional)</label>
                                <div class="rating-stars mb-3">
                                    <input type="radio" id="star5" name="rating" value="5" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 5) ? 'checked' : ''; ?> />
                                    <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" id="star4" name="rating" value="4" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 4) ? 'checked' : ''; ?> />
                                    <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" id="star3" name="rating" value="3" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 3) ? 'checked' : ''; ?> />
                                    <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" id="star2" name="rating" value="2" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 2) ? 'checked' : ''; ?> />
                                    <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                                    
                                    <input type="radio" id="star1" name="rating" value="1" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 1) ? 'checked' : ''; ?> />
                                    <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                                </div>
                            </div>
                            <button type="submit" name="submit_feedback" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Feedback
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Feedback List for Admin/Manager -->
                <?php if ($is_admin): ?>
                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-inbox"></i> Feedback Inbox</span>
                        <span class="badge badge-info"><?php echo count($feedback_list); ?> items</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($feedback_list)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No feedback has been submitted yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($feedback_list as $feedback): ?>
                                <div class="feedback-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex justify-content-between align-items-center w-100">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($feedback['subject']); ?></h5>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($feedback['rating'])): ?>
                                                <div class="rating-display mr-3" title="Rating: <?php echo $feedback['rating']; ?>/5">
                                                    <?php 
                                                    $fullStars = (int)$feedback['rating'];
                                                    $emptyStars = 5 - $fullStars;
                                                    echo str_repeat('★', $fullStars) . str_repeat('☆', $emptyStars);
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="status-badge status-<?php echo $feedback['status']; ?>">
                                                <?php echo str_replace('_', ' ', $feedback['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    </div>
                                    <div class="feedback-meta">
                                        Submitted by <?php echo htmlspecialchars($feedback['full_name'] ?? $feedback['username']); ?> 
                                        on <?php echo date('M j, Y g:i A', strtotime($feedback['created_at'])); ?>
                                    </div>
                                    <div class="feedback-message">
                                        <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                                    </div>
                                    <div class="feedback-actions">
                                        <?php if ($feedback['status'] !== 'resolved'): ?>
                                            <a href="#" class="btn btn-sm btn-success btn-update-status" 
                                               data-id="<?php echo $feedback['id']; ?>" data-status="resolved">
                                                <i class="fas fa-check"></i> Mark as Resolved
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($feedback['status'] !== 'in_progress'): ?>
                                            <a href="#" class="btn btn-sm btn-warning btn-update-status" 
                                               data-id="<?php echo $feedback['id']; ?>" data-status="in_progress">
                                                <i class="fas fa-spinner"></i> In Progress
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($feedback['status'] !== 'new'): ?>
                                            <a href="#" class="btn btn-sm btn-info btn-update-status" 
                                               data-id="<?php echo $feedback['id']; ?>" data-status="new">
                                                <i class="fas fa-undo"></i> Reopen
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Star rating hover effect
        $('.rating-stars label').hover(
            function() {
                $(this).prevAll().addBack().children('i').removeClass('far').addClass('fas');
                $(this).nextAll().children('i').removeClass('fas').addClass('far');
            },
            function() {
                $('.rating-stars input[type="radio"]:checked').each(function() {
                    $(this).prevAll().addBack().nextAll().children('i').removeClass('fas').addClass('far');
                    $(this).prevAll().addBack().children('i').removeClass('far').addClass('fas');
                });
            }
        );
        
        // Handle star click
        $('.rating-stars input[type="radio"]').on('change', function() {
            const rating = $(this).val();
            console.log('Selected rating:', rating);
        });
        
        // Handle status update
        $('.btn-update-status').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const feedbackId = button.data('id');
            const newStatus = button.data('status');
            
            // Show loading state
            const originalText = button.html();
            button.html('<i class="fas fa-spinner fa-spin"></i> Updating...').prop('disabled', true);
            
            // Send AJAX request to update status
            $.ajax({
                url: 'ajax/update_feedback_status.php',
                type: 'POST',
                data: {
                    id: feedbackId,
                    status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated status
                        location.reload();
                    } else {
                        alert('Error updating status: ' + (response.message || 'Unknown error'));
                        button.html(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Error updating status. Please try again.');
                    button.html(originalText).prop('disabled', false);
                }
            });
        });
    });
    </script>
</body>
</html>
