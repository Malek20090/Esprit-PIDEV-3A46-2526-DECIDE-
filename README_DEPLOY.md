# Decide$ Production Deployment Guide (Symfony 6.4)

This guide prepares the project for safe production deployment without changing business logic.

## 1) What was checked
- `composer.json` dependencies and platform requirements
- Env strategy (`.env`, `.env.local`)
- Doctrine DB configuration (`config/packages/doctrine.yaml`)
- Front controller and web root (`public/index.php`)

## 2) Hosting requirements

## PHP
- Minimum: `PHP 8.1` (from `composer.json`)
- Recommended: `PHP 8.2` or `PHP 8.3`
- Required extensions (project + Symfony common runtime):
  - `ctype`
  - `iconv`
  - `json`
  - `mbstring`
  - `openssl`
  - `pdo`
  - `pdo_mysql`
  - `tokenizer`
  - `xml`
  - `fileinfo`
  - `intl` (recommended because `symfony/intl` is used)

## Database
- MySQL 8+ or MariaDB 10.4+ (your project already uses MariaDB in local setup)
- Charset/collation recommendation: `utf8mb4` / `utf8mb4_unicode_ci`

## Tools
- Composer 2.x
- Web server: Apache or Nginx
- Cron/worker capability if you later run background jobs (Messenger workers)

## 3) Web root must be `/public`

Symfony front controller is:
- [public/index.php](/C:/Users/rahma/mon_projet_symfony/public/index.php)

Configure hosting document root to the `public/` directory only.  
Do not point web root to project root.

## 4) Production environment variables

Use:
- `APP_ENV=prod`
- `APP_DEBUG=0`

A sanitized production template was added:
- [.env.prod.example](/C:/Users/rahma/mon_projet_symfony/.env.prod.example)

Important:
- Do not commit real credentials.
- Store production secrets in server environment variables or secret manager.
- Rotate any credentials that were previously committed in local env files.

## 5) Doctrine database configuration status

Current DB config uses:
- `url: '%env(resolve:DATABASE_URL)%'`
- From [config/packages/doctrine.yaml](/C:/Users/rahma/mon_projet_symfony/config/packages/doctrine.yaml)

So production DB is controlled by `DATABASE_URL`, for example:
`mysql://user:password@host:3306/decides_db?serverVersion=10.11.2-MariaDB&charset=utf8mb4`

## 6) Deployment steps (safe baseline)

1. Upload project files to server.
2. Set web root to `/public`.
3. Set production env vars from `.env.prod.example` values (with your real secrets).
4. Install dependencies:
   - `composer install --no-dev --optimize-autoloader`
5. Build optimized env (optional but recommended):
   - `composer dump-env prod`
6. Run DB migrations:
   - `php bin/console doctrine:migrations:migrate --no-interaction --env=prod`
7. Clear/warmup cache:
   - `php bin/console cache:clear --env=prod`
   - `php bin/console cache:warmup --env=prod`
8. Ensure writable permissions for:
   - `var/`
   - `public/uploads/` (if file uploads are used)
9. Smoke test key routes in browser.

## 7) Import local database to online server

Recommended sequence:
1. Export local DB with phpMyAdmin (SQL format) or `mysqldump`.
2. Create online DB (same schema charset `utf8mb4`).
3. Create dedicated DB user with limited privileges for that DB.
4. Import SQL dump into online DB.
5. Update Symfony `DATABASE_URL` to online DB credentials.
6. Run `doctrine:migrations:migrate` once more to ensure schema is aligned.
7. Validate rows from key tables (`user`, `transaction`, `revenue`, `expense`, `financial_goal`, etc.).

## 8) Java desktop app using same online DB later

When you want both apps to share one production DB:
1. Keep a single DB/schema (no duplication).
2. Set Java JDBC URL to same host/port/db name as Symfony.
3. Use matching table/column mappings in Java entities/queries.
4. Use UTC or aligned timezone handling in both apps.
5. After Java CRUD actions, refresh Java UI from DB (not cached lists).
6. In Symfony, keep page reloads reading from DB (already prepared by no-cache strategy).

Example JDBC:
`jdbc:mysql://DB_HOST:3306/DB_NAME?useSSL=true&serverTimezone=UTC&characterEncoding=utf8`

Security note:
- Prefer private network/VPN between app server and DB.
- Do not expose DB port publicly unless strictly required.
- If remote desktop access is needed, whitelist IPs and enforce strong credentials.

## 9) Quick production checklist

- [ ] `APP_ENV=prod`
- [ ] `APP_DEBUG=0`
- [ ] Web root points to `/public`
- [ ] Real secrets are not in Git
- [ ] `DATABASE_URL` points to production DB
- [ ] `composer install --no-dev` completed
- [ ] migrations executed
- [ ] cache warmed
- [ ] file permissions validated
- [ ] login + core CRUD smoke tests passed

## 10) Pre-deploy checklist script (simple)

A safe helper script was added:
- [scripts/predeploy-check.php](/C:/Users/rahma/mon_projet_symfony/scripts/predeploy-check.php)

It checks:
- PHP version
- `vendor/` installed
- `APP_ENV` and `APP_DEBUG`
- `DATABASE_URL` exists
- `public/index.php` exists (`/public` web root rule)
- `cache:clear --env=prod`
- Doctrine DB connection (`SELECT 1`)
- Doctrine migrations status

Run:
```bash
php scripts/predeploy-check.php
```

If your env values are in another file:
```bash
php scripts/predeploy-check.php --env-file=.env.local
```

## 11) Production deploy commands (simple sequence)

```bash
composer install --no-dev --optimize-autoloader
composer dump-env prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php scripts/predeploy-check.php
```

## 12) Rollback commands (if deployment fails)

Simple rollback idea for student projects:

1. Keep previous release folder (example: `releases/2026-05-13-prev`).
2. Switch web root symlink/path back to previous release.
3. Clear cache on previous release.

Example commands (Linux-style):
```bash
# Go back to previous code release
ln -sfn /var/www/decide/releases/2026-05-13-prev /var/www/decide/current

# Run cache clear on previous release
cd /var/www/decide/current
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

If a migration caused the issue:
- Restore DB from backup taken before migration import/apply.
- Then point app back to previous release.

