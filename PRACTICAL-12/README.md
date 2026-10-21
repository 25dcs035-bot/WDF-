# Practical 12: Admin Event Management

## Setup and run

1. Start Apache and MySQL in XAMPP.
2. Confirm the `studenthub` database exists and your account has the admin role.
3. In phpMyAdmin, select `studenthub` and import this folder's `setup.sql`.
4. Copy the Practical 12 folder into the project folder inside `htdocs`, keeping
   its `posters` folder and `.htaccess` file.
5. Sign in as an admin and open
   `http://localhost/LAB/WDF%20project/PRACTICAL-12/events.php`.

Admins can add, view, edit, and delete events. Posters are optional; uploads
accept valid JPG, PNG, or WebP images up to 2 MB. The server checks the actual
image MIME type and content, generates a safe filename, and stores it in the
`posters` folder. Database operations use prepared MySQLi statements.
