# Yarahman Biryani Shop Manager — Production Deployment Guide
**Host:** 7digit.in cPanel  |  **DB:** u777110831_briyani_shop  |  **PHP:** 7.2.34  |  **MariaDB:** 11.8.6

---

## Pre-Flight Checklist

Before starting, confirm you have:
- [ ] cPanel login credentials for 7digit.in
- [ ] Your MySQL/MariaDB database password (not in this guide for security)
- [ ] FTP access OR cPanel File Manager access
- [ ] A local backup of the existing live files (download first!)
- [ ] `yarahman_production_v2.0.zip` (this package)

---

## Step 1 — Back Up the Live Site

1. Log in to **cPanel → File Manager**
2. Navigate to `public_html/yarahman/` (or wherever the app currently lives)
3. Select all files → **Compress** → save as `yarahman_backup_YYYYMMDD.zip`
4. Download this backup to your computer before continuing

---

## Step 2 — Back Up the Database

1. In cPanel → **phpMyAdmin**
2. Select `u777110831_briyani_shop`
3. Click **Export** → Quick → Format: SQL → **Go**
4. Save the `.sql` file to your computer

---

## Step 3 — Run Database Migrations

These SQL files must be run **before** uploading the new code.

### Migration 1 — Login Rate Limiting Table

1. In cPanel → phpMyAdmin → select `u777110831_briyani_shop`
2. Click the **SQL** tab
3. Paste the contents of `install/migration_001_login_attempts.sql`
4. Click **Go**
5. You should see: `CREATE TABLE` succeeded (or "table already exists" if re-running — safe)

### Migration 2 — Performance Indexes

1. Still in phpMyAdmin → SQL tab
2. Paste the contents of `install/migration_002_production_indexes.sql`
3. Click **Go**
4. You should see multiple `CREATE INDEX` statements succeed

---

## Step 4 — Create the .env File

The new `config/db.php` reads credentials from a `.env` file.  
You must create this file on the server **before** deploying.

1. In cPanel → File Manager → navigate to the app's root folder
   (e.g. `public_html/yarahman/`)
2. Click **New File** → name it `.env`
3. Edit `.env` and paste:

```
DB_HOST=localhost
DB_NAME=u777110831_briyani_shop
DB_USER=u777110831_admin
DB_PASS=YOUR_ACTUAL_DATABASE_PASSWORD
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Kolkata
```

4. Replace `YOUR_ACTUAL_DATABASE_PASSWORD` with your real password
5. Save the file
6. **IMPORTANT:** Verify `.htaccess` contains the FilesMatch block that denies HTTP access to `.env`
   (it does — already included in the production `.htaccess`)

---

## Step 5 — Upload Production Files

### Option A — via cPanel File Manager (Recommended)

1. In File Manager, navigate to your app root
2. Click **Upload** → select `yarahman_production_v2.0.zip`
3. After upload, right-click the ZIP → **Extract** → extract to the app folder
4. The ZIP contains a `yarahman_production/` subfolder — move those files **into** your app root

### Option B — via FTP (FileZilla)

1. Connect to 7digit.in with your FTP credentials
2. Upload all files from the `yarahman_production/` folder into the live app root
3. Make sure hidden files (`.htaccess`, `.env`) are visible and uploaded

### Files to Upload

```
.htaccess                   ← REPLACE existing (adds security headers)
.env                        ← ALREADY CREATED in Step 4
favicon.svg                 ← NEW
index.php                   ← REPLACE
logout.php                  ← REPLACE
api/get_dashboard.php       ← REPLACE
api/get_entry.php           ← NEW
api/get_report.php          ← REPLACE
api/save_entry.php          ← REPLACE
api/save_online.php         ← REPLACE
api/switch_branch.php       ← REPLACE
assets/css/style.css        ← REPLACE
assets/js/app.js            ← REPLACE
auth/login.php              ← REPLACE
auth/session.php            ← REPLACE
config/constants.php        ← REPLACE
config/db.php               ← REPLACE
includes/nav.php            ← REPLACE (or NEW if not existing)
includes/branch_bar.php     ← REPLACE (or NEW if not existing)
pages/daily_entry.php       ← REPLACE
pages/dashboard.php         ← REPLACE
pages/monthly_report.php    ← REPLACE
pages/online_sales.php      ← REPLACE
pages/settings.php          ← REPLACE
pages/weekly_report.php     ← REPLACE
```

**Do NOT delete:** `assets/images/` (background photos), `vendor/` (if using Composer packages), any other existing files not listed above.

---

## Step 6 — Create Logs Directory

The new `config/db.php` writes PHP error logs to `logs/php_errors.log`.

1. In File Manager → navigate to app root
2. Create a new folder named `logs`
3. Set its permissions to **750** (owner read/write/execute, group read/execute, others none)
4. Create an empty `.htaccess` inside `logs/` with this content to block web access:

```
Deny from all
```

---

## Step 7 — Verify File Permissions

| File / Folder        | Permission | Notes                                 |
|----------------------|-----------|---------------------------------------|
| `.env`               | 640       | Owner read/write, group read, no other |
| `logs/`              | 750       | Owner rwx, group rx                   |
| `logs/php_errors.log`| 640       | Created automatically on first error  |
| PHP files (*.php)    | 644       | Standard web file permission          |
| `.htaccess`          | 644       | Apache reads this                     |

In File Manager: right-click → **Permissions** → set the values above.

---

## Step 8 — Test the Deployment

Open the site in a browser and verify each step:

### Login & Auth
- [ ] Login page loads without PHP errors
- [ ] Login with admin/admin123 works (you should be prompted to change this!)
- [ ] After login, redirects to dashboard
- [ ] Logout clears session, redirects to login page
- [ ] After logout, pressing browser Back button does NOT show dashboard

### Rate Limiting
- [ ] Enter wrong password 5 times → should see lockout message (wait 15 min OR clear login_attempts table)

### Dashboard
- [ ] KPI cards load with real data
- [ ] Bar chart (Last 7 Days) renders
- [ ] Pie chart (Payment Split) renders

### Daily Entry
- [ ] Today's existing entries load on page open
- [ ] Changing quantities updates amounts in real-time
- [ ] Save button works — data persists after page refresh
- [ ] Staff user cannot change the date away from today

### Reports
- [ ] Weekly report loads and shows table + chart
- [ ] Monthly report loads and shows table + trend line

### Online Sales
- [ ] Adding a Swiggy/Zomato entry saves and appears in the list

### Settings (owner/admin)
- [ ] Item rates can be updated
- [ ] New item can be added
- [ ] Password change works (test with a new user first)

### Security Checks
- [ ] Visiting `yourdomain.com/yarahman/.env` → returns **403 Forbidden** ✓
- [ ] Visiting `yourdomain.com/yarahman/install/` → returns **403 Forbidden** ✓
- [ ] Visiting `yourdomain.com/yarahman/vendor/` → returns **403 Forbidden** ✓
- [ ] Browser DevTools → Network tab: response headers include `X-Frame-Options: SAMEORIGIN` ✓
- [ ] Response headers include `X-Content-Type-Options: nosniff` ✓

---

## Step 9 — Change Default Passwords

**This is critical for production security.**

1. Log in as `admin`
2. Go to **Settings → Users**
3. Change the `admin` password to a strong password (min 8 chars, mixed case + number)
4. Change or delete the `staff1` default user
5. Also update your `.env` `DB_PASS` if you haven't already

---

## Step 10 — Ongoing Maintenance

| Task | Frequency | How |
|------|-----------|-----|
| Take DB backup | Weekly | cPanel → phpMyAdmin → Export |
| Check error log | Monthly | File Manager → `logs/php_errors.log` |
| Clear old login attempts | Monthly | `DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 30 DAY;` in phpMyAdmin |
| Renew SSL certificate | Yearly | cPanel → SSL/TLS → Let's Encrypt |

---

## Rollback Procedure

If something goes wrong after deployment:

1. In cPanel File Manager → upload and extract your `yarahman_backup_YYYYMMDD.zip`
2. In phpMyAdmin → import the backup `.sql` file
3. Your site will be back to the state before deployment

---

## What Was Fixed (Summary)

| Category | Issues Fixed |
|----------|-------------|
| **CRITICAL Security** | CSRF protection on all forms & APIs, login rate limiting, session fixation prevention, open redirect fix, DB credentials in .env |
| **HIGH Security** | Generic error messages (no more schema leaks), input sanitization, strict date validation, staff permission enforcement |
| **Performance** | N+1 → single aggregated SQL query on dashboard (-14 DB calls), JOIN replaces correlated subqueries, 6 production indexes added |
| **Code Quality** | Duplicate CSS removed, PHP 7.2 compatible throughout, error display disabled in production, CSP + security headers via .htaccess |
| **UI/UX** | Removed non-functional "Remember Me", fixed mobile viewport (user-scalable=no removed), lazy-load background images, aria labels on all interactive elements |
| **Branding** | Fixed typo: "Briyani" → "Biryani", new favicon.svg |

---

*Deployment guide generated for Yarahman Production v2.0 — May 2026*
