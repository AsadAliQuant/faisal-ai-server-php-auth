# Deployment Guide — server-php-auth
## AI Document Analyzer — Admin Console

**Live URL:** `https://ssma.sa/console`  
**Hosting:** GoDaddy shared hosting (cPanel)  
**Last deployed:** 2026-05-21  
**Deployed by:** Asad Ali

---

## Project Stack

| Layer | Tech |
|-------|------|
| Frontend | React 19 + TypeScript, Vite, shadcn/ui, Tailwind v4 |
| Backend | PHP (no framework), PDO, JWT auth |
| Database | MySQL 8.0.46 (GoDaddy shared hosting) |
| Email | PHPMailer via SMTP `mail.ssma.sa` |
| Monorepo | Turborepo (`admin_console_shadcn/`) |

---

## Hosting Details

| Setting | Value |
|---------|-------|
| cPanel DB name | `nupthasa_aiauth` |
| cPanel DB user | `nupthasa_aiauth` |
| DB host | `localhost` |
| SMTP host | `mail.ssma.sa:465` (SSL) |
| SMTP from | `noreply@ssma.sa` |
| Document root | `public_html/` (default — app lives in `public_html/console/`) |

Credentials are in `config.php` (not committed — keep this file private).

---

## Database Schema

5 tables in `nupthasa_aiauth`:

| Table | Purpose |
|-------|---------|
| `users` | Auth + role (user/admin), avatar for Google OAuth |
| `refresh_tokens` | 30-day JWT refresh tokens |
| `licenses` | License keys (standard/trial, active/expired/revoked) |
| `license_activity` | Audit log for license actions |
| `password_resets` | OTP-based password reset flow |

9 live users migrated from old DB `nupthasa_ai_api` (preserved bcrypt hashes, no password resets needed).

---

## What Changed in This Version (2026-05-21 upgrade)

Migrated from old DB `nupthasa_ai_api` → new DB `nupthasa_aiauth`.

**New features:**
- License management (generate, assign, revoke, bulk actions)
- License activity audit log
- OTP-based password reset via email
- Google OAuth avatar support (`avatar` column on users)
- Redesigned React admin console (shadcn/ui, Tailwind v4)

---

## Full Deployment Steps (What Was Done)

### Step 1 — Update Vite base path

Since the app is served from `/console` (not root), added `base: '/console/'` to Vite config:

**File:** `admin_console_shadcn/apps/web/vite.config.ts`
```ts
export default defineConfig({
  base: '/console/',   // ← added this
  plugins: [react(), tailwindcss()],
  ...
})
```

This ensures React asset paths compile as `/console/assets/...` instead of `/assets/...`.

---

### Step 2 — Build the React app

```powershell
cd C:\xampp\htdocs\server-php-auth\admin_console_shadcn
npm run build:prod
```

`build:prod` = `turbo build` + `xcopy` compiled output into `../public/`.  
After this, `public/index.html` and `public/assets/` contain the production build.

---

### Step 3 — Create the MySQL database on GoDaddy

1. cPanel → **MySQL Databases**
2. Created database: `nupthasa_aiauth`
3. Created user: `nupthasa_aiauth`
4. Assigned user to DB with **All Privileges**

---

### Step 4 — Import the database schema + users

Opened phpMyAdmin, selected `nupthasa_aiauth`, ran the SQL below directly in the **SQL tab** (did NOT use the raw `migrate.sql` file — it contains `USE \`ai_auth\`` which causes Access Denied on shared hosting).

**Key fix:** Remove the `USE` statement and reorder tables so `users` is created before `refresh_tokens` FK constraint.

Full SQL that was run:

```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(190) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','disabled') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rt_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `licenses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `type` enum('standard','trial') DEFAULT 'standard',
  `status` enum('active','expired','revoked') DEFAULT 'active',
  `assigned_to` varchar(190) DEFAULT NULL,
  `assigned_name` varchar(190) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `license_activity` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `by_user` varchar(190) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `reset_token_hash` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `users`
  (`id`, `email`, `password_hash`, `name`, `avatar`, `role`, `status`, `created_at`, `updated_at`, `last_login`)
VALUES
  (11, 'f.m.alharbiy@gmail.com',  '$2y$12$rpbCi2JNFfPNwN.hbeyxg.BdsmdlFR5F5ybXQ34zgoCPqOXEfCt3q', 'Admin',              NULL, 'admin', 'active', '2025-12-08 16:04:57', '2026-05-16 08:42:18', '2026-05-16 08:42:18'),
  (12, 'faesl67@hotmail.com',     '$2y$12$SSiOTCG9viUVos7DdtFFSeIcm7LvYc5WKmx7HU3gmV/gOjTW6urt6', 'Faisal Alharbi',     NULL, 'user',  'active', '2025-12-08 16:08:04', '2026-01-01 15:35:37', '2026-01-01 15:35:37'),
  (13, 's.salfaraj@outlook.com',  '$2y$12$EVivCp.s34z743FPvCp1HugcKMK6gaZH.H7KKSUZsgK6KmiZMeaz6', 'Sultan Alfaraj',     NULL, 'user',  'active', '2025-12-08 16:08:59', '2026-04-14 06:20:07', '2026-04-14 06:20:07'),
  (15, 'test@test.com',           '$2y$12$9SfBZlKhIoBm4Qe8JW2IQe7Bos4FwOvmXuEX/98Cvu4RGVx7pQgam', 'Test User',          NULL, 'user',  'active', '2025-12-25 18:34:43', '2026-01-08 16:52:23', '2026-01-08 16:52:23'),
  (17, 'eng.mohd.a@gmail.com',    '$2y$12$sNmvvpZG3bMQQv30srHG8uVhLJ/0Pn/3s2GKJU89V7bdwFw5r9G6C', 'Mohammed Alasmari',  NULL, 'user',  'active', '2026-01-01 16:35:26', '2026-01-01 16:35:26', NULL),
  (18, 'Cubetechn@outlook.com',   '$2y$12$cq5QmbMdqKPVhuSFJ8pnpOxGkgJfm5nJ9DS3c6huksFvTZSBWyu9u', 'Cube Tech',          NULL, 'admin', 'active', '2026-01-31 14:32:20', '2026-02-24 18:19:43', '2026-02-24 18:19:43'),
  (19, 'Laalmadi@pnu.edu.sa',     '$2y$12$YBe5BsysQsA4mSWVivwig.nB4iSTQcDTHIov.FG3UxX1aYCocz6eG', 'Layla AL-Madi',      NULL, 'user',  'active', '2026-02-01 15:42:39', '2026-02-05 08:25:23', '2026-02-05 08:25:23'),
  (20, 'aalmusamih@gmail.com',    '$2y$12$y6570cTvT0yVHLQwAZd8oOi2pgwhRsIN.e2yAl1S1UkH362k46Eqa', 'Abdulrahman fahad',  NULL, 'user',  'active', '2026-02-10 16:49:21', '2026-02-10 16:49:21', NULL),
  (21, 'eng.ma.426@gmail.com',    '$2y$12$o759mKxheyiuZYzM0VZ3penpxCdUD65ysr6C8BlDx9Zo3pqWvFM5q', 'Mana  alahmari',     NULL, 'user',  'active', '2026-03-15 20:47:27', '2026-03-15 20:47:27', NULL);

ALTER TABLE `users` AUTO_INCREMENT = 22;
```

---

### Step 5 — Prepare upload folder

Created a `console/` folder locally (on Desktop) with this structure:

```
console/
├── .htaccess          ← from public/.htaccess
├── index.php          ← from public/index.php
├── index.html         ← from public/index.html  (built)
├── favicon.svg        ← from public/favicon.svg
├── assets/            ← from public/assets/      (built React JS/CSS/fonts)
├── src/               ← PHP controllers, repositories, services
├── vendor/            ← PHPMailer (3 files)
└── config.php         ← production credentials (renamed from config.prod.php)
```

**NOT included:** `admin_console_shadcn/`, root `.htaccess`, any `.sql` files, `config.sample.php`

This was created automatically via PowerShell — no manual copying needed next time, just run:

```powershell
$dest = "$env:USERPROFILE\Desktop\console"
New-Item -ItemType Directory -Force $dest | Out-Null
$pub = "C:\xampp\htdocs\server-php-auth\public"
Copy-Item "$pub\.htaccess", "$pub\index.php", "$pub\index.html", "$pub\favicon.svg" $dest -Force
Copy-Item "$pub\assets" $dest -Recurse -Force
Copy-Item "C:\xampp\htdocs\server-php-auth\src"    $dest -Recurse -Force
Copy-Item "C:\xampp\htdocs\server-php-auth\vendor" $dest -Recurse -Force
Copy-Item "C:\xampp\htdocs\server-php-auth\config.php" "$dest\config.php" -Force
```

---

### Step 6 — Upload to GoDaddy

1. Zipped `console/` → `console.zip`
2. In cPanel File Manager → navigated to `public_html/`
3. Uploaded `console.zip` and extracted in place
4. Result: `public_html/console/` live at `https://ssma.sa/console`

No document root change needed — it's a subdirectory of the existing domain.

---

## Verification Checklist

- [ ] `https://ssma.sa/console` → React login page loads
- [ ] `https://ssma.sa/console/api/health` → `{"status":"ok"}`
- [ ] Login as `f.m.alharbiy@gmail.com` (admin) works
- [ ] Users page shows 9 users
- [ ] Licenses page loads (empty on first deploy)
- [ ] Password reset OTP email arrives

---

## Re-deploying (Future Updates)

1. Make code changes locally
2. Run `npm run build:prod` inside `admin_console_shadcn/`
3. Run the PowerShell script from Step 5 above to rebuild the `console/` folder
4. Zip and re-upload to `public_html/` on GoDaddy, overwrite existing files

**DB changes only:** Run new SQL directly in phpMyAdmin — no need to re-upload files.

---

## Rollback

The old database `nupthasa_ai_api` is untouched on the server.  
To rollback: update `config.php` → change `db.name` to `nupthasa_ai_api` and re-upload.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| `#1044 Access denied` in phpMyAdmin | SQL file contains `USE \`ai_auth\`` | Remove that line, paste SQL directly in SQL tab |
| Assets 404 (`/assets/...` not found) | Vite `base` not set to `/console/` | Check `vite.config.ts` has `base: '/console/'`, rebuild |
| 500 on all API calls | Wrong DB credentials or missing `config.php` | Check `config.php` is in `console/` root, verify DB name/user/pass |
| 404 on `/api/*` routes | `.htaccess` missing or `mod_rewrite` off | Confirm `console/.htaccess` uploaded; contact GoDaddy to enable mod_rewrite |
| Blank white page | React JS bundle 404 | DevTools → Network tab → check if `assets/index-*.js` returns 404 |
| Login 401 | JWT secret mismatch | Verify `jwt.secret` in `config.php` matches what was originally set |
