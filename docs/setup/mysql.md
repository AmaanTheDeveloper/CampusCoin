# MySQL Setup — Campus Coin

**Status:** Competition/production target database  
**Phase:** 1 — Foundation

## Decision

Campus Coin targets **MySQL** for competition and production use.

Local development may temporarily use SQLite if MySQL is not yet configured. Phase 0 found `DB_CONNECTION=sqlite` in the local environment. That does **not** change the competition target.

MySQL connectivity was **not** verified during Phase 1. Do not assume MySQL is running until you complete the steps below and confirm a successful connection.

## Assumptions

- MySQL **8.0+** (or a compatible MariaDB 10.6+ if your host requires it)
- PHP with `pdo_mysql` enabled
- Character set `utf8mb4` / collation `utf8mb4_unicode_ci` (Laravel defaults)

## 1. Create the database

In MySQL:

```sql
CREATE DATABASE campus_coin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Create a dedicated database user with privileges on that database only. Do **not** commit real credentials.

## 2. Environment variables

Copy `.env.example` to `.env` if needed, then set:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=campus_coin
DB_USERNAME=your_mysql_username
DB_PASSWORD=your_mysql_password
```

`.env.example` contains placeholders only. Never put real passwords in `.env.example` or documentation.

Also ensure:

```env
APP_NAME="Campus Coin"
```

## 3. Generate app key (first-time setup)

```bash
php artisan key:generate
```

## 4. Run migrations

Non-destructive:

```bash
php artisan migrate
```

**Do not** run destructive commands (`migrate:fresh`, `migrate:refresh`, `db:wipe`) against shared or competition data unless explicitly approved.

## 5. Verify

```bash
php artisan db:show
php artisan migrate:status
```

## 6. Test database considerations

`phpunit.xml` uses an **in-memory SQLite** database for automated tests:

```xml
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

That is intentional for fast, isolated Pest runs and does **not** replace MySQL as the application target.

If you later add MySQL-specific features (fulltext indexes, JSON path queries), add CI coverage that exercises MySQL or keep tests dialect-safe.

## 7. Local SQLite fallback (optional)

For quick local UI work without MySQL:

```env
DB_CONNECTION=sqlite
# DB_DATABASE defaults to database/database.sqlite when using Laravel defaults
```

Switch back to MySQL before competition deployment verification.

## Safety rules

- Never commit `.env`
- Never expose credentials in docs, screenshots, or chat logs
- Never trust client-supplied ownership IDs in future domain modules
- Prefer `DECIMAL` for all monetary amounts (see database blueprint)
