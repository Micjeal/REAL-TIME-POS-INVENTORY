<?php
// Fetch recent sales for dashboard
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Check if we should use documents table or sales table
    $stmt = $db->query("SHOW TABLES LIKE 'documents'");
    $documents_table_exists = $stmt->rowCount() > 0;
    
    $stmt = $db->query("SHOW TABLES LIKE 'sales'");
    $sales_table_exists = $stmt->rowCount() > 0;
    
    $recent_sales = [];
    
    if ($documents_table_exists) {
        // Use documents table (newer schema)
        $stmt = $db->prepare("SELECT 
                            d.id, 
                            d.document_number, 
                            d.document_date, 
                            d.total, 
                            d.paid_status,
                            d.payment_method,
                            cs.name as customer_name,
                            u.name as user_name
                        FROM documents d
                        LEFT JOIN customers_suppliers cs ON d.customer_id = cs.id
                        LEFT JOIN users u ON d.user_id = u.id
                        WHERE d.document_type = 'receipt'
                        ORDER BY d.document_date DESC
                        LIMIT 10");
        $stmt->execute();
        $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($recent_sales as &$sale) {
            $sale['date'] = date('M j, Y H:i', strtotime($sale['document_date']));
            $sale['amount'] = $sale['total'];
            $sale['invoice_number'] = $sale['document_number'];
        }
    } elseif ($sales_table_exists) {
        // Use sales table (older schema)
        $stmt = $db->prepare("SELECT 
                            s.id, 
                            s.invoice_number, 
                            s.date, 
                            s.total_amount as amount, 
                            s.paid_status,
                            s.payment_method,
                            c.name as customer_name,
                            u.name as user_name
                        FROM sales s
                        LEFT JOIN customers c ON s.customer_id = c.id
                        LEFT JOIN users u ON s.user_id = u.id
                        ORDER BY s.date DESC
                        LIMIT 10");
        $stmt->execute();
        $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($recent_sales as &$sale) {
            $sale['date'] = date('M j, Y H:i', strtotime($sale['date']));
        }
    }
    
    echo json_encode(['success' => true, 'sales' => $recent_sales]);
    
} catch (PDOException $e) {
    error_log("Error in get_recent_sales.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}