# Practical 15: Integrated Student Hub Portal

## Local XAMPP deployment

Apache and MySQL must be running. This checkout can be served directly from
its current folder, without copying the project into `htdocs`.

1. Import `database/setup.sql` through phpMyAdmin for a fresh installation.
   Existing tables are preserved by `CREATE TABLE IF NOT EXISTS`; use the
   applicable practical migration if your earlier schema differs.
2. Confirm the connection settings in `PRACTICAL-9/db.php`. Defaults are
   `localhost`, `root`, an empty local password, and `studenthub`; optional
   `DB_HOST`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME` environment variables
   override them.
3. Add this virtual host to
   `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (adjust the project path if
   the checkout moves):

   ```apache
   <VirtualHost *:80>
       ServerName studenthub.local
       DocumentRoot "C:/Users/Jeel/Desktop/WDF project"
       <Directory "C:/Users/Jeel/Desktop/WDF project">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

4. Add `127.0.0.1 studenthub.local` to
   `C:\Windows\System32\drivers\etc\hosts` as Administrator.
5. Run `C:\xampp\apache\bin\httpd.exe -t`, restart Apache, and visit
   **http://studenthub.local/**.

The portal home is the root `index.php`. Its shared navigation presents public
pages to guests, the protected dashboard to students, and student/event/API
management tools to admins. Public Events uses Fetch to show records managed
in Practicals 12–13. The same gray/blue visual style and responsive navigation
are shared across the integrated pages.

## Security and verification

Registration stores password hashes. Login regenerates the session ID and
supports an idle timeout and hashed remember-me tokens. Protected actions use
role checks and CSRF tokens; database queries use prepared statements. Event
posters are size-limited and checked by their actual image MIME type. Browser
rendering of profile and event data uses text nodes rather than interpolating
database or JSON values into HTML.

After deployment, verify:

1. Portal, Home, About, Events, Contact, Student Profiles, Login, and Register
   links at desktop and mobile widths.
2. Registration, valid/invalid login, student/admin redirects, direct access
   while signed out, logout, and session timeout.
3. Student search/filter and student add/edit/delete with database
   verification.
4. Admin event add/edit/delete, poster file validation, and matching event
   rows in the public Events page.
5. Practical 13 Fetch add/update/delete without a page reload. Confirm guests
   cannot write to the API and invalid/missing CSRF tokens are rejected.
6. Admin dashboard totals against the database.

## GitHub submission

Git and a GitHub repository URL are not configured in this workspace, so a
remote repository cannot be created or pushed from here. After installing Git
and creating an empty GitHub repository, review `git status` and push with:

```powershell
git init
git add .
git commit -m "Complete Student Hub practicals 9-15"
git branch -M main
git remote add origin https://github.com/25dcs035-bot/WDF-.git
git push -u origin main
```

Replace the remote URL with your repository. Do not commit credentials,
personal student data, or user-uploaded posters.
