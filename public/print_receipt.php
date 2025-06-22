<?php
// Start the session
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Get document ID from URL parameter (support both doc_id and invoice_number)
$doc_id = isset($_GET['doc_id']) ? $_GET['doc_id'] : (isset($_GET['invoice_number']) ? $_GET['invoice_number'] : null);

if (!$doc_id) {
    die('Error: No document ID or invoice number provided');
}

try {
    // Get database connection
    $pdo = get_db_connection();
    
    // Get sale details
    $stmt = $pdo->prepare("SELECT s.*, 
                          cs.name as customer_name, 
                          cs.phone as customer_phone, 
                          cs.email as customer_email, 
                          u.name as user_name 
                          FROM sales s 
                          LEFT JOIN customers cs ON s.customer_id = cs.id 
                          LEFT JOIN users u ON s.user_id = u.id 
                          WHERE s.invoice_number = ?");
    $stmt->execute([$doc_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        die('Error: Sale not found');
    }
    
    // Get sale items
    $stmt = $pdo->prepare("SELECT si.*, p.name as product_name, p.code as product_code 
                          FROM sale_items si 
                          LEFT JOIN products p ON si.product_id = p.id 
                          WHERE si.sale_id = ?");
    $stmt->execute([$sale['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Include TCPDF library
    require_once('management/lib/tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('P', 'mm', array(80, 200), true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('MTECH UGANDA');
    $pdf->SetAuthor('MTECH UGANDA');
    $pdf->SetTitle('Receipt #' . $sale['invoice_number']);
    $pdf->SetSubject('Receipt');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(5, 5, 5);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(true, 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 8);
    
    // Get company information
    $stmt = $pdo->query("SELECT * FROM company LIMIT 1");
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Company header
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, $company['name'] ?? 'MTECH UGANDA', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, $company['address'] ?? '', 0, 1, 'C');
    $pdf->Cell(0, 4, 'Tel: ' . ($company['phone'] ?? ''), 0, 1, 'C');
    $pdf->Cell(0, 4, $company['email'] ?? '', 0, 1, 'C');
    
    // Receipt title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'MTECH UGANDA', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Receipt #' . $sale['invoice_number'], 0, 1, 'C');
    $pdf->Cell(0, 5, 'Date: ' . date('d/m/Y H:i', strtotime($sale['date'])), 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(30, 4, 'Date:', 0, 0);
    $pdf->Cell(40, 4, date('d/m/Y H:i', strtotime($sale['date'])), 0, 1);
    
    // Add customer info
    if (!empty($sale['customer_name'])) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Customer:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, $sale['customer_name'], 0, 1);
        if (!empty($sale['customer_phone'])) {
            $pdf->Cell(0, 5, 'Phone: ' . $sale['customer_phone'], 0, 1);
        }
        if (!empty($sale['customer_email'])) {
            $pdf->Cell(0, 5, 'Email: ' . $sale['customer_email'], 0, 1);
        }
        $pdf->Ln(5);
    }
    
    $pdf->Cell(30, 4, 'Cashier:', 0, 0);
    $pdf->Cell(40, 4, $sale['user_name'], 0, 1);
    
    $pdf->Cell(30, 4, 'Payment Method:', 0, 0);
    $pdf->Cell(40, 4, ucfirst($sale['payment_type']), 0, 1);
    
    // Line break
    $pdf->Ln(2);
    $pdf->Cell(70, 0, '', 'T', 1);
    
    // Add items
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 5, 'Item', 0, 0);
    $pdf->Cell(15, 5, 'Qty', 0, 0, 'R');
    $pdf->Cell(15, 5, 'Price', 0, 0, 'R');
    $pdf->Cell(20, 5, 'Total', 0, 1, 'R');
    $pdf->Line(10, $pdf->GetY(), 80, $pdf->GetY());
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 9);
    $total = 0;
    
    foreach ($items as $item) {
        $line = $item['product_name'] . ' (' . $item['product_code'] . ')';
        $pdf->Cell(40, 5, $line, 0, 0);
        $pdf->Cell(15, 5, number_format($item['quantity'], 2), 0, 0, 'R');
        $pdf->Cell(15, 5, number_format($item['unit_price'], 2), 0, 0, 'R');
        $pdf->Cell(20, 5, number_format($item['quantity'] * $item['unit_price'], 2), 0, 1, 'R');
        $total += $item['quantity'] * $item['unit_price'];
    }
    
    // Line break
    $pdf->Cell(70, 0, '', 'T', 1);
    $pdf->Ln(2);
    
    // Totals
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(55, 5, 'Total:', 0, 0, 'R');
    $pdf->Cell(15, 5, number_format($total, 2), 0, 1);
    
    // Add payment info
    $pdf->Ln(5);
    $pdf->Cell(0, 5, 'Payment Method: ' . ucfirst(str_replace('_', ' ', $sale['payment_type'])), 0, 1);
    $pdf->Cell(0, 5, 'Payment Status: ' . ucfirst($sale['payment_status']), 0, 1);
    
    // Add notes if any
    if (!empty($sale['notes'])) {
        $pdf->Ln(5);
        $pdf->MultiCell(0, 5, 'Notes: ' . $sale['notes']);
    }
    
    // Thank you note
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, 'Thank you for your business!', 0, 1, 'C');
    
    // Output the PDF
    $pdf->Output('receipt_' . $sale['invoice_number'] . '.pdf', 'I');
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}