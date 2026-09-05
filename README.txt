CANTEEN MANAGEMENT SYSTEM - STEP 1
=====================================

Included:
1. MySQL database
2. Login/logout
3. Session authentication
4. Super Admin role
5. Role Management
6. User Management
7. Admin Dashboard
8. Responsive sidebar/topbar
9. Bootstrap 5 + Font Awesome
10. PDO prepared statements
11. Password hashing

REQUIREMENTS
------------
- XAMPP
- Apache
- MySQL
- PHP 8.0+ recommended

INSTALLATION
------------

1. Copy the folder "canteen_management_step1" into:

   C:\xampp\htdocs\

2. Start XAMPP:
   - Apache = Start
   - MySQL = Start

3. Open phpMyAdmin:

   http://localhost/phpmyadmin/

4. Click Import.

5. Select:

   database.sql

6. Click Go.

7. Open:

   http://localhost/canteen_management_step1/

DEFAULT LOGIN
-------------
Email:
admin@canteen.local

Password:
admin123

IMPORTANT:
----------
Change the default password after installation.

DATABASE CONFIGURATION
----------------------
If your MySQL has a different username/password, edit:

config/database.php

Default XAMPP:
username = root
password = empty

MAIN FILES
----------
index.php                  Login
logout.php                 Logout

admin/dashboard.php        Admin dashboard
admin/users.php            User management
admin/roles.php            Role management

config/database.php        PDO connection

includes/auth.php          Login protection
includes/sidebar.php       Admin sidebar
includes/topbar.php        Header
includes/footer.php        Footer
includes/functions.php     Common functions

NEXT DEVELOPMENT STEP
---------------------
After Step 1 is working, build:

Step 2 - Store Module
- Material Master
- Stock Management
- Stock Inward
- Stock Issue
- Low Stock Alert
- Purchase Request

Then:

Step 3 - Purchase Module
Step 4 - Kitchen Module
Step 5 - Canteen Module
Step 6 - Reports
Step 7 - Final Dashboard integration
