<?php
require_once __DIR__ . '/config.php';

// Check if user is logged in and has admin/manager role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'])) {
    header('Location: /MTECH%20UGANDA/public/login.php');
    exit();
}

$page_title = 'Feedback Management';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo htmlspecialchars($page_title); ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Feedback</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-comments me-1"></i>
            User Feedback
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="feedbackTable" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $pdo->query("
                                SELECT f.*, u.username, u.name as user_name 
                                FROM feedback f 
                                JOIN users u ON f.user_id = u.id 
                                ORDER BY f.created_at DESC
                            ");
                            
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['user_name'] . ' (' . $row['username'] . ')') . '</td>';
                                echo '<td>' . htmlspecialchars($row['subject']) . '</td>';
                                echo '<td class="text-warning">' . str_repeat('★', $row['rating']) . str_repeat('☆', 5 - $row['rating']) . '</td>';
                                
                                // Status badge
                                $statusClass = '';
                                switch ($row['status']) {
                                    case 'new':
                                        $statusClass = 'bg-primary';
                                        break;
                                    case 'in_progress':
                                        $statusClass = 'bg-warning text-dark';
                                        break;
                                    case 'resolved':
                                        $statusClass = 'bg-success';
                                        break;
                                }
                                echo '<td><span class="badge ' . $statusClass . '">' . ucfirst(str_replace('_', ' ', $row['status'])) . '</span></td>';
                                
                                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                                
                                // Actions
                                echo '<td>';
                                echo '<button class="btn btn-sm btn-info view-message" data-bs-toggle="modal" data-bs-target="#viewMessageModal" 
                                      data-subject="' . htmlspecialchars($row['subject']) . '" 
                                      data-message="' . htmlspecialchars($row['message']) . '"
                                      data-rating="' . $row['rating'] . '">
                                    <i class="fas fa-eye"></i> View
                                </button> ';
                                
                                if (($_SESSION['user_role'] ?? '') === 'admin') {
                                    echo '<div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item update-status" href="#" data-id="' . $row['id'] . '" data-status="new">Mark as New</a></li>
                                            <li><a class="dropdown-item update-status" href="#" data-id="' . $row['id'] . '" data-status="in_progress">Mark as In Progress</a></li>
                                            <li><a class="dropdown-item update-status" href="#" data-id="' . $row['id'] . '" data-status="resolved">Mark as Resolved</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger delete-feedback" href="#" data-id="' . $row['id'] . '"><i class="fas fa-trash-alt me-1"></i> Delete</a></li>
                                        </ul>
                                    </div>';
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                        } catch (PDOException $e) {
                            echo '<tr><td colspan="7" class="text-center text-danger">Error loading feedback: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMessageModalLabel">Feedback Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 id="feedbackSubject"></h4>
                <div class="mb-3">
                    <span id="feedbackRating" class="text-warning"></span>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p id="feedbackMessage" class="mb-0"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this feedback? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Page level plugins -->
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#feedbackTable').DataTable({
        responsive: true,
        order: [[0, 'desc']], // Sort by ID descending (newest first)
        columnDefs: [
            { orderable: false, targets: [6] } // Disable sorting on actions column
        ]
    });

    // View message in modal
    $('#viewMessageModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var subject = button.data('subject');
        var message = button.data('message');
        var rating = button.data('rating');
        
        var modal = $(this);
        modal.find('.modal-title').text('Feedback: ' + subject);
        modal.find('#feedbackSubject').text(subject);
        modal.find('#feedbackMessage').text(message);
        
        // Create star rating display
        var stars = '';
        for (var i = 1; i <= 5; i++) {
            if (i <= rating) {
                stars += '★';
            } else {
                stars += '☆';
            }
        }
        modal.find('#feedbackRating').html(stars);
    });

    // Update status
    $('body').on('click', '.update-status', function(e) {
        e.preventDefault();
        var feedbackId = $(this).data('id');
        var newStatus = $(this).data('status');
        
        $.ajax({
            url: 'ajax/update_feedback_status.php',
            type: 'POST',
            data: {
                id: feedbackId,
                status: newStatus,
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Reload the page to show updated status
                    location.reload();
                } else {
                    alert('Error updating status: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error communicating with server');
            }
        });
    });

    // Delete feedback
    var feedbackIdToDelete;
    
    $('body').on('click', '.delete-feedback', function(e) {
        e.preventDefault();
        feedbackIdToDelete = $(this).data('id');
        $('#deleteModal').modal('show');
    });
    
    $('#confirmDelete').on('click', function() {
        if (!feedbackIdToDelete) return;
        
        $.ajax({
            url: 'ajax/delete_feedback.php',
            type: 'POST',
            data: {
                id: feedbackIdToDelete,
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close modal and reload
                    $('#deleteModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error deleting feedback: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error communicating with server');
            }
        });
    });
});
</script>
