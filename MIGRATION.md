# Database Migration Reference
## Project: AI Document Analyzer (nupthasa_ai_api → ai_auth)
**Date:** 2026-05-19  
**Prepared by:** Asad Ali  

---

## 1. Background

The project originally ran on a database called `nupthasa_ai_api` (MySQL 8.0.46 on shared hosting). A new version of the admin console webapp was built locally on MariaDB 12.2.2 with an expanded schema called `ai_auth`.

The new schema adds:
- License management (generate, assign, revoke license keys)
- License activity audit log
- OTP-based password reset flow
- `avatar` column on users (for Google OAuth profile pictures)

The migration preserves all 9 live users with their existing passwords. No user needs to re-register or reset their password.

---

## 2. Old Schema: `nupthasa_ai_api`

**Server:** Shared hosting, MySQL 8.0.46  
**Charset:** utf8mb4_0900_ai_ci  
**Tables:** 2

### Table: `users`

```sql
CREATE TABLE `users` (
  `id`            bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name`          varchar(190) DEFAULT NULL,
  `role`          enum('user','admin') DEFAULT 'user',
  `status`        enum('active','disabled') DEFAULT 'active',
  `created_at`    timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Table: `refresh_tokens`

```sql
CREATE TABLE `refresh_tokens` (
  `id`          bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     bigint UNSIGNED NOT NULL,
  `token_hash`  varchar(255) NOT NULL,
  `expires_at`  datetime NOT NULL,
  `created_at`  timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `refresh_tokens_ibfk_1`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

### Live Users (as of 2026-05-19)

| id | email | name | role | status | registered |
|----|-------|------|------|--------|------------|
| 11 | f.m.alharbiy@gmail.com | Admin | admin | active | 2025-12-08 |
| 12 | faesl67@hotmail.com | Faisal Alharbi | user | active | 2025-12-08 |
| 13 | s.salfaraj@outlook.com | Sultan Alfaraj | user | active | 2025-12-08 |
| 15 | test@test.com | Test User | user | active | 2025-12-25 |
| 17 | eng.mohd.a@gmail.com | Mohammed Alasmari | user | active | 2026-01-01 |
| 18 | Cubetechn@outlook.com | Cube Tech | admin | active | 2026-01-31 |
| 19 | Laalmadi@pnu.edu.sa | Layla AL-Madi | user | active | 2026-02-01 |
| 20 | aalmusamih@gmail.com | Abdulrahman fahad | user | active | 2026-02-10 |
| 21 | eng.ma.426@gmail.com | Mana alahmari | user | active | 2026-03-15 |

> Password hashes use bcrypt cost factor `$2y$12$`. All are compatible with the new schema.

---

## 3. New Schema: `ai_auth`

**Server:** Local dev, MariaDB 12.2.2 (production hosting will use its MySQL version)  
**Charset:** utf8mb4_unicode_ci  
**Tables:** 5

### Table: `users` (MODIFIED — added `avatar`)

```sql
CREATE TABLE `users` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name`          varchar(190) DEFAULT NULL,
  `avatar`        varchar(255) DEFAULT NULL,           -- NEW: Google OAuth profile photo URL
  `role`          enum('user','admin') DEFAULT 'user',
  `status`        enum('active','disabled') DEFAULT 'active',
  `created_at`    timestamp NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login`    timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `refresh_tokens` (UNCHANGED — same structure)

```sql
CREATE TABLE `refresh_tokens` (
  `id`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     bigint(20) UNSIGNED NOT NULL,
  `token_hash`  varchar(255) NOT NULL,
  `expires_at`  datetime NOT NULL,
  `created_at`  timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rt_user_fk`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `licenses` (NEW)

```sql
CREATE TABLE `licenses` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`           varchar(100) NOT NULL,
  `type`          enum('standard','trial') DEFAULT 'standard',
  `status`        enum('active','expired','revoked') DEFAULT 'active',
  `assigned_to`   varchar(190) DEFAULT NULL,           -- email of assigned user
  `assigned_name` varchar(190) DEFAULT NULL,           -- display name
  `notes`         text DEFAULT NULL,
  `expires_at`    datetime NOT NULL,
  `created_at`    timestamp NULL DEFAULT current_timestamp(),
  `updated_at`    timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `license_activity` (NEW)

```sql
CREATE TABLE `license_activity` (
  `id`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`      varchar(100) NOT NULL,     -- e.g. 'License created', 'Bulk revoke'
  `license_key` varchar(100) NOT NULL,
  `by_user`     varchar(190) NOT NULL,     -- email of admin who performed action
  `detail`      text DEFAULT NULL,
  `created_at`  timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `password_resets` (NEW)

```sql
CREATE TABLE `password_resets` (
  `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`            varchar(190) NOT NULL,
  `otp_hash`         varchar(255) NOT NULL,       -- bcrypt of 6-digit OTP
  `reset_token_hash` varchar(255) DEFAULT NULL,   -- token issued after OTP verified
  `expires_at`       datetime NOT NULL,
  `used`             tinyint(1) DEFAULT 0,
  `attempts`         int(11) DEFAULT 0,
  `ip_address`       varchar(45) DEFAULT NULL,
  `created_at`       timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Schema Diff Summary

| Change | Old DB | New DB |
|--------|--------|--------|
| `users.avatar` | missing | `varchar(255) NULL` |
| `licenses` table | missing | added |
| `license_activity` table | missing | added |
| `password_resets` table | missing | added |
| DB name | `nupthasa_ai_api` | `ai_auth` |
| Collation | utf8mb4_0900_ai_ci | utf8mb4_unicode_ci |
| bcrypt cost | `$2y$12$` | `$2y$10$` (new accounts only) |

> Collation change is safe — both are full utf8mb4. Old hashes (`$2y$12$`) continue to verify fine in the new DB; bcrypt doesn't care about collation.

---

## 5. ID Conflict Note

Old live DB had users at IDs 12 and 13. Local dev DB also used IDs 12 and 13 for test accounts. The **live users win** — their IDs are preserved. The local test data is throwaway and was never meant for production.

| ID | Live DB (KEEP) | Local Dev DB (DISCARD) |
|----|----------------|------------------------|
| 12 | faesl67@hotmail.com (Faisal Alharbi) | asadaly.developer@gmail.com |
| 13 | s.salfaraj@outlook.com (Sultan Alfaraj) | arif.ismail635@gmail.com |

---

## 6. Migration Strategy

**Chosen approach: Start fresh with `ai_auth` schema, import live users into it.**

Rejected alternative — "extend old DB": would require the exact same ALTER TABLE + CREATE TABLE work, while also carrying over the old DB name. No benefit, extra mess.

**What gets migrated:**
- All 9 `users` rows (IDs, emails, bcrypt hashes, names, roles, timestamps)

**What does NOT get migrated:**
- `refresh_tokens` — all are expired (30-day TTL, oldest from Dec 2025). Users just re-login once.
- No other data — old DB had no licenses or password resets.

---

## 7. Step-by-Step Migration Instructions

### Prerequisites
- Access to phpMyAdmin on your hosting control panel (cPanel / Plesk / DirectAdmin)
- The file `migrate.sql` from this repo
- Your hosting DB credentials (host, username, password)

---

### Step 1 — Create `ai_auth` database on hosting

1. Open phpMyAdmin on your hosting
2. Click **New** in the left sidebar
3. Database name: `ai_auth`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**

---

### Step 2 — Run migrate.sql

1. In phpMyAdmin, select the `ai_auth` database
2. Click the **SQL** tab
3. Open `migrate.sql` from this repo, copy all contents
4. Paste into the SQL box
5. Click **Go**

> `migrate.sql` creates all 5 tables and inserts the 9 live users. It uses `INSERT IGNORE` so it's safe to run again if something goes wrong.

**Expected result after running:**
```
users: 9 rows (IDs 11,12,13,15,17,18,19,20,21)
licenses: 0 rows
license_activity: 0 rows
password_resets: 0 rows
refresh_tokens: 0 rows
AUTO_INCREMENT on users = 22
```

---

### Step 3 — Upload the new webapp files

Build the React admin console and upload to hosting.

```bash
# In admin_console_shadcn/
npm run build:prod
```

This builds the frontend and copies `dist/` into `server-php-auth/public/`. Then upload the entire `server-php-auth/` directory to your hosting via FTP/SFTP or cPanel File Manager.

---

### Step 4 — Create config.php on hosting

On the hosting server, create `server-php-auth/config.php` (do NOT commit this file — it contains secrets).

```php
<?php
return [
  'db' => [
    'host'    => 'localhost',       // usually localhost on shared hosting
    'port'    => 3306,              // default MySQL port (NOT 3600 — that's local dev only)
    'name'    => 'ai_auth',
    'user'    => 'YOUR_DB_USER',    // from hosting DB panel
    'pass'    => 'YOUR_DB_PASS',
    'charset' => 'utf8mb4'
  ],
  'jwt' => [
    'secret'              => 'CHANGE_TO_RANDOM_64_CHAR_STRING',
    'alg'                 => 'HS256',
    'issuer'              => 'ai-auth-api',
    'access_ttl_seconds'  => 900
  ],
  'refresh_secret'        => 'CHANGE_TO_ANOTHER_RANDOM_64_CHAR_STRING',
  'refresh_ttl_seconds'   => 2592000,
  'initial_admin_email'   => 'f.m.alharbiy@gmail.com',
  'seed_token'            => 'CHANGE_TO_RANDOM_STRING',
  'smtp' => [
    'host'       => 'mail.ssma.sa',
    'port'       => 465,
    'encryption' => 'ssl',
    'username'   => 'noreply@ssma.sa',
    'password'   => 'YOUR_SMTP_PASSWORD',
    'from'       => 'noreply@ssma.sa',
    'from_name'  => 'AI Document Analyzer',
  ],
];
```

> Generate fresh JWT secrets for production. Do not reuse local dev secrets from `config.php`.

---

### Step 5 — Verify the migration

After deploying:

1. **Log in as a migrated user** — try `f.m.alharbiy@gmail.com` (admin). If login works, bcrypt migration succeeded.
2. **Check user list in admin console** — should show all 9 original users.
3. **Register a new user** — new ID should be 22 or higher (confirms AUTO_INCREMENT is correct).
4. **Check licenses tab** — should show empty list with no errors.
5. **Test password reset flow** — request OTP for a test email, verify the email arrives.

---

### Step 6 — Keep old DB as backup

**Do NOT drop `nupthasa_ai_api` immediately.**

Wait 2–4 weeks. If no issues, then drop it from phpMyAdmin.

---

## 8. Rollback Plan

If the new deployment breaks for any reason:

1. Change hosting `config.php` → `db.name` back to `nupthasa_ai_api`
2. Redeploy old webapp files
3. Old DB is completely untouched — zero data loss

---

## 9. Files in this Repo

| File | Purpose |
|------|---------|
| `migrate.sql` | Run on hosting to create new schema + import live users |
| `config.php` | Local dev config — NOT committed (port 3600, local MariaDB) |
| `config.sample.php` | Template for creating production config |
| `admin_console_shadcn/nupthasa_ai_api.sql` | Full export of old live DB (permanent backup) |
| `admin_console_shadcn/ai_auth.sql` | Full export of new local dev DB (schema reference) |

---

## 10. What Changed in the PHP Backend (vs old version)

The old webapp only had `users` and `refresh_tokens`. The new PHP backend (`server-php-auth/src/`) adds:

| File | Route | Description |
|------|-------|-------------|
| `controllers/LicenseController.php` | `GET /api/licenses` | List all licenses |
| `controllers/LicenseController.php` | `POST /api/licenses` | Create license(s) |
| `controllers/LicenseController.php` | `PATCH /api/licenses/:id` | Edit a license |
| `controllers/LicenseController.php` | `DELETE /api/licenses/:id` | Delete a license |
| `controllers/PasswordResetController.php` | `POST /api/auth/forgot-password` | Send OTP email |
| `controllers/PasswordResetController.php` | `POST /api/auth/verify-otp` | Verify OTP, return reset token |
| `controllers/PasswordResetController.php` | `POST /api/auth/reset-password` | Set new password using reset token |
| `controllers/MeController.php` | `GET /api/me` | Get current user profile (includes avatar) |
| `controllers/AdminController.php` | `GET /api/admin/users` | List all users |

---

*Last updated: 2026-05-19*
