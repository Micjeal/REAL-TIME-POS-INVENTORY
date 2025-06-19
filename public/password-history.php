<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$password_history = [];

try {
    $db = get_db_connection();
    
    if ($is_admin && isset($_GET['user_id'])) {
        // Admins can view history for any user
        $target_user_id = (int)$_GET['user_id'];
        $stmt = $db->prepare("
            SELECT h.*, u.username as changed_by_name 
            FROM user_password_history h
            LEFT JOIN users u ON h.changed_by = u.id
            WHERE h.user_id = :user_id 
            ORDER BY h.changed_at DESC
        ");
        $stmt->execute(['user_id' => $target_user_id]);
    } else {
        // Regular users can only see their own history
        $stmt = $db->prepare("
            SELECT h.*, u.username as changed_by_name 
            FROM user_password_history h
            LEFT JOIN users u ON h.changed_by = u.id
            WHERE h.user_id = :user_id 
            ORDER BY h.changed_at DESC
            LIMIT 50
        ");
        $stmt->execute(['user_id' => $user_id]);
    }
    
    $password_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Change History - <?php echo SITE_NAME; ?></title>
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
            margin: 0;
            padding: 20px;
        }
        
        .history-container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: var(--med-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            padding: 20px;
        }
        
        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-bg);
        }
        
        .history-title {
            margin: 0;
            color: var(--text-light);
        }
        
        .back-link {
            color: var(--accent-blue);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .history-table th,
        .history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-bg);
        }
        
        .history-table th {
            background-color: var(--light-bg);
            color: var(--accent-blue);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        .history-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .ip-address {
            font-family: monospace;
            font-size: 13px;
            color: var(--text-muted);
        }
        
        .user-agent {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .no-history {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .history-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .user-agent {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="history-container">
        <div class="history-header">
            <h2 class="history-title">
                <i class="fas fa-history"></i> Password Change History
                <?php if (isset($_GET['user_id']) && $is_admin): ?>
                    for User ID: <?php echo htmlspecialchars($_GET['user_id']); ?>
                <?php endif; ?>
            </h2>
            <a href="user-info.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($password_history)): ?>
            <div class="no-history">
                <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>No password change history found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <?php if ($is_admin): ?>
                                <th>Changed By</th>
                            <?php endif; ?>
                            <th>IP Address</th>
                            <th>Device/Browser</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($password_history as $entry): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $date = new DateTime($entry['changed_at']);
                                    echo htmlspecialchars($date->format('M d, Y H:i:s')); 
                                    ?>
                                </td>
                                <?php if ($is_admin): ?>
                                    <td>
                                        <?php 
                                        if ($entry['changed_by'] == $user_id) {
                                            echo 'You';
                                        } else {
                                            echo htmlspecialchars($entry['changed_by_name'] ?? 'System');
                                        }
                                        ?>
                                    </td>
                                <?php endif; ?>
                                <td class="ip-address">
                                    <?php echo htmlspecialchars($entry['ip_address'] ?? 'N/A'); ?>
                                </td>
                                <td class="user-agent" title="<?php echo htmlspecialchars($entry['user_agent'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($this->getBrowserFromUserAgent($entry['user_agent'] ?? '')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Add any JavaScript functionality here
        });
    </script>
</body>
</html>

<?php
// Helper function to extract browser info from user agent
function getBrowserFromUserAgent($user_agent) {
    if (empty($user_agent)) return 'Unknown';
    
    $browser = 'Unknown';
    
    if (preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/Chrome/i', $user_agent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser = 'Apple Safari';
    } elseif (preg_match('/Opera/i', $user_agent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Netscape/i', $user_agent)) {
        $browser = 'Netscape';
    }
    
    // Try to get OS
    $os = 'Unknown';
    if (preg_match('/windows|win32/i', $user_agent)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $user_agent)) {
        $os = 'Mac OS';
    } elseif (preg_match('/linux/i', $user_agent)) {
        $os = 'Linux';
    } elseif (preg_match('/android/i', $user_agent)) {
        $os = 'Android';
    } elseif (preg_match('/iphone|ipad|ipod/i', $user_agent)) {
        $os = 'iOS';
    }
    
    return $browser . ' on ' . $os;
}
?>
