-- Create customer_audit table to track changes to customers
CREATE TABLE IF NOT EXISTS customer_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_data JSON DEFAULT NULL,
    new_data JSON DEFAULT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    INDEX idx_customer_id (customer_id),
    INDEX idx_changed_at (changed_at),
    CONSTRAINT fk_customer_audit_user 
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create trigger for INSERT
DELIMITER //
CREATE TRIGGER after_customer_insert
AFTER INSERT ON customers
FOR EACH ROW
BEGIN
    INSERT INTO customer_audit (
        customer_id, 
        action, 
        new_data,
        changed_by,
        ip_address,
        user_agent
    ) VALUES (
        NEW.id,
        'INSERT',
        JSON_OBJECT(
            'name', NEW.name,
            'email', NEW.email,
            'phone', NEW.phone,
            'address', NEW.address,
            'tax_number', NEW.tax_number,
            'discount', NEW.discount,
            'notes', NEW.notes,
            'status', NEW.status
        ),
        @current_user_id,
        @current_ip_address,
        @current_user_agent
    );
END //


-- Create trigger for UPDATE
CREATE TRIGGER after_customer_update
AFTER UPDATE ON customers
FOR EACH ROW
BEGIN
    IF OLD.name != NEW.name OR 
       OLD.email != NEW.email OR 
       OLD.phone != NEW.phone OR 
       OLD.address != NEW.address OR 
       OLD.tax_number != NEW.tax_number OR 
       OLD.discount != NEW.discount OR 
       OLD.notes != NEW.notes OR 
       OLD.status != NEW.status THEN
        
        INSERT INTO customer_audit (
            customer_id, 
            action, 
            old_data,
            new_data,
            changed_by,
            ip_address,
            user_agent
        ) VALUES (
            NEW.id,
            'UPDATE',
            JSON_OBJECT(
                'name', OLD.name,
                'email', OLD.email,
                'phone', OLD.phone,
                'address', OLD.address,
                'tax_number', OLD.tax_number,
                'discount', OLD.discount,
                'notes', OLD.notes,
                'status', OLD.status
            ),
            JSON_OBJECT(
                'name', NEW.name,
                'email', NEW.email,
                'phone', NEW.phone,
                'address', NEW.address,
                'tax_number', NEW.tax_number,
                'discount', NEW.discount,
                'notes', NEW.notes,
                'status', NEW.status
            ),
            @current_user_id,
            @current_ip_address,
            @current_user_agent
        );
    END IF;
END //


-- Create trigger for DELETE
CREATE TRIGGER before_customer_delete
BEFORE DELETE ON customers
FOR EACH ROW
BEGIN
    INSERT INTO customer_audit (
        customer_id, 
        action, 
        old_data,
        changed_by,
        ip_address,
        user_agent
    ) VALUES (
        OLD.id,
        'DELETE',
        JSON_OBJECT(
            'name', OLD.name,
            'email', OLD.email,
            'phone', OLD.phone,
            'address', OLD.address,
            'tax_number', OLD.tax_number,
            'discount', OLD.discount,
            'notes', OLD.notes,
            'status', OLD.status
        ),
        @current_user_id,
        @current_ip_address,
        @current_user_agent
    );
END //

DELIMITER ;
