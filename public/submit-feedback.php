<?php
require_once __DIR__ . '/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$page_title = 'Submit Feedback';
$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $rating = (int)($_POST['rating'] ?? 0);
        
        // Validate input
        if (empty($subject) || empty($message)) {
            throw new Exception('Subject and message are required.');
        }
        
        if ($rating < 1 || $rating > 5) {
            throw new Exception('Please provide a rating between 1 and 5.');
        }
        
        // Insert feedback into database
        $stmt = $pdo->prepare("
            INSERT INTO feedback (user_id, subject, message, rating, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'new', NOW(), NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $subject,
            $message,
            $rating
        ]);
        
        $success_message = 'Thank you for your feedback! We appreciate your input.';
        
        // Clear form
        $_POST = [];
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo htmlspecialchars($page_title); ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="welcome.php">Home</a></li>
        <li class="breadcrumb-item active">Submit Feedback</li>
    </ol>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-comment-dots me-1"></i>
                    Share Your Feedback
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="submit-feedback.php">
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Your Feedback</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php 
                                echo htmlspecialchars($_POST['message'] ?? ''); 
                            ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Rating</label>
                            <div class="rating-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" id="star<?php echo $i; ?>" name="rating" 
                                           value="<?php echo $i; ?>" 
                                           <?php echo (isset($_POST['rating']) && (int)$_POST['rating'] === $i) ? 'checked' : ''; ?>>
                                    <label for="star<?php echo $i; ?>">
                                        <i class="far fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="welcome.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> Submit Feedback
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}

.rating-input input[type="radio"] {
    display: none;
}

.rating-input label {
    cursor: pointer;
    font-size: 2rem;
    color: #ddd;
    position: relative;
}

.rating-input label .fas {
    display: none;
}

.rating-input input[type="radio"]:checked ~ label .far,
.rating-input input[type="radio"]:checked ~ label .fas,
.rating-input label:hover .fas,
.rating-input label:hover ~ label .fas {
    display: inline-block;
}

.rating-input label:hover .far,
.rating-input label:hover ~ label .far {
    display: none;
}

.rating-input input[type="radio"]:checked ~ label .far,
.rating-input input[type="radio"]:not(:checked) ~ label .fas {
    display: none;
}

.rating-input input[type="radio"]:checked ~ label .fas,
.rating-input input[type="radio"]:not(:checked) ~ label .far {
    display: inline-block;
}

.rating-input label:hover,
.rating-input input[type="radio"]:checked ~ label {
    color: #ffc107;
}
</style>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';
?>
