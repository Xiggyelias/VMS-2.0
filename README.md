# Vehicle Registration System (PHP)

A PHP/MySQL web application for registering vehicles and managing access. It provides user self‑service registration, authorized driver management, and an admin console for oversight and reporting.

## Contents
- Overview
- Architecture & Directory Structure
- Features
- Security Model
- Data Model (Tables)
- Setup & Installation
- Configuration
- Usage Guide (User & Admin flows)
- API Endpoints (AJAX handlers)
- Logging & Error Handling
- Development Notes

---

## Overview
This app is a modular, plain‑PHP project using mysqli/PDO. It includes a lightweight security middleware for hardened headers, CSRF protection, session hygiene, and simple rate limiting. UI uses standard HTML/CSS with a shared assets bundle, plus small amounts of vanilla JavaScript for dynamic forms and AJAX.

## Architecture & Directory Structure
```
frontend/
  admin-dashboard.php          # Admin landing page (metrics, quick links)
  admin-login.php              # Admin login screen
  admin_reports.php            # Admin reports creation/listing
  assets/                      # Shared UI assets
    css/
      main.css
      styles.css
    js/
      main.js
    images/
      AULogo.png
  config/
    app.php                    # Global app constants (paths/URLs)
    database.php               # DB connection (PDO + legacy mysqli helpers)
    security.php               # Security configuration (headers, session, CSRF)
  database/                    # SQL migration helpers
    create_password_reset_tokens.sql
    create_reports_table.sql
    create_search_logs_table.sql
    setup_admin.sql
    setup_password_reset.sql
  includes/
    init.php                   # Global bootstrap (loads configs, functions, handlers)
    middleware/
      security.php             # SecurityMiddleware (headers, CSRF, rate limiting)
    functions/
      auth.php                 # Auth helpers (login/logout/session checks)
      utilities.php            # Common utilities (logging, redirects, etc.)
      vehicle.php              # Vehicle helpers (search/scan info)
  logs/
    error.log
    audit.log
  views/                       # (Optional) View templates
  *.php                        # Page controllers/views

README.md                      # This file
```

Notes:
- Always include `includes/init.php` and then `includes/middleware/security.php` and call `SecurityMiddleware::initialize();` at the top of PHP entry points.
- Do not call `session_start()` directly in pages that initialize the middleware; the middleware starts the session when needed.

## Features
- Registration flow for Students/Staff/Guests with dynamic form fields
- Multiple vehicles per owner (policy: Students limited to 1 active at a time)
- Authorized drivers per vehicle
- User dashboard (vehicles, authorized drivers, quick actions)
- Admin dashboard (owners, vehicles, reports, disk numbers)
- Reports module (admin) with create/list/delete
- Password reset (email token)
- Audit logging and hardened security headers

## Security Model
- `SecurityMiddleware` applies:
  - Strict security headers (X-Frame-Options, CSP, etc.)
  - Session security (cookie flags, lifetime, SameSite)
  - CSRF protection via `SecurityMiddleware::generateCSRFToken()` and verification for POSTs (with route exemptions)
  - Lightweight rate limiting (login/api bursts)
- Use `requireAuth()` for user‑only pages and `requireAdmin()` for admin‑only pages.
- CSRF: Exemptions include public login/reset routes and a few form endpoints (see `frontend/includes/middleware/security.php`). All other POSTs must include a token (`_token` in body or `X-CSRF-Token` header).

## Data Model (Tables)
Key tables used by the application (names inferred from queries):
- `applicants`
  - `applicant_id` (PK), `studentRegNo`, `staffsRegNo`, `fullName`, `password`, `phone`, `Email`, `college`, `idNumber`, `licenseNumber`, `licenseClass`, `licenseDate`, `registrantType`, `last_login`, `registration_date`
- `vehicles`
  - `vehicle_id` (PK), `applicant_id` (FK), `regNumber`, `make`, `owner`, `address`, `PlateNumber`, `status` ('active'|'inactive'), `diskNumber` (optional), `registration_date`, `last_updated`
- `authorized_driver`
  - `Id` (PK), `vehicle_id` (FK), `fullname`, `licenseNumber`, `contact`, `applicant_id` (optional legacy)
- `notifications`
  - `id`, `type`, `message`, `created_at`, `is_read`
- `admin`
  - `id`, `username`, `password`, `email`, `created_at`
- `admin_reports`
  - `id`, `title`, `description`, `category`, `report_date`, `file_path`, `admin_id`, `created_at`
- `password_reset_tokens`
  - `id`, `user_id`, `token`, `expires_at`

SQL helpers live under `frontend/database/`.

## Setup & Installation
1) Requirements
- PHP 8.x (XAMPP recommended), MySQL 8.x
- Composer (for PHPMailer)

2) Clone / Copy
- Place the project at: `C:\xampp\htdocs\system`

3) Install PHP dependencies (PHPMailer)
```
cd C:\xampp\htdocs\system\frontend
composer install
```

4) Create database and tables
- Create a MySQL database named `vehicleregistrationsystem`.
- Run the SQL scripts in `frontend/database/`:
  - `create_password_reset_tokens.sql`
  - `create_reports_table.sql`
  - `create_search_logs_table.sql`
  - `setup_password_reset.sql`
  - `setup_admin.sql` (creates `admin` and a default admin user)
- Ensure `applicants`, `vehicles`, `authorized_driver`, `notifications` exist (create if missing).

5) Configure
- `frontend/config/database.php`: update `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME` to match your MySQL.
- `frontend/config/security.php`: review `SESSION_SECURITY` (set `secure` to `false` for local HTTP) and CSP.
- Email: update SMTP settings in `frontend/forgot_password.php` (host, username, app password, from address).

6) Run
- Start Apache and MySQL in XAMPP.
- Visit `http://localhost/system/frontend/login.php` for users.
- Visit `http://localhost/system/frontend/admin-login.php` for admins.

## Configuration
- Paths and URLs are defined in `frontend/config/app.php`.
- Database connections:
  - `getDatabaseConnection()` (PDO) for new code
  - `getLegacyDatabaseConnection()` (mysqli) for existing pages
- Common assets helper: `includeCommonAssets()` from `includes/init.php` injects shared CSS/JS.

## Usage Guide
### Registration (User)
- Page: `frontend/registration-form.php`
- JS dynamically shows fields for student/staff/guest and builds `vehicles` JSON in `FormData`.
- Submits to `frontend/submit_registration.php`, which:
  - Hashes the password (`password_hash`)
  - Inserts an `applicants` row
  - Deactivates existing active vehicles (policy) and inserts new `vehicles`
  - Inserts `authorized_driver` rows for each driver
  - Commits transactions and redirects to `login.php?registration=success`

### Login / Dashboard (User)
- `frontend/login.php` → sets session; `includes/functions/auth.php` manages session keys.
- `frontend/user-dashboard.php` shows:
  - Owner info (College hidden for `guest`)
  - Vehicles table with Add/Edit/Delete (AJAX to `vehicle_operations.php`)
  - Authorized Drivers table with Add/Edit/Delete (AJAX to `driver_operations.php`)

### Admin Console
- `frontend/admin-dashboard.php`: KPIs, navigation.
- `frontend/owner-list.php`: paginated owners with View/Edit/Delete
  - Delete posts to `frontend/delete_user.php` (hard delete of `applicants`)
- `frontend/owner-details.php`: owner summary + vehicles and driver counts
- `frontend/vehicle-list.php` and `frontend/vehicle-details.php`: vehicle management, list authorized drivers
- `frontend/admin_reports.php`: create/list/delete reports (AJAX to `delete_report.php`)
- `frontend/manage-disk-numbers.php`: manage disk number assignments

### Password Reset
- `frontend/forgot_password.php` → `send-reset.php` generates token in `password_reset_tokens` and emails a link.
- `frontend/reset-password.php` + `frontend/process-reset.php` validate token and update password.

## API Endpoints (AJAX handlers)
- `frontend/vehicle_operations.php`
  - `POST action=add|edit|delete` with fields: `make`, `regNumber`, `vehicle_id` (edit/delete)
  - CSRF via `X-CSRF-Token`
- `frontend/driver_operations.php`
  - `POST action=add|edit|delete` with `fullname`, `licenseNumber`, `contact`, `driver_id` (edit/delete)
  - CSRF via `X-CSRF-Token`
- `frontend/delete_user.php`
  - `POST user_id` (admin‑only)
- `frontend/get_notifications.php` / `frontend/mark_notification_read.php`
  - Fetch and mark alerts

## Logging & Error Handling
- Errors logged to `frontend/logs/error.log` per `config/security.php`.
- Security/audit events appended to `frontend/logs/audit.log` by `SecurityMiddleware::logSecurityEvent()`.
- `includes/init.php` wires custom error/exception handlers; in development it can print friendly diagnostics.

## Development Notes
- Sessions: Use the middleware. If you must start manually, guard with:
  ```php
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  ```
- CSRF: Add `<input type="hidden" name="_token" value="<?= htmlspecialchars(SecurityMiddleware::generateCSRFToken()) ?>">` in forms or send `X-CSRF-Token` header.
- Database: Prefer the PDO helper; mysqli helper exists for legacy endpoints.
- Students are limited to one active vehicle; adding a new one deactivates the prior active vehicle.
- Authorized drivers are linked to `vehicles.vehicle_id`. Some legacy code also stores `authorized_driver.applicant_id`; the dashboard queries support both.

## License
Internal/educational project. Add your preferred license here.
