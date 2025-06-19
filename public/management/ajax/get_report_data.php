<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once '../../config.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'manager', 'accountant'])) {
    http_response_code(403);
    die('Access denied');
}

// Get request parameters
$report_type = $_GET['report_type'] ?? 'sales_summary';
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$customer_id = $_GET['customer_id'] ?? null;
$category_id = $_GET['category_id'] ?? null;

try {
    $db = get_db_connection();
    
    // Validate dates
    $start_date_obj = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);
    
    if ($start_date_obj > $end_date_obj) {
        throw new Exception('Start date cannot be after end date');
    }
    
    // Prepare base query based on report type
    switch ($report_type) {
        case 'sales_summary':
            $query = "SELECT 
                        DATE(d.document_date) as date,
                        COUNT(DISTINCT d.id) as num_orders,
                        SUM(d.total) as total_sales,
                        SUM(d.tax_amount) as total_tax,
                        SUM(d.discount_amount) as total_discount,
                        SUM(d.total - d.tax_amount - d.discount_amount) as net_sales
                      FROM documents d
                      WHERE d.document_type IN ('invoice', 'receipt')
                        AND d.status = 'completed'";
            break;
            
        case 'product_sales':
            $query = "SELECT 
                        p.id,
                        p.name as product_name,
                        p.code as sku,
                        c.name as category,
                        COUNT(DISTINCT d.id) as num_orders,
                        SUM(di.quantity) as quantity_sold,
                        SUM(di.subtotal) as total_revenue,
                        SUM(di.cost * di.quantity) as total_cost,
                        (SUM(di.subtotal) - SUM(di.cost * di.quantity)) as gross_profit
                      FROM document_items di
                      JOIN products p ON di.product_id = p.id
                      LEFT JOIN categories c ON p.category_id = c.id
                      JOIN documents d ON di.document_id = d.id
                      WHERE d.document_type IN ('invoice', 'receipt')
                        AND d.status = 'completed'";
            break;
            
        case 'customer':
            $query = "SELECT 
                        c.id,
                        c.name as customer_name,
                        c.email,
                        c.phone,
                        COUNT(DISTINCT d.id) as num_orders,
                        SUM(d.total) as total_spent,
                        MAX(d.document_date) as last_purchase_date,
                        (SELECT COUNT(*) FROM documents d2 
                         WHERE d2.customer_id = c.id 
                         AND d2.document_type = 'invoice' 
                         AND d2.status = 'pending_payment') as pending_invoices
                      FROM customers c
                      LEFT JOIN documents d ON c.id = d.customer_id
                      WHERE c.type IN ('customer', 'both')";
            break;
            
        case 'inventory':
            $query = "SELECT 
                        p.id,
                        p.name as product_name,
                        p.code as sku,
                        c.name as category,
                        p.stock_quantity as current_stock,
                        p.min_stock as reorder_level,
                        p.cost as unit_cost,
                        (p.stock_quantity * p.cost) as inventory_value,
                        p.price as selling_price,
                        (p.price - p.cost) as profit_margin
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      WHERE p.active = 1";
            break;
            
        default:
            throw new Exception('Invalid report type');
    }
    
    // Add date filter
    if (in_array($report_type, ['sales_summary', 'product_sales', 'customer'])) {
        $query .= " AND d.document_date BETWEEN :start_date AND DATE_ADD(:end_date, INTERVAL 1 DAY)";
    }
    
    // Add customer filter
    if ($customer_id && $customer_id !== 'all' && in_array($report_type, ['sales_summary', 'product_sales'])) {
        $query .= " AND d.customer_id = :customer_id";
    }
    
    // Add category filter
    if ($category_id && $category_id !== 'all' && in_array($report_type, ['product_sales', 'inventory'])) {
        $query .= " AND p.category_id = :category_id";
    }
    
    // Group by and order
    switch ($report_type) {
        case 'sales_summary':
            $query .= " GROUP BY DATE(d.document_date) ORDER BY date DESC";
            break;
        case 'product_sales':
            $query .= " GROUP BY p.id ORDER BY quantity_sold DESC";
            break;
        case 'customer':
            $query .= " GROUP BY c.id ORDER BY total_spent DESC";
            break;
        case 'inventory':
            $query .= " ORDER BY (p.stock_quantity <= p.min_stock) DESC, inventory_value DESC";
            break;
    }
    
    // Prepare and execute query
    $stmt = $db->prepare($query);
    
    // Bind parameters
    if (in_array($report_type, ['sales_summary', 'product_sales', 'customer'])) {
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
    }
    
    if ($customer_id && $customer_id !== 'all' && in_array($report_type, ['sales_summary', 'product_sales'])) {
        $stmt->bindParam(':customer_id', $customer_id);
    }
    
    if ($category_id && $category_id !== 'all' && in_array($report_type, ['product_sales', 'inventory'])) {
        $stmt->bindParam(':category_id', $category_id);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML table based on report type
    if (empty($results)) {
        echo '<div class="alert alert-info">No data found for the selected criteria.</div>';
        exit;
    }
    
    // Start building the table
    $html = '<table class="table table-bordered table-hover">';
    $html .= '<thead><tr>';
    
    // Table headers based on report type
    switch ($report_type) {
        case 'sales_summary':
            $html .= '<th>Date</th>';
            $html .= '<th class="text-end">Orders</th>';
            $html .= '<th class="text-end">Net Sales</th>';
            $html .= '<th class="text-end">Tax</th>';
            $html .= '<th class="text-end">Discount</th>';
            $html .= '<th class="text-end">Total Sales</th>';
            break;
            
        case 'product_sales':
            $html .= '<th>Product</th>';
            $html .= '<th>SKU</th>';
            $html .= '<th>Category</th>';
            $html .= '<th class="text-end">Orders</th>';
            $html .= '<th class="text-end">Qty Sold</th>';
            $html .= '<th class="text-end">Revenue</th>';
            $html .= '<th class="text-end">Cost</th>';
            $html .= '<th class="text-end">Profit</th>';
            $html .= '<th class="text-end">Margin</th>';
            break;
            
        case 'customer':
            $html .= '<th>Customer</th>';
            $html .= '<th>Contact</th>';
            $html .= '<th class="text-end">Orders</th>';
            $html .= '<th class="text-end">Total Spent</th>';
            $html .= '<th>Last Purchase</th>';
            $html .= '<th class="text-end">Pending Invoices</th>';
            break;
            
        case 'inventory':
            $html .= '<th>Product</th>';
            $html .= '<th>SKU</th>';
            $html .= '<th>Category</th>';
            $html .= '<th class="text-end">In Stock</th>';
            $html .= '<th class="text-end">Reorder Level</th>';
            $html .= '<th class="text-end">Unit Cost</th>';
            $html .= '<th class="text-end">Total Value</th>';
            $html .= '<th class="text-end">Selling Price</th>';
            $html .= '<th class="text-end">Profit Margin</th>';
            break;
    }
    
    $html .= '</tr></thead><tbody>';
    
    // Format and add table rows
    foreach ($results as $row) {
        $html .= '<tr>';
        
        switch ($report_type) {
            case 'sales_summary':
                $html .= sprintf(
                    '<td>%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end fw-bold">%s</td>',
                    date('M j, Y', strtotime($row['date'])),
                    number_format($row['num_orders']),
                    number_format($row['net_sales'], 2),
                    number_format($row['total_tax'], 2),
                    number_format($row['total_discount'], 2),
                    number_format($row['total_sales'], 2)
                );
                break;
                
            case 'product_sales':
                $margin = $row['total_revenue'] > 0 ? 
                    (($row['gross_profit'] / $row['total_revenue']) * 100) : 0;
                    
                $html .= sprintf(
                    '<td>%s</td><td>%s</td><td>%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s%%</td>',
                    htmlspecialchars($row['product_name']),
                    htmlspecialchars($row['sku']),
                    htmlspecialchars($row['category'] ?? 'Uncategorized'),
                    number_format($row['num_orders']),
                    number_format($row['quantity_sold']),
                    number_format($row['total_revenue'], 2),
                    number_format($row['total_cost'], 2),
                    number_format($row['gross_profit'], 2),
                    number_format($margin, 1)
                );
                break;
                
            case 'customer':
                $last_purchase = $row['last_purchase_date'] ? 
                    date('M j, Y', strtotime($row['last_purchase_date'])) : 'Never';
                    
                $html .= sprintf(
                    '<td>%s</td><td>%s<br>%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td>%s</td><td class="text-end">%s</td>',
                    htmlspecialchars($row['customer_name']),
                    htmlspecialchars($row['email']),
                    htmlspecialchars($row['phone']),
                    number_format($row['num_orders']),
                    number_format($row['total_spent'], 2),
                    $last_purchase,
                    $row['pending_invoices'] > 0 ? 
                        '<span class="badge bg-danger">' . $row['pending_invoices'] . '</span>' : 
                        '<span class="badge bg-success">None</span>'
                );
                break;
                
            case 'inventory':
                $margin = $row['selling_price'] > 0 ? 
                    (($row['profit_margin'] / $row['selling_price']) * 100) : 0;
                    
                $stock_class = $row['current_stock'] <= $row['reorder_level'] ? 'text-danger fw-bold' : '';
                
                $html .= sprintf(
                    '<td>%s</td><td>%s</td><td>%s</td><td class="text-end %s">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s%%</td>',
                    htmlspecialchars($row['product_name']),
                    htmlspecialchars($row['sku']),
                    htmlspecialchars($row['category'] ?? 'Uncategorized'),
                    $stock_class,
                    number_format($row['current_stock']),
                    number_format($row['reorder_level']),
                    number_format($row['unit_cost'], 2),
                    number_format($row['inventory_value'], 2),
                    number_format($row['selling_price'], 2),
                    number_format($margin, 1)
                );
                break;
        }
        
        $html .= '</tr>';
    }
    
    // Add summary row if needed
    if (in_array($report_type, ['sales_summary', 'product_sales'])) {
        $summary = [
            'num_orders' => array_sum(array_column($results, 'num_orders')),
            'total_sales' => array_sum(array_column($results, 'total_sales')),
            'net_sales' => array_sum(array_column($results, 'net_sales')),
            'total_tax' => array_sum(array_column($results, 'total_tax')),
            'total_discount' => array_sum(array_column($results, 'total_discount')),
            'quantity_sold' => array_sum(array_column($results, 'quantity_sold')),
            'total_revenue' => array_sum(array_column($results, 'total_revenue')),
            'total_cost' => array_sum(array_column($results, 'total_cost')),
            'gross_profit' => array_sum(array_column($results, 'gross_profit'))
        ];
        
        $html .= '<tr class="table-active fw-bold">';
        
        switch ($report_type) {
            case 'sales_summary':
                $html .= sprintf(
                    '<td>Total</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td>',
                    number_format($summary['num_orders']),
                    number_format($summary['net_sales'], 2),
                    number_format($summary['total_tax'], 2),
                    number_format($summary['total_discount'], 2),
                    number_format($summary['total_sales'], 2)
                );
                break;
                
            case 'product_sales':
                $margin = $summary['total_revenue'] > 0 ? 
                    (($summary['gross_profit'] / $summary['total_revenue']) * 100) : 0;
                    
                $html .= sprintf(
                    '<td colspan="3" class="text-end">Total</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s</td><td class="text-end">%s%%</td>',
                    number_format($summary['num_orders']),
                    number_format($summary['quantity_sold']),
                    number_format($summary['total_revenue'], 2),
                    number_format($summary['total_cost'], 2),
                    number_format($summary['gross_profit'], 2),
                    number_format($margin, 1)
                );
                break;
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    
    echo $html;
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
