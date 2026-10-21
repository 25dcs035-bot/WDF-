# Practical 9: Secure User Registration with MySQLi

## Aim

Create a user registration form that validates input in the browser and on the
server, prevents duplicate usernames and email addresses, and stores a secure
password hash in MySQL.

## Files

- `Register.html` — registration form and browser-side validation.
- `Register.php` — server-side validation, duplicate checks, password hashing,
  and MySQL insertion using prepared MySQLi statements.
- `db.php` — MySQLi connection settings.
- `setup.sql` — creates the `studenthub` database and `users` table.

## Setup and run with XAMPP

1. Start Apache and MySQL from the XAMPP Control Panel.
2. Open phpMyAdmin, select **Import**, and import `setup.sql`.
3. Put the whole Student Hub project folder inside the XAMPP `htdocs` folder.
   Keep `PRACTICAL-9` beside the existing site pages and `logo.jpg` so the
   shared navigation and logo links work.
4. If your MySQL settings differ from the defaults, update the values in
   `db.php` or set the `DB_HOST`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME`
   environment variables.
5. Open
   `http://localhost/WDF%20project/PRACTICAL-9/Register.html` in a browser and
   submit the form. If you named the project folder differently, use that
   folder name in the URL.

The default connection is `localhost`, MySQL user `root`, an empty password,
and database `studenthub`. Keep database credentials out of publicly accessible
code when deploying a real application.

## Security features

- HTML constraints and JavaScript validate form data in the browser.
- PHP independently validates every submitted field.
- Prepared statements prevent user input from being interpreted as SQL.
- Username and email are checked before insertion and protected by unique
  database indexes to prevent duplicates, including concurrent submissions.
- `password_hash()` stores a one-way password hash; the plain-text password is
  never inserted into the database.
- Output is HTML-escaped and database errors are logged rather than exposed.
