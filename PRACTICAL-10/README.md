# Practical 10: Secure Login and Logout

## Setup

1. Start Apache and MySQL in XAMPP.
2. Import `PRACTICAL-9/setup.sql` first if the `studenthub.users` table does not
   exist yet.
3. In phpMyAdmin, import this folder's `setup.sql` once. It adds student roles
   and the remember-me token table.
4. Open `http://localhost/LAB/WDF%20project/PRACTICAL-10/login.php`.

The login uses email and password from Practical 9. It verifies passwords with
`password_verify()`, regenerates the session ID, expires idle sessions after
30 minutes, and protects both dashboards. The optional remember-me checkbox
stores only a hashed random token in MySQL and uses an HttpOnly cookie.

To give a registered user admin access, run this in phpMyAdmin after replacing
the email with that user's registered email:

```sql
UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
```

Use a non-admin account to test the student dashboard. Admin accounts are sent
to `admin_dashboard.php`; students are sent to `dashboard.php`. Logout is a
CSRF-protected POST request.
