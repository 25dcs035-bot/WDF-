# Practical 11: Student Management

## Setup and run

1. Start Apache and MySQL in XAMPP.
2. Set up the `studenthub` database and users table using Practical 9, then
   import Practical 10's `setup.sql` if you have not already added roles.
3. If you already have the `students` table shown in phpMyAdmin with
   `student_id` and `year` columns, replace the `PRACTICAL-11/students.php`
   file inside `htdocs` with this updated version, then import
   `fix_existing_table.sql` in phpMyAdmin. It only makes the unused
   `username` and `password_hash` fields optional; it does not rename or delete
   your existing columns or records.
4. If you do not have a `students` table, import this folder's `setup.sql`.
5. Sign in as an admin and open
   `http://localhost/LAB/WDF%20project/PRACTICAL-11/students.php`.

To give a registered account admin access, run this in phpMyAdmin with that
account's email:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

This page supports adding, listing, editing, deleting, searching, and filtering
student records. It is restricted to admin accounts and uses prepared MySQLi
statements for its database operations. Roll numbers and emails must be unique.
