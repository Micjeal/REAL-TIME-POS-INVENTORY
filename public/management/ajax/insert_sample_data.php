<?php
require_once '../../config.php';

try {
    $db = get_db_connection();

    // Insert sample product categories
    $db->exec("INSERT INTO product_categories (name, description) VALUES 
        ('Electronics', 'Electronic devices and accessories'),
        ('Clothing', 'Apparel and fashion items'),
        ('Food & Beverages', 'Consumable items')");

    // Insert sample products
    $db->exec("INSERT INTO products (name, description, category_id, price, cost_price, quantity, tax_rate) VALUES 
        ('Laptop', 'High performance laptop', 1, 1200.00, 1000.00, 10, 18.00),
        ('Smartphone', '5G enabled smartphone', 1, 800.00, 600.00, 15, 18.00),
        ('T-Shirt', 'Cotton T-shirt', 2, 20.00, 10.00, 100, 18.00),
        ('Soft Drink', '500ml beverage', 3, 2.00, 1.00, 200, 18.00)");

    // Insert sample customers
    $db->exec("INSERT INTO contacts (name, email, phone, is_customer) VALUES 
        ('John Doe', 'john@example.com', '+256700000001', 1),
        ('Jane Smith', 'jane@example.com', '+256700000002', 1)");

    // Insert sample sales
    $db->exec("INSERT INTO sales (customer_id, user_id, invoice_number, date, total_amount, payment_type, payment_status, tax_amount) VALUES 
        (1, 1, 'INV-2025-001', '2025-05-26 10:00:00', 1200.00, 'Cash', 'Paid', 216.00),
        (2, 1, 'INV-2025-002', '2025-05-26 11:30:00', 822.00, 'Credit Card', 'Paid', 147.96),
        (1, 1, 'INV-2025-003', '2025-05-27 09:15:00', 40.00, 'Cash', 'Paid', 7.20)");

    // Insert sample sale items
    $db->exec("INSERT INTO sale_items (sale_id, product_id, quantity, price, tax_rate, total_amount) VALUES 
        (1, 1, 1, 1200.00, 18.00, 1200.00),
        (2, 2, 1, 800.00, 18.00, 800.00),
        (2, 4, 11, 2.00, 18.00, 22.00),
        (3, 3, 2, 20.00, 18.00, 40.00)");

    // Insert sample stock movements
    $db->exec("INSERT INTO stock_movements (product_id, type, quantity, reference_type, date, created_by) VALUES 
        (1, 'OUT', 1, 'SALE', '2025-05-26 10:00:00', 1),
        (2, 'OUT', 1, 'SALE', '2025-05-26 11:30:00', 1),
        (3, 'OUT', 2, 'SALE', '2025-05-27 09:15:00', 1)");

    echo json_encode(['success' => true, 'message' => 'Sample data inserted successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
