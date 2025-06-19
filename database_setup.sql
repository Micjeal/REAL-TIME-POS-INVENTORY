-- MTECH UGANDA Database Setup Script
-- Created on: 2025-05-26

-- 1️⃣ Create Database
CREATE DATABASE IF NOT EXISTS mtech-uganda;
USE mtech-uganda;

-- 2️⃣ Create Tables

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    email VARCHAR(100),
    role ENUM('admin', 'manager', 'cashier') NOT NULL DEFAULT 'cashier',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Password History Table
CREATE TABLE IF NOT EXISTS user_password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at DATETIME NOT NULL,
    changed_by INT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    KEY idx_user_id (user_id),
    KEY idx_changed_at (changed_at),
    KEY idx_user_changed_at (user_id, changed_at),
    CONSTRAINT fk_user_password_history_user 
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,  -- NULL means broadcast to all admins/managers
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    related_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Feedback Table
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating TINYINT DEFAULT NULL,
    status ENUM('new', 'in_progress', 'resolved') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (rating IS NULL OR (rating >= 1 AND rating <= 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Company Table
CREATE TABLE IF NOT EXISTS company (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    tax_number VARCHAR(100),
    street_name VARCHAR(255),
    building_number VARCHAR(50),
    additional_street_name VARCHAR(255),
    plot_identification VARCHAR(100),
    district VARCHAR(100),
    postal_code VARCHAR(20),
    city VARCHAR(100),
    state_province VARCHAR(100),
    country VARCHAR(100),
    phone_number VARCHAR(50),
    email VARCHAR(255),
    bank_account VARCHAR(255),
    bank_acc_number VARCHAR(100),
    bank_details TEXT,
    logo_path VARCHAR(255)
);

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    active TINYINT(1) DEFAULT 1
);

-- Tax Rates Table
CREATE TABLE IF NOT EXISTS tax_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rate DECIMAL(5,2) NOT NULL,
    active TINYINT(1) DEFAULT 1
);

-- Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE,
    barcode VARCHAR(50),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    unit_of_measure VARCHAR(20) DEFAULT 'pcs',
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_rate_id INT,
    tax_included TINYINT(1) DEFAULT 1,
    cost DECIMAL(10,2) DEFAULT 0,
    stock_quantity DECIMAL(10,2) DEFAULT 0,
    min_stock DECIMAL(10,2) DEFAULT 0,
    image_path VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL
);

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('customer', 'supplier', 'both') DEFAULT 'customer',
    tax_number VARCHAR(100),
    address VARCHAR(255),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(100),
    contact_person VARCHAR(100),
    notes TEXT,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Cash Registers Table
CREATE TABLE IF NOT EXISTS cash_registers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    active TINYINT(1) DEFAULT 1
);

-- Documents Table
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_number VARCHAR(50) NOT NULL,
    external_document VARCHAR(50),
    document_type ENUM('invoice', 'receipt', 'order', 'quote', 'credit_note', 'delivery_note') NOT NULL,
    document_date DATETIME NOT NULL,
    customer_id INT,
    user_id INT,
    cash_register_id INT,
    order_number VARCHAR(50),
    paid_status ENUM('paid', 'unpaid', 'partial', 'cancelled') DEFAULT 'unpaid',
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE SET NULL
);

-- Document Items Table
CREATE TABLE IF NOT EXISTS document_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    product_id INT,
    description VARCHAR(255),
    unit_of_measure VARCHAR(20),
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    price_before_tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_before_discount DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Price Lists Table
CREATE TABLE IF NOT EXISTS price_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_default TINYINT(1) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Price List Items Table
CREATE TABLE IF NOT EXISTS price_list_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    price_list_id INT NOT NULL,
    product_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY (price_list_id, product_id)
);

-- Promotions Table
CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    active_days VARCHAR(50) DEFAULT '1,2,3,4,5,6,7',
    discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_purchase_amount DECIMAL(10,2) DEFAULT 0,
    min_purchase_qty INT DEFAULT 0,
    apply_to ENUM('all', 'specific_products') DEFAULT 'all',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Promotion Products Table
CREATE TABLE IF NOT EXISTS promotion_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    product_id INT NOT NULL,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY (promotion_id, product_id)
);

-- Stock Movements Table
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    document_id INT,
    type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    notes TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 3️⃣ Insert Sample Data

-- Admin user
INSERT INTO users (username, password, name, role) VALUES
('admin', '$2y$10$uoU/U5J0MKeASy.2mHvkF.Rme.ZmlxksXAjNQHbw2UwBfSvwTr8EO', 'Administrator', 'admin');

-- Company Info
INSERT INTO company (name, tax_number, city, country, phone_number, email) VALUES
('MTECH UGANDA', 'UG123456789', 'Kampala', 'Uganda', '+256 123 456 789', 'info@mtechuganda.com');

-- Tax Rates
INSERT INTO tax_rates (name, rate) VALUES
('No Tax', 0),
('Standard VAT', 18),
('Reduced VAT', 10);

-- Categories
INSERT INTO categories (name, description) VALUES
('Electronics', 'Electronic devices and accessories'),
('Groceries', 'Food and household items'),
('Clothing', 'Apparel and fashion items'),
('Stationery', 'Office and school supplies');

-- Products
INSERT INTO products (code, name, description, category_id, unit_of_measure, price, tax_rate_id, tax_included, cost, stock_quantity, min_stock) VALUES
('P001', 'Laptop', 'High-performance laptop', 1, 'pcs', 1500000, 2, 1, 1200000, 5, 2),
('P002', 'Smartphone', 'Latest smartphone model', 1, 'pcs', 800000, 2, 1, 650000, 10, 3),
('P003', 'Rice', 'Premium quality rice', 2, 'kg', 4500, 3, 1, 3200, 50, 10),
('P004', 'T-Shirt', 'Cotton t-shirt', 3, 'pcs', 25000, 2, 1, 15000, 30, 5),
('P005', 'Notebook', 'A4 lined notebook', 4, 'pcs', 5000, 1, 1, 3000, 100, 20);

-- Sales Table
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    user_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    date DATETIME NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_type ENUM('cash', 'card', 'mobile_money', 'credit') NOT NULL,
    payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_number),
    INDEX idx_date (date),
    INDEX idx_customer (customer_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales Items Table
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    tax_rate_id INT,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    subtotal DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customers
INSERT INTO customers (name, type, tax_number, address, city, country, phone, email) VALUES
('John Doe', 'customer', 'UG987654321', '123 Main St', 'Kampala', 'Uganda', '+256 111 222 333', 'john@example.com'),
('ABC Company', 'both', 'UG567890123', '456 Business Ave', 'Entebbe', 'Uganda', '+256 444 555 666', 'info@abccompany.com'),
('XYZ Suppliers', 'supplier', 'UG345678901', '789 Supply St', 'Jinja', 'Uganda', '+256 777 888 999', 'xyz@suppliers.com');
