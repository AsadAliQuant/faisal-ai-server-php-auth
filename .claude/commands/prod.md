Make production files for server-php-auth. Follow this exact order — never skip steps, never just zip existing files.

**Step 1 — Build React app**

Run `npm run build:prod` inside `admin_console_shadcn/`:

```powershell
cd c:\xampp\htdocs\server-php-auth\admin_console_shadcn
npm run build:prod
```

Wait for it to finish. This builds Vite and xcopy's dist/* into `../public/`.

**Step 2 — Clear production/ folder (start fresh every time)**

```powershell
$dest = "c:\xampp\htdocs\server-php-auth\production"
Remove-Item "$dest\*" -Recurse -Force -ErrorAction SilentlyContinue
```

**Step 3 — Recreate directory structure**

```powershell
@(
  "$dest",
  "$dest\src\controllers",
  "$dest\src\repositories",
  "$dest\src\services",
  "$dest\vendor\phpmailer",
  "$dest\assets",
  "$dest\admin"
) | ForEach-Object { New-Item -ItemType Directory -Force $_ | Out-Null }
```

**Step 4 — Copy PHP source files from src/ → production/src/**

```powershell
$src = "c:\xampp\htdocs\server-php-auth"
Copy-Item "$src\src\util.php"                                        "$dest\src\util.php" -Force
Copy-Item "$src\src\db.php"                                          "$dest\src\db.php" -Force
Copy-Item "$src\src\jwt.php"                                         "$dest\src\jwt.php" -Force
Copy-Item "$src\src\auth.php"                                        "$dest\src\auth.php" -Force
Copy-Item "$src\src\RefreshTokenRepository.php"                      "$dest\src\RefreshTokenRepository.php" -Force
Copy-Item "$src\src\controllers\AuthController.php"                  "$dest\src\controllers\AuthController.php" -Force
Copy-Item "$src\src\controllers\AdminController.php"                 "$dest\src\controllers\AdminController.php" -Force
Copy-Item "$src\src\controllers\LicenseController.php"               "$dest\src\controllers\LicenseController.php" -Force
Copy-Item "$src\src\controllers\MeController.php"                    "$dest\src\controllers\MeController.php" -Force
Copy-Item "$src\src\controllers\PasswordResetController.php"         "$dest\src\controllers\PasswordResetController.php" -Force
Copy-Item "$src\src\repositories\UserRepository.php"                 "$dest\src\repositories\UserRepository.php" -Force
Copy-Item "$src\src\repositories\LicenseRepository.php"             "$dest\src\repositories\LicenseRepository.php" -Force
Copy-Item "$src\src\repositories\PasswordResetRepository.php"       "$dest\src\repositories\PasswordResetRepository.php" -Force
Copy-Item "$src\src\services\EmailService.php"                       "$dest\src\services\EmailService.php" -Force
```

**Step 5 — Copy frontend from public/ → production/**

```powershell
Copy-Item "$src\public\index.php"   "$dest\index.php"   -Force
Copy-Item "$src\public\index.html"  "$dest\index.html"  -Force
Copy-Item "$src\public\favicon.svg" "$dest\favicon.svg" -Force
Get-ChildItem "$src\public\assets" -File | ForEach-Object { Copy-Item $_.FullName "$dest\assets\$($_.Name)" -Force }
Copy-Item "$src\public\admin\*" "$dest\admin\" -Force
```

**Step 6 — Copy config.prod.php → production/config.php (NOT the dev config.php)**

```powershell
Copy-Item "$src\config.prod.php" "$dest\config.php" -Force
```

**Step 7 — Copy vendor/phpmailer/**

```powershell
Copy-Item "$src\vendor\phpmailer\*" "$dest\vendor\phpmailer\" -Force
```

**Step 8 — Copy migrate.sql and seed_admin.php**

```powershell
Copy-Item "$src\migrate.sql"    "$dest\migrate.sql"    -Force
Copy-Item "$src\seed_admin.php" "$dest\seed_admin.php" -Force
```

**Step 9 — Write the production .htaccess (different from dev — has DirectoryIndex + SPA fallback)**

Write this exact content to `production/.htaccess`:

```
DirectoryIndex index.html index.php

RewriteEngine On

# Pass Authorization header to PHP (stripped by Apache CGI/FastCGI)
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]

# Real files and directories → serve directly (React assets: JS, CSS, images)
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# API requests → PHP router
RewriteCond %{REQUEST_URI} /api/ [NC]
RewriteRule ^ index.php [QSA,L]

# SPA fallback → serve index.html for all other non-file routes
RewriteRule ^ index.html [L]
```

**Step 10 — Done. Do NOT zip.**

Report what was copied and confirm the production/ folder is ready. The user will zip it themselves.

Note: The `server` block in vite.config.ts does NOT affect `vite build` — only the dev server. No need to modify vite.config.ts before building. `base: '/console/'` is already correct for ssma.sa/console.
