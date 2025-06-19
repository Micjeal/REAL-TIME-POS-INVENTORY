-- Create credit_payments table
CREATE TABLE IF NOT EXISTS `credit_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `credit_payments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credit_payments_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `credit_payments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add credit_balance column to customers table if it doesn't exist
ALTER TABLE `customers` 
ADD COLUMN IF NOT EXISTS `credit_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `phone`;
