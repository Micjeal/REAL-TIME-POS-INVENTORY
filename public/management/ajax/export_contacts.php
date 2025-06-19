<?php
// Include the configuration file
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if contact type is provided
if (!isset($_GET['type']) || empty($_GET['type'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Contact type is required']);
    exit();
}

$contact_type = $_GET['type'];
$type_condition = '';
$filename = '';

// Set condition and filename based on contact type
if ($contact_type === 'customers') {
    $type_condition = 'is_customer = 1';
    $filename = 'customers_export_' . date('Y-m-d') . '.csv';
} elseif ($contact_type === 'suppliers') {
    $type_condition = 'is_supplier = 1';
    $filename = 'suppliers_export_' . date('Y-m-d') . '.csv';
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid contact type']);
    exit();
}

try {
    $db = get_db_connection();
    
    // Get all contacts of the specified type
    $sql = "SELECT code, name, tax_number, address, city, postal_code, country, 
            phone, email, website, credit_limit, payment_terms, notes, 
            is_active, is_tax_exempt 
            FROM customers_suppliers 
            WHERE $type_condition 
            ORDER BY code";
    
    $stmt = $db->query($sql);
    $contacts = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for proper encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add column headers
    fputcsv($output, [
        'Code', 'Name', 'Tax Number', 'Address', 'City', 'Postal Code', 'Country',
        'Phone', 'Email', 'Website', 'Credit Limit', 'Payment Terms', 'Notes',
        'Active', 'Tax Exempt'
    ]);
    
    // Add rows
    foreach ($contacts as $contact) {
        // Convert boolean values to Yes/No
        $contact['is_active'] = $contact['is_active'] ? 'Yes' : 'No';
        $contact['is_tax_exempt'] = $contact['is_tax_exempt'] ? 'Yes' : 'No';
        
        fputcsv($output, $contact);
    }
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
