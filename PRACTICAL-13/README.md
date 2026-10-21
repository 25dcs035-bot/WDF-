# Practical 13: Events JSON API with Fetch

## Setup and run

1. Start Apache and MySQL in XAMPP.
2. Import `PRACTICAL-12/setup.sql` into `studenthub` if the `managed_events`
   table does not already exist.
3. Copy the `PRACTICAL-13` folder into the project folder under `htdocs`.
4. Log in with an admin account and open
   `http://localhost/LAB/WDF%20project/PRACTICAL-13/index.php`.

The page consumes `api/events.php` with Fetch. The API supports GET (list),
POST (add), PUT (update), and DELETE (delete), returns JSON, and uses prepared
MySQLi statements. Add/update/delete requests require an admin session and a
CSRF token. Changes appear without reloading the page.
