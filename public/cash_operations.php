<?php
// Start the session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';

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
$user_role = $_SESSION['role'] ?? 'cashier';

// Process cash operations if form submitted via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $db = get_db_connection();
        $action = $_POST['action'];
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        // Validate inputs
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than zero');
        }
        
        if (empty($description)) {
            throw new Exception('Description is required');
        }
        
        // Determine operation type
        $operation_type = ($action === 'add') ? 'in' : 'out';
        
        // Insert cash operation record
        $stmt = $db->prepare("INSERT INTO cash_operations (user_id, operation_type, amount, description, created_at) 
                            VALUES (:user_id, :operation_type, :amount, :description, NOW())");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':operation_type', $operation_type, PDO::PARAM_STR);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        $stmt->execute();
        
        $response['success'] = true;
        $response['message'] = 'Cash ' . ($operation_type === 'in' ? 'added' : 'removed') . ' successfully';
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get cash operations data
try {
    $db = get_db_connection();
    
    // Get recent cash operations
    $stmt = $db->prepare("SELECT co.id, co.operation_type, co.amount, co.description, co.created_at, u.username 
                         FROM cash_operations co 
                         LEFT JOIN users u ON co.user_id = u.id 
                         ORDER BY co.created_at DESC 
                         LIMIT 50");
    $stmt->execute();
    $cash_operations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $cash_operations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash In/Out - <?php echo SITE_NAME; ?></title>
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
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        
        /* Cash Operations Modal */
        .cash-operations-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--dark-bg);
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .cash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: var(--dark-bg);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .cash-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .close-btn {
            background: transparent;
            border: none;
            color: var(--text-light);
            font-size: 20px;
            cursor: pointer;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background-color: var(--dark-bg);
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: var(--light-bg);
            color: var(--text-light);
            border: none;
            padding: 15px;
            width: 120px;
            height: 100px;
            margin-right: 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .action-btn.active {
            background-color: var(--accent-blue);
        }
        
        .action-btn i {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .action-btn.add i {
            color: var(--accent-green);
        }
        
        .action-btn.remove i {
            color: var(--accent-red);
        }
        
        .action-btn.active i {
            color: var(--text-light);
        }
        
        .cash-drawer-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: var(--light-bg);
            color: var(--text-light);
            border: none;
            padding: 15px;
            width: 120px;
            height: 100px;
            margin-left: auto;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .cash-drawer-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--text-light);
        }
        
        /* Form Area */
        .form-area {
            padding: 15px;
            background-color: var(--dark-bg);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            background-color: var(--light-bg);
            border: none;
            border-radius: 4px;
            color: var(--text-light);
        }
        
        .form-control:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--accent-blue);
        }
        
        /* Cash Entries Table */
        .cash-entries {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: var(--med-bg);
        }
        
        .entries-title {
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .entries-count {
            background-color: var(--accent-blue);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .entries-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .entries-table th {
            background-color: var(--light-bg);
            color: var(--text-light);
            padding: 12px;
            text-align: left;
            font-weight: 500;
        }
        
        .entries-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: var(--text-muted);
        }
        
        .entries-table tr:hover td {
            background-color: var(--light-bg);
            color: var(--text-light);
        }
        
        .no-entries {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-style: italic;
        }
        
        /* Entry Type Badges */
        .entry-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .entry-type.in {
            background-color: var(--accent-green);
            color: white;
        }
        
        .entry-type.out {
            background-color: var(--accent-red);
            color: white;
        }
        
        /* Bottom Actions */
        .bottom-actions {
            padding: 15px;
            display: flex;
            justify-content: flex-end;
            background-color: var(--dark-bg);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .save-btn {
            background-color: var(--accent-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .cancel-btn {
            background-color: var(--accent-red);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
        }
        
        .spinner {
            margin-bottom: 1rem;
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .status-message {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="cash-operations-container">
        <!-- Header -->
        <div class="cash-header">
            <div class="cash-title">Cash In / Out</div>
            <button class="close-btn" id="closeBtn">&times;</button>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="action-btn add active" id="addCashBtn">
                <i class="fas fa-arrow-down"></i>
                <span>Add cash</span>
            </button>
            <button class="action-btn remove" id="removeCashBtn">
                <i class="fas fa-arrow-up"></i>
                <span>Remove cash</span>
            </button>
            <button class="cash-drawer-btn" id="cashDrawerBtn">
                <i class="fas fa-cash-register"></i>
                <span>Cash drawer</span>
            </button>
        </div>
        
        <!-- Form Area -->
        <div class="form-area">
            <form id="cashOperationForm">
                <input type="hidden" id="operationType" name="operationType" value="add">
                
                <div class="form-group">
                    <label for="amount" class="form-label">Amount</label>
                    <input type="number" id="amount" name="amount" class="form-control" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Enter the reason for adding or removing cash..." required></textarea>
                </div>
            </form>
        </div>
        
        <!-- Cash Entries -->
        <div class="cash-entries">
            <div class="entries-title">
                Cash entries <span class="entries-count"><?php echo count($cash_operations); ?></span>
            </div>
            
            <?php if (empty($cash_operations)): ?>
                <div class="no-entries">No records</div>
            <?php else: ?>
                <table class="entries-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cash_operations as $operation): ?>
                            <tr>
                                <td><?php echo $operation['id']; ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($operation['created_at'])); ?></td>
                                <td><?php echo $operation['username']; ?></td>
                                <td>
                                    <span class="entry-type <?php echo $operation['operation_type']; ?>">
                                        <?php echo $operation['operation_type'] === 'in' ? 'Cash In' : 'Cash Out'; ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($operation['amount'], 0, '.', ','); ?></td>
                                <td><?php echo htmlspecialchars($operation['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Bottom Actions -->
        <div class="bottom-actions">
            <button id="saveBtn" class="save-btn">
                <i class="fas fa-check"></i> Save
            </button>
            <button id="cancelBtn" class="cancel-btn">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
        <div id="statusMessage" class="status-message">Processing...</div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set initial operation type
            let currentOperation = 'add';
            
            // Toggle between add and remove cash
            $('#addCashBtn').click(function() {
                $(this).addClass('active');
                $('#removeCashBtn').removeClass('active');
                $('#operationType').val('add');
                currentOperation = 'add';
            });
            
            $('#removeCashBtn').click(function() {
                $(this).addClass('active');
                $('#addCashBtn').removeClass('active');
                $('#operationType').val('remove');
                currentOperation = 'remove';
            });
            
            // Close button returns to previous page
            $('#closeBtn, #cancelBtn').click(function() {
                window.location.href = 'welcome.php';
            });
            
            // Save cash operation
            $('#saveBtn').click(function() {
                const amount = $('#amount').val();
                const description = $('#description').val();
                
                // Validate inputs
                if (!amount || amount <= 0) {
                    alert('Please enter a valid amount greater than zero');
                    return;
                }
                
                if (!description.trim()) {
                    alert('Please enter a description');
                    return;
                }
                
                // Show loading overlay
                $('#loadingOverlay').css('display', 'flex');
                $('#statusMessage').text('Processing cash operation...');
                
                // Submit via AJAX
                $.ajax({
                    url: 'cash_operations.php',
                    type: 'POST',
                    data: {
                        action: currentOperation,
                        amount: amount,
                        description: description
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Operation successful, reload page to show updated entries
                            $('#statusMessage').text(response.message + '. Refreshing...');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Show error
                            $('#loadingOverlay').hide();
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        $('#loadingOverlay').hide();
                        alert('An error occurred. Please try again.');
                    }
                });
            });
            
            // Cash drawer button (placeholder functionality)
            $('#cashDrawerBtn').click(function() {
                alert('Cash drawer functionality will be implemented in a future update.');
            });
        });
    </script>
</body>
</html>
