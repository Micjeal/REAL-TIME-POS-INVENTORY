MTECH UGANDA Database Setup Instructions
===============================

Follow these steps to set up your database:

1. Start XAMPP Control Panel
   - Launch XAMPP Control Panel
   - Start Apache and MySQL services
   - Ensure both are running (green status)

2. Import the database schema
   Method 1: Using phpMyAdmin
   - Open your browser and navigate to http://localhost/phpmyadmin/
   - Click on 'New' to create a new database named 'mtech_uganda'
   - Select the newly created database
   - Click on the 'Import' tab
   - Click 'Choose File' and select the 'database_setup.sql' file
   - Click 'Go' to import the schema

   Method 2: Using MySQL Command Line
   - Open command prompt
   - Navigate to MySQL bin directory: cd C:\xampp\mysql\bin
   - Run: mysql -u root < "C:\xampp\htdocs\MTECH UGANDA\database_setup.sql"

3. Verify the setup
   - In phpMyAdmin, check that 'mtech_uganda' database exists
   - Verify that all tables are created
   - Test the login using username: 'admin' and password: 'password'

Important: The database connection settings in config.php are:
- Host: localhost
- Database: mtech_uganda
- Username: root
- Password: (blank)

If you've set a password for your MySQL root user, update the DB_PASSWORD in config.php.
