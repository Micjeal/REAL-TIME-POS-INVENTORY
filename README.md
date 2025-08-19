I'm# MTECH UGANDA - Point of Sale (POS) & Inventory Management System

## Overview
MTECH UGANDA is a comprehensive Point of Sale (POS) and Inventory Management System designed to streamline business operations for retail and wholesale businesses. The system provides robust features for sales management, inventory control, customer management, and business analytics.

## Features

### User Management
- Multi-user support with role-based access control (Admin, Manager, Cashier)
- Secure login with session management
- Password recovery system
- User activity tracking

### Sales Management
- Point of Sale (POS) interface
- Barcode scanning support
- Receipt generation
- Sales history and tracking
- Returns and refunds processing

### Inventory Management
- Product catalog with categories and subcategories
- Stock level monitoring
- Low stock alerts
- Product variants and attributes
- Batch and expiry date tracking

### Customer Management
- Customer database
- Purchase history
- Customer loyalty programs
- Contact management

### Reporting & Analytics
- Sales reports (daily, weekly, monthly, yearly)
- Inventory reports
- Customer purchase history
- Profit and loss statements
- Top-selling products analysis

### Promotions & Pricing
- Dynamic pricing rules
- Special promotions and discounts
- Bulk pricing
- Coupon management

## System Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2 or higher
- Web server (Apache/Nginx)
- PHP extensions: PDO, MySQLi, OpenSSL, JSON, cURL, GD Library

### Browser Support
- Google Chrome (latest)
- Mozilla Firefox (latest)
- Microsoft Edge (latest)
- Safari (latest)

## Installation

1. **Prerequisites**
   - Install XAMPP/WAMP/LAMP stack
   - Ensure PHP and MySQL are properly configured

2. **Database Setup**
   - Create a new MySQL database
   - Import the database schema from `database_setup.sql`
   - Update database credentials in `public/config.php`

3. **Application Setup**
   - Clone or extract the project files to your web server's root directory
   - Set proper file permissions (755 for directories, 644 for files)
   - Ensure the `uploads` directory is writable

4. **Initial Configuration**
   - Access the application through your web browser
   - Complete the initial setup wizard
   - Create an admin user account

## Getting Started

### Login
1. Navigate to the login page
2. Enter your credentials
3. You'll be redirected to the dashboard upon successful login

### Dashboard
- View key metrics and reports
- Quick access to main features
- Recent transactions and alerts

## Security

### Best Practices
- Always use strong passwords
- Regularly backup your database
- Keep the system updated
- Restrict file permissions
- Use HTTPS for secure data transmission

## Support

For technical support or feature requests, please contact:
- Email: mugishamicheal24@gmail.com
- Phone: +256 768 432 509

## License

This software is proprietary and confidential. Unauthorized copying, distribution, modification, public display, or public performance of this software is strictly prohibited.

## Recent Updates (June 2024)

### Users & Security Management
- Implemented comprehensive user management system with role-based access control (Admin, Manager, Cashier)
- Added user listing with sorting and filtering capabilities
- Implemented user CRUD operations (Create, Read, Update, Delete)
- Added password history tracking and security features
- Enhanced session management and security controls

### User Experience Improvements
- Added logout confirmation dialog with 5-second countdown timer
- Implemented loading overlay for login process with status messages
- Enhanced form validation and error handling
- Added success/error feedback messages throughout the application

### Promotions Management
- Created promotions listing interface
- Implemented promotion creation with date ranges and active days
- Added product-specific discount configuration
- Integrated with existing price lists and inventory system

## Upcoming Features

### High Priority
- Complete promotions management module
  - Edit/Delete existing promotions
  - Apply promotions to sales transactions
  - Promotion usage analytics
- Enhance reporting for promotions
- Add bulk import/export for promotions

### Medium Priority
- Audit logging system
- User activity monitoring
- Advanced reporting for user actions
- Password policy configuration

### Low Priority
- Multi-branch support
- Mobile responsiveness improvements
- Additional export formats for reports

## Version
Current Version: 1.1.0 (Development)

## Last Updated
June 9, 2025
