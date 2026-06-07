# VAR Room — XAMPP Setup Guide

## Prerequisites
- XAMPP installed (Windows / macOS / Linux)
- PHP >= 8.1
- MySQL >= 5.7

---

## Step 1 — Place the Project

1. Copy the entire `var-room/` folder into:
   - **Windows**: `C:\xampp\htdocs\var-room\`
   - **macOS**:   `/Applications/XAMPP/htdocs/var-room/`
   - **Linux**:   `/opt/lampp/htdocs/var-room/`

---

## Step 2 — Start XAMPP Services

1. Open **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both status lights should turn green.

---

## Step 3 — Create the Database

1. Open your browser and go to:
   `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar.
3. Name the database **`var_room`**, set collation to `utf8mb4_unicode_ci`, click **Create**.
4. Select the new `var_room` database.
5. Click the **SQL** tab at the top.
6. Open `var-room/database.sql` in any text editor.
7. Copy the **entire** contents and paste into the SQL tab.
8. Click **Go**.
9. You should see all 4 tables (`users`, `match_reviews`, `votes`, `comments`) and 2 views created.

---

## Step 4 — Verify Config

Open `var-room/config/db.php` and confirm:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'var_room');
define('DB_USER', 'root');   // XAMPP default
define('DB_PASS', '');       // XAMPP default (empty password)
```

If you set a MySQL password in XAMPP, update `DB_PASS` accordingly.

---

## Step 5 — Set Folder Permissions

The uploads folder needs to be writable:

- **Windows**: Usually automatic (XAMPP runs as admin).
- **macOS/Linux**:
  ```bash
  chmod -R 755 /path/to/htdocs/var-room/uploads/
  ```

---

## Step 6 — Visit the Site

Open your browser:

```
http://localhost/var-room/index.php
```

### Default Admin Login
| Field    | Value             |
|----------|-------------------|
| Email    | admin@varroom.com |
| Password | Admin@123         |

> ⚠️ **Change the admin password immediately** after first login!

---

## Common Errors & Fixes

### "Database connection failed"
- Make sure MySQL is running in XAMPP.
- Double-check `DB_NAME`, `DB_USER`, `DB_PASS` in `config/db.php`.

### "Uploads not saving"
- Check that `uploads/controversies/` exists and is writable.
- On Linux/macOS run: `chmod 755 uploads/controversies/`

### "Call to undefined function db()"
- You forgot to `require_once` the config files at the top of your PHP file.
  Each page needs at least:
  ```php
  require_once __DIR__ . '/config/db.php';
  require_once __DIR__ . '/config/session.php';
  require_once __DIR__ . '/config/constants.php';
  require_once __DIR__ . '/includes/functions.php';
  ```

### "403 Forbidden"
- Apache mod_rewrite may not be enabled. Open `httpd.conf` and ensure:
  `LoadModule rewrite_module modules/mod_rewrite.so` is uncommented.

### PHP version errors (`mixed`, `|false` types)
- These require PHP 8.0+. In XAMPP, ensure you have PHP >= 8.1 selected.

---

## Folder Structure

```
var-room/
├── config/
│   ├── db.php           ← Database PDO connection
│   ├── session.php      ← Session / auth helpers
│   └── constants.php    ← App-wide constants
├── includes/
│   ├── functions.php    ← Utility functions
│   ├── header.php       ← HTML <head> + nav (Module 2)
│   └── footer.php       ← HTML footer (Module 2)
├── auth/
│   ├── login.php        ← Login page (Module 3)
│   ├── register.php     ← Register page (Module 3)
│   └── logout.php       ← Logout handler (Module 3)
├── ajax/
│   ├── vote.php         ← Vote handler (Module 5)
│   └── comment.php      ← Comment handler (Module 6)
├── admin/
│   ├── index.php        ← Admin dashboard (Module 8)
│   └── moderate.php     ← Approve/reject reviews (Module 8)
├── uploads/
│   └── controversies/   ← User-uploaded images (writable)
├── assets/
│   ├── css/
│   │   └── style.css    ← Global styles (Module 2)
│   ├── js/
│   │   └── main.js      ← Global JavaScript (Module 2)
│   └── images/
│       └── logo.svg
├── index.php            ← Homepage (Module 4)
├── review.php           ← Single review page (Module 5)
├── upload.php           ← Upload form (Module 4)
├── database.sql         ← ← ← YOU ARE HERE
└── SETUP.md             ← ← ← YOU ARE HERE
```
