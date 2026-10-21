# Practical 14: Admin Dashboard

## Setup and run

1. Start Apache and MySQL in XAMPP.
2. Ensure the Student Hub `studenthub` database has the tables from Practicals
   9–12 (`users`, `students`, and `managed_events`).
3. Log in with an admin account and open
   `http://localhost/LAB/WDF%20project/PRACTICAL-14/index.php`.

The dashboard is restricted to admin users. It shows total users, admin and
student account counts, student-record and event counts, and the eight latest
user registrations. Navigation and management links are rendered according to
the current role.
